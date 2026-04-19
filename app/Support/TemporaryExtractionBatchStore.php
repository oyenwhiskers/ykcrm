<?php

namespace App\Support;

use App\Models\ExtractionBatch;
use App\Models\ExtractionImage;
use App\Services\Extraction\ExtractionBatchScheduler;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TemporaryExtractionBatchStore
{
    public function create(array $images, ?int $uploadedBy): array
    {
        $batch = DB::transaction(function () use ($images, $uploadedBy): ExtractionBatch {
            $batch = ExtractionBatch::query()->create([
                'uploaded_by' => $uploadedBy,
                'status' => 'queued',
                'total_images' => count($images),
                'processed_images' => 0,
                'succeeded_images' => 0,
                'failed_images' => 0,
                'metadata' => [
                    'source' => 'manual-upload',
                ],
            ]);

            foreach ($images as $image) {
                $batch->extractionImages()->create([
                    'storage_disk' => $image['storage_disk'],
                    'path' => $image['path'],
                    'original_name' => $image['original_name'],
                    'mime_type' => $image['mime_type'] ?? null,
                    'size' => $image['size'] ?? null,
                    'status' => 'pending',
                    'attempts' => 0,
                    'extracted_records' => 0,
                    'confidence_avg' => null,
                    'raw_response' => null,
                    'error_message' => null,
                    'started_at' => null,
                    'finished_at' => null,
                ]);
            }

            return $batch->fresh('extractionImages');
        });

        app(ExtractionBatchScheduler::class)->dispatchAvailableSlots();

        return $this->serializeBatch($batch->fresh('extractionImages.extractedLeads'));
    }

    public function find(string $batchId): ?array
    {
        $batch = ExtractionBatch::query()
            ->with(['extractionImages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->find($batchId);

        return $batch ? $this->serializeBatch($batch) : null;
    }

    public function findImage(string $batchId, string $imageId): ?array
    {
        $image = ExtractionImage::query()
            ->where('extraction_batch_id', $batchId)
            ->find($imageId);

        return $image ? $this->serializeImage($image) : null;
    }

    public function updateImage(string $batchId, string $imageId, callable $mutator): ?array
    {
        return $this->withLock($batchId, function () use ($batchId, $imageId, $mutator): ?array {
            return DB::transaction(function () use ($batchId, $imageId, $mutator): ?array {
                $batch = ExtractionBatch::query()->with('extractionImages')->lockForUpdate()->find($batchId);

                if (! $batch) {
                    return null;
                }

                $image = $batch->extractionImages->firstWhere('id', (int) $imageId);

                if (! $image) {
                    return null;
                }

                $current = $this->serializeImage($image);
                $updated = $mutator($current) ?? $current;

                $image->fill([
                    'storage_disk' => Arr::get($updated, 'storage_disk', $image->storage_disk),
                    'path' => Arr::get($updated, 'path', $image->path),
                    'original_name' => Arr::get($updated, 'original_name', $image->original_name),
                    'mime_type' => Arr::get($updated, 'mime_type', $image->mime_type),
                    'size' => Arr::get($updated, 'size', $image->size),
                    'status' => Arr::get($updated, 'status', $image->status),
                    'attempts' => Arr::get($updated, 'attempts', $image->attempts),
                    'extracted_records' => Arr::get($updated, 'extracted_records', $image->extracted_records),
                    'confidence_avg' => Arr::get($updated, 'confidence_avg', $image->confidence_avg),
                    'raw_response' => Arr::get($updated, 'raw_response', $image->raw_response),
                    'error_message' => Arr::get($updated, 'error_message', $image->error_message),
                    'started_at' => $this->nullableTimestamp(Arr::get($updated, 'started_at')),
                    'finished_at' => $this->nullableTimestamp(Arr::get($updated, 'finished_at')),
                ]);
                $image->save();

                return $this->persistBatch($batch->fresh('extractionImages.extractedLeads'));
            });
        });
    }

    public function markCommitted(string $batchId, int $persistedBatchId): ?array
    {
        return $this->withLock($batchId, function () use ($batchId, $persistedBatchId): ?array {
            return DB::transaction(function () use ($batchId, $persistedBatchId): ?array {
                $batch = ExtractionBatch::query()->with('extractionImages.extractedLeads')->lockForUpdate()->find($batchId);

                if (! $batch) {
                    return null;
                }

                $metadata = $batch->metadata ?? [];
                $metadata['persisted_batch_id'] = $persistedBatchId;
                $metadata['committed_at'] = now()->toIso8601String();
                $batch->metadata = $metadata;
                $batch->save();

                return $this->persistBatch($batch);
            });
        });
    }

    public function removeImage(string $batchId, string $imageId): ?array
    {
        return $this->withLock($batchId, function () use ($batchId, $imageId): ?array {
            return DB::transaction(function () use ($batchId, $imageId): ?array {
                $batch = ExtractionBatch::query()->with('extractionImages')->lockForUpdate()->find($batchId);

                if (! $batch) {
                    return null;
                }

                ExtractionImage::query()
                    ->where('extraction_batch_id', $batchId)
                    ->whereKey($imageId)
                    ->delete();

                return $this->persistBatch($batch->fresh('extractionImages.extractedLeads'));
            });
        });
    }

    public function abandon(string $batchId): ?array
    {
        return $this->withLock($batchId, function () use ($batchId): ?array {
            return DB::transaction(function () use ($batchId): ?array {
                $batch = ExtractionBatch::query()
                    ->with(['extractionImages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
                    ->lockForUpdate()
                    ->find($batchId);

                if (! $batch) {
                    return null;
                }

                $snapshot = $this->serializeBatch($batch);
                $batch->delete();

                return $snapshot;
            });
        });
    }

    public function cleanupStaleBatches(int $minutes): int
    {
        $cutoff = now()->subMinutes(max(1, $minutes));
        $deleted = 0;

        $batchIds = ExtractionBatch::query()
            ->where('created_at', '<=', $cutoff)
            ->whereNotIn('status', ['completed', 'completed_with_failures'])
            ->orderBy('id')
            ->pluck('id');

        foreach ($batchIds as $batchId) {
            $removed = $this->withLock((string) $batchId, function () use ($batchId, $cutoff, &$deleted): ?bool {
                $fileTargets = DB::transaction(function () use ($batchId, $cutoff): ?array {
                    $batch = ExtractionBatch::query()
                        ->with(['extractionImages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
                        ->lockForUpdate()
                        ->find($batchId);

                    if (! $batch) {
                        return null;
                    }

                    if ($batch->created_at === null || $batch->created_at->gt($cutoff)) {
                        return null;
                    }

                    if (in_array($batch->status, ['completed', 'completed_with_failures'], true)) {
                        return null;
                    }

                    $metadata = $batch->metadata ?? [];

                    if (! empty($metadata['persisted_batch_id'])) {
                        return null;
                    }

                    $files = $batch->extractionImages
                        ->map(fn (ExtractionImage $image) => [
                            'disk' => $image->storage_disk,
                            'path' => $image->path,
                        ])
                        ->all();

                    $batch->delete();

                    return $files;
                });

                if ($fileTargets === null) {
                    return null;
                }

                foreach ($fileTargets as $fileTarget) {
                    $this->deleteStoredFile($fileTarget['disk'], $fileTarget['path']);
                }

                $deleted++;

                return true;
            });

            if ($removed === null) {
                continue;
            }
        }

        if ($deleted > 0) {
            app(ExtractionBatchScheduler::class)->dispatchAvailableSlots();
        }

        return $deleted;
    }

    private function persistBatch(ExtractionBatch $batch): array
    {
        $images = $batch->extractionImages;
        $processed = $images->whereIn('status', ['completed', 'failed'])->count();
        $succeeded = $images->where('status', 'completed')->count();
        $failed = $images->where('status', 'failed')->count();
        $hasInFlight = $images->contains(fn (ExtractionImage $image) => in_array($image->status, ['queued', 'processing'], true));

        $batch->forceFill([
            'total_images' => $images->count(),
            'processed_images' => $processed,
            'succeeded_images' => $succeeded,
            'failed_images' => $failed,
            'status' => match (true) {
                $images->isEmpty() => 'queued',
                $failed > 0 && $processed === $images->count() => 'completed_with_failures',
                $processed === $images->count() => 'completed',
                $processed > 0 || $hasInFlight => 'processing',
                default => 'queued',
            },
        ])->save();

        return $this->serializeBatch($batch->fresh('extractionImages.extractedLeads'));
    }

    private function serializeBatch(ExtractionBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];

        return [
            'id' => (string) $batch->id,
            'persisted_batch_id' => isset($metadata['persisted_batch_id']) ? (int) $metadata['persisted_batch_id'] : null,
            'uploaded_by' => $batch->uploaded_by,
            'status' => $batch->status,
            'created_at' => $batch->created_at?->toIso8601String(),
            'committed_at' => $metadata['committed_at'] ?? null,
            'metadata' => $metadata,
            'total_images' => $batch->total_images,
            'processed_images' => $batch->processed_images,
            'succeeded_images' => $batch->succeeded_images,
            'failed_images' => $batch->failed_images,
            'images' => $batch->extractionImages
                ->sortBy(fn (ExtractionImage $image) => $image->created_at?->getTimestamp() ?? 0)
                ->values()
                ->map(fn (ExtractionImage $image) => $this->serializeImage($image))
                ->all(),
        ];
    }

    private function serializeImage(ExtractionImage $image): array
    {
        return [
            'id' => (string) $image->id,
            'storage_disk' => $image->storage_disk,
            'path' => $image->path,
            'original_name' => $image->original_name,
            'mime_type' => $image->mime_type,
            'size' => $image->size,
            'status' => $this->displayStatus($image->status),
            'attempts' => $image->attempts,
            'extracted_records' => $image->extracted_records,
            'confidence_avg' => $image->confidence_avg,
            'raw_response' => $image->raw_response,
            'error_message' => $image->error_message,
            'created_at' => $image->created_at?->toIso8601String(),
            'started_at' => $image->started_at?->toIso8601String(),
            'finished_at' => $image->finished_at?->toIso8601String(),
        ];
    }

    private function displayStatus(string $status): string
    {
        return $status === 'pending' ? 'queued' : $status;
    }

    private function nullableTimestamp(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function withLock(string $batchId, callable $callback): mixed
    {
        return $this->store()->lock($this->lockKey($batchId), 10)->block(5, $callback);
    }

    private function deleteStoredFile(string $disk, string $path): void
    {
        try {
            \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);
        } catch (\Throwable) {
            // Database cleanup is more important than orphaned temp files.
        }
    }

    private function lockKey(string $batchId): string
    {
        return 'temporary-extraction-batch-lock:'.$batchId;
    }

    private function store(): CacheRepository
    {
        return Cache::store(config('cache.default', 'database'));
    }
}