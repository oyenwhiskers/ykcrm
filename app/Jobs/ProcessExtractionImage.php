<?php

namespace App\Jobs;

use App\Services\Extraction\ExtractionServiceClient;
use App\Support\TemporaryExtractionBatchStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessExtractionImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public string $batchId, public string $imageId)
    {
    }

    public function handle(ExtractionServiceClient $client, TemporaryExtractionBatchStore $temporaryBatches): void
    {
        $image = $temporaryBatches->findImage($this->batchId, $this->imageId);

        if (! $image) {
            return;
        }

        $temporaryBatches->updateImage($this->batchId, $this->imageId, function (array $batchImage): array {
            $batchImage['status'] = 'processing';
            $batchImage['attempts'] = ($batchImage['attempts'] ?? 0) + 1;
            $batchImage['started_at'] = now()->toIso8601String();
            $batchImage['error_message'] = null;

            return $batchImage;
        });

        try {
            $payload = $client->extract(
                $image['storage_disk'],
                $image['path'],
                $image['original_name'],
                [
                    'batch_id' => $this->batchId,
                    'extraction_image_id' => $this->imageId,
                ],
            );

            $temporaryBatches->updateImage($this->batchId, $this->imageId, function (array $batchImage) use ($payload): array {
                $records = collect($payload['records'] ?? []);

                $batchImage['status'] = 'completed';
                $batchImage['extracted_records'] = $records->count();
                $batchImage['confidence_avg'] = $records->avg(fn (array $record) => isset($record['confidence']) ? ((float) $record['confidence'] * 100) : null);
                $batchImage['raw_response'] = $payload;
                $batchImage['error_message'] = null;
                $batchImage['finished_at'] = now()->toIso8601String();

                return $batchImage;
            });
        } catch (Throwable $exception) {
            $temporaryBatches->updateImage($this->batchId, $this->imageId, function (array $batchImage) use ($exception): array {
                $batchImage['status'] = 'queued';
                $batchImage['error_message'] = $exception->getMessage();

                return $batchImage;
            });

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(TemporaryExtractionBatchStore::class)->updateImage($this->batchId, $this->imageId, function (array $batchImage) use ($exception): array {
            $batchImage['status'] = 'failed';
            $batchImage['error_message'] = $exception->getMessage();
            $batchImage['finished_at'] = now()->toIso8601String();

            return $batchImage;
        });
    }
}