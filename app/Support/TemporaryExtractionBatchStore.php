<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TemporaryExtractionBatchStore
{
    private const TTL_SECONDS = 21600;

    public function create(array $images, ?int $uploadedBy): array
    {
        $batchId = (string) Str::uuid();
        $createdAt = now()->toIso8601String();

        $batch = [
            'id' => $batchId,
            'persisted_batch_id' => null,
            'uploaded_by' => $uploadedBy,
            'status' => 'queued',
            'created_at' => $createdAt,
            'committed_at' => null,
            'metadata' => [
                'source' => 'manual-upload',
            ],
            'total_images' => count($images),
            'processed_images' => 0,
            'succeeded_images' => 0,
            'failed_images' => 0,
            'images' => array_map(function (array $image) use ($createdAt): array {
                return [
                    'id' => (string) Str::uuid(),
                    'storage_disk' => $image['storage_disk'],
                    'path' => $image['path'],
                    'original_name' => $image['original_name'],
                    'mime_type' => $image['mime_type'],
                    'size' => $image['size'],
                    'status' => 'queued',
                    'attempts' => 0,
                    'extracted_records' => 0,
                    'confidence_avg' => null,
                    'raw_response' => null,
                    'error_message' => null,
                    'created_at' => $createdAt,
                    'started_at' => null,
                    'finished_at' => null,
                ];
            }, $images),
        ];

        $this->store()->put($this->key($batchId), $batch, self::TTL_SECONDS);

        return $batch;
    }

    public function find(string $batchId): ?array
    {
        return $this->store()->get($this->key($batchId));
    }

    public function findImage(string $batchId, string $imageId): ?array
    {
        $batch = $this->find($batchId);

        if (! $batch) {
            return null;
        }

        return collect($batch['images'] ?? [])->firstWhere('id', $imageId);
    }

    public function updateImage(string $batchId, string $imageId, callable $mutator): ?array
    {
        return $this->withLock($batchId, function (?array $batch) use ($imageId, $mutator): ?array {
            if (! $batch) {
                return null;
            }

            $batch['images'] = collect($batch['images'] ?? [])
                ->map(function (array $image) use ($imageId, $mutator): array {
                    if ($image['id'] !== $imageId) {
                        return $image;
                    }

                    return $mutator($image) ?? $image;
                })
                ->all();

            return $this->persist($this->recalculate($batch));
        });
    }

    public function markCommitted(string $batchId, int $persistedBatchId): ?array
    {
        return $this->withLock($batchId, function (?array $batch) use ($persistedBatchId): ?array {
            if (! $batch) {
                return null;
            }

            $batch['persisted_batch_id'] = $persistedBatchId;
            $batch['committed_at'] = now()->toIso8601String();

            return $this->persist($batch);
        });
    }

    public function removeImage(string $batchId, string $imageId): ?array
    {
        return $this->withLock($batchId, function (?array $batch) use ($imageId): ?array {
            if (! $batch) {
                return null;
            }

            $batch['images'] = collect($batch['images'] ?? [])
                ->reject(fn (array $image) => $image['id'] === $imageId)
                ->values()
                ->all();

            return $this->persist($this->recalculate($batch));
        });
    }

    private function recalculate(array $batch): array
    {
        $images = collect($batch['images'] ?? []);
        $batch['total_images'] = $images->count();
        $processed = $images->whereIn('status', ['completed', 'failed'])->count();
        $succeeded = $images->where('status', 'completed')->count();
        $failed = $images->where('status', 'failed')->count();
        $hasProcessing = $images->contains(fn (array $image) => $image['status'] === 'processing');

        $batch['processed_images'] = $processed;
        $batch['succeeded_images'] = $succeeded;
        $batch['failed_images'] = $failed;
        $batch['status'] = match (true) {
            $batch['total_images'] === 0 => 'queued',
            $failed > 0 && $processed === (int) $batch['total_images'] => 'completed_with_failures',
            $processed === (int) $batch['total_images'] => 'completed',
            $processed > 0 || $hasProcessing => 'processing',
            default => 'queued',
        };

        return $batch;
    }

    private function persist(array $batch): array
    {
        $this->store()->put($this->key($batch['id']), $batch, self::TTL_SECONDS);

        return $batch;
    }

    private function withLock(string $batchId, callable $callback): ?array
    {
        return $this->store()->lock($this->lockKey($batchId), 10)->block(5, function () use ($batchId, $callback): ?array {
            return $callback($this->find($batchId));
        });
    }

    private function key(string $batchId): string
    {
        return 'temporary-extraction-batch:'.$batchId;
    }

    private function lockKey(string $batchId): string
    {
        return 'temporary-extraction-batch-lock:'.$batchId;
    }

    private function store(): CacheRepository
    {
        return Cache::store('file');
    }
}