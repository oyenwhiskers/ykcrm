<?php

namespace App\Services\Extraction;

use App\Jobs\ProcessExtractionImage;
use App\Models\ExtractionBatch;
use App\Models\ExtractionImage;
use Illuminate\Support\Facades\Cache;

class ExtractionBatchScheduler
{
    private const LOCK_KEY = 'extraction-batch-scheduler';
    private const CURSOR_KEY = 'extraction-batch-scheduler:last-batch-id';

    public function dispatchAvailableSlots(): void
    {
        $this->lockStore()->lock(self::LOCK_KEY, 10)->block(5, function (): void {
            $availableSlots = max(0, $this->maxConcurrentJobs() - $this->inFlightImageCount());

            while ($availableSlots > 0) {
                $batchIds = ExtractionBatch::query()
                    ->whereIn('status', ['queued', 'processing'])
                    ->whereHas('extractionImages', fn ($query) => $query->where('status', 'pending'))
                    ->orderBy('created_at')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($batchIds === []) {
                    break;
                }

                $dispatchedInPass = 0;
                $lastBatchId = $this->lastBatchId();
                $orderedBatchIds = $this->rotateBatchIds($batchIds, $lastBatchId);

                foreach ($orderedBatchIds as $batchId) {
                    if ($availableSlots <= 0) {
                        break;
                    }

                    $image = ExtractionImage::query()
                        ->where('extraction_batch_id', $batchId)
                        ->where('status', 'pending')
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->first();

                    if (! $image) {
                        continue;
                    }

                    $updated = ExtractionImage::query()
                        ->whereKey($image->id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'queued',
                            'updated_at' => now(),
                        ]);

                    if (! $updated) {
                        continue;
                    }

                    ProcessExtractionImage::dispatch((string) $batchId, (string) $image->id);
                    $this->rememberLastBatchId($batchId);

                    $availableSlots--;
                    $dispatchedInPass++;
                }

                if ($dispatchedInPass === 0) {
                    break;
                }
            }
        });
    }

    private function maxConcurrentJobs(): int
    {
        $queueWorkers = max(1, (int) env('QUEUE_WORKER_PROCESSES', 1));
        $extractionWorkers = max(1, (int) config('services.extraction.workers', 1));

        return min($queueWorkers, $extractionWorkers);
    }

    private function inFlightImageCount(): int
    {
        return ExtractionImage::query()
            ->whereIn('status', ['queued', 'processing'])
            ->count();
    }

    private function rotateBatchIds(array $batchIds, ?int $lastBatchId): array
    {
        if ($lastBatchId === null) {
            return $batchIds;
        }

        $lastIndex = array_search($lastBatchId, $batchIds, true);

        if ($lastIndex === false) {
            return $batchIds;
        }

        $nextIndex = $lastIndex + 1;

        return [
            ...array_slice($batchIds, $nextIndex),
            ...array_slice($batchIds, 0, $nextIndex),
        ];
    }

    private function lastBatchId(): ?int
    {
        $value = $this->lockStore()->get(self::CURSOR_KEY);

        return $value === null ? null : (int) $value;
    }

    private function rememberLastBatchId(int $batchId): void
    {
        $this->lockStore()->forever(self::CURSOR_KEY, $batchId);
    }

    private function lockStore()
    {
        return Cache::store(config('cache.default', 'database'));
    }
}