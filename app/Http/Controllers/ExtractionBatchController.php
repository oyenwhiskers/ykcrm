<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExtractionImage;
use App\Models\ExtractedLead;
use App\Models\ExtractionBatch;
use App\Models\ExtractionImage;
use App\Services\Extraction\BatchEtaEstimator;
use App\Support\TemporaryExtractionBatchStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExtractionBatchController extends Controller
{
    public function __construct(
        private TemporaryExtractionBatchStore $temporaryBatches,
        private BatchEtaEstimator $etaEstimator,
    )
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:50'],
            'images.*' => ['required', 'image', 'max:15360'],
        ]);

        $disk = config('filesystems.default', 'local');

        $temporaryImages = collect($validated['images'])
            ->map(fn ($uploadedImage) => [
                'storage_disk' => $disk,
                'path' => $uploadedImage->store('extraction-batches/temp', $disk),
                'original_name' => $uploadedImage->getClientOriginalName(),
                'mime_type' => $uploadedImage->getMimeType(),
                'size' => $uploadedImage->getSize(),
            ])
            ->all();

        $batch = $this->temporaryBatches->create($temporaryImages, $request->user()?->id);

        foreach ($batch['images'] as $image) {
            ProcessExtractionImage::dispatch($batch['id'], $image['id']);
        }

        return response()->json([
            'data' => $this->serializeTemporaryBatch($batch),
        ], 201);
    }

    public function show(string $batchKey): JsonResponse
    {
        $batch = $this->temporaryBatches->find($batchKey);

        abort_unless($batch, 404);

        return response()->json([
            'data' => $this->serializeTemporaryBatch($batch),
        ]);
    }

    public function commitLeads(string $batchKey): JsonResponse
    {
        $batch = $this->temporaryBatches->find($batchKey);

        abort_unless($batch, 404);

        if (isset($batch['persisted_batch_id']) && $batch['persisted_batch_id']) {
            return response()->json([
                'message' => 'Extracted leads already saved.',
                'created_count' => 0,
                'data' => $this->serializeTemporaryBatch($batch),
            ]);
        }

        abort_unless(in_array($batch['status'], ['completed', 'completed_with_failures'], true), 422, 'Wait for extraction to finish before saving leads.');

        ['persisted_batch_id' => $persistedBatchId, 'created_count' => $createdCount] = DB::transaction(function () use ($batch): array {
            $created = 0;
            $persistedBatch = ExtractionBatch::create([
                'uploaded_by' => $batch['uploaded_by'] ?? null,
                'status' => $batch['status'],
                'total_images' => $batch['total_images'],
                'processed_images' => $batch['processed_images'],
                'succeeded_images' => $batch['succeeded_images'],
                'failed_images' => $batch['failed_images'],
                'metadata' => array_merge($batch['metadata'] ?? [], [
                    'temporary_batch_id' => $batch['id'],
                    'committed_at' => now()->toIso8601String(),
                ]),
            ]);

            foreach ($batch['images'] as $image) {
                $persistedImage = ExtractionImage::create([
                    'extraction_batch_id' => $persistedBatch->id,
                    'storage_disk' => $image['storage_disk'],
                    'path' => $image['path'],
                    'original_name' => $image['original_name'],
                    'mime_type' => $image['mime_type'] ?? null,
                    'size' => $image['size'] ?? null,
                    'status' => $image['status'],
                    'attempts' => $image['attempts'] ?? 0,
                    'extracted_records' => $image['extracted_records'] ?? 0,
                    'confidence_avg' => $image['confidence_avg'] ?? null,
                    'raw_response' => $image['raw_response'] ?? null,
                    'error_message' => $image['error_message'] ?? null,
                    'started_at' => ! empty($image['started_at']) ? CarbonImmutable::parse($image['started_at']) : null,
                    'finished_at' => ! empty($image['finished_at']) ? CarbonImmutable::parse($image['finished_at']) : null,
                    'created_at' => ! empty($image['created_at']) ? CarbonImmutable::parse($image['created_at']) : now(),
                    'updated_at' => now(),
                ]);

                $records = collect(data_get($image, 'raw_response.records', []));

                foreach ($records as $record) {
                    ExtractedLead::create([
                        'extraction_image_id' => $persistedImage->id,
                        'name' => $record['name'] ?? null,
                        'phone_number' => $record['phone_number'] ?? null,
                        'normalized_phone_number' => $record['normalized_phone_number'] ?? $record['phone_number'] ?? null,
                        'confidence_score' => isset($record['confidence']) ? round((float) $record['confidence'] * 100, 2) : null,
                        'raw_text' => $record['raw_text'] ?? null,
                        'review_status' => (($record['confidence'] ?? 0) < (float) config('services.extraction.low_confidence_threshold', 0.85))
                            ? 'needs_review'
                            : 'pending_approval',
                        'metadata' => $record['metadata'] ?? null,
                    ]);

                    $created++;
                }
            }

            return [
                'persisted_batch_id' => $persistedBatch->id,
                'created_count' => $created,
            ];
        });

        $batch = $this->temporaryBatches->markCommitted($batchKey, $persistedBatchId) ?? $batch;

        return response()->json([
            'message' => 'Extracted leads saved successfully.',
            'created_count' => $createdCount,
            'data' => $this->serializeTemporaryBatch($batch),
        ]);
    }

    public function destroyImage(string $batchKey, string $imageKey): JsonResponse
    {
        $batch = $this->temporaryBatches->find($batchKey);

        abort_unless($batch, 404);
        abort_if(! empty($batch['persisted_batch_id']), 422, 'Saved batches cannot remove images.');

        $image = collect($batch['images'] ?? [])->firstWhere('id', $imageKey);
        abort_unless($image, 404);

        $updatedBatch = $this->temporaryBatches->removeImage($batchKey, $imageKey);
        abort_unless($updatedBatch, 404);

        if (($image['status'] ?? null) !== 'processing') {
            Storage::disk($image['storage_disk'])->delete($image['path']);
        }

        return response()->json([
            'message' => 'Image removed from extraction batch.',
            'data' => $this->serializeTemporaryBatch($updatedBatch),
        ]);
    }

    private function serializeTemporaryBatch(array $batch): array
    {
        $isSaved = ! empty($batch['persisted_batch_id']);
        $eta = $this->etaEstimator->estimate($batch);
        $elapsed = $this->batchElapsed($batch);

        return [
            'id' => $batch['id'],
            'persisted_batch_id' => $batch['persisted_batch_id'] ?? null,
            'status' => $batch['status'],
            'created_at' => $batch['created_at'] ?? null,
            'committed_at' => $batch['committed_at'] ?? null,
            'total_images' => $batch['total_images'],
            'processed_images' => $batch['processed_images'],
            'succeeded_images' => $batch['succeeded_images'],
            'failed_images' => $batch['failed_images'],
            'eta_seconds' => $eta['eta_seconds_display'],
            'eta_seconds_raw' => $eta['eta_seconds_raw'],
            'eta_seconds_display' => $eta['eta_seconds_display'],
            'eta_label' => $eta['eta_label'],
            'eta_confidence' => $eta['confidence_level'],
            'eta_basis' => $eta['estimation_basis'],
            'elapsed_seconds' => $elapsed['seconds'],
            'elapsed_label' => $elapsed['label'],
            'images' => collect($batch['images'] ?? [])->map(fn (array $image) => [
                'id' => $image['id'],
                'status' => $image['status'],
                'original_name' => $image['original_name'],
                'size' => $image['size'] ?? null,
                'extracted_records' => $image['extracted_records'] ?? 0,
                'confidence_avg' => $image['confidence_avg'] ?? null,
                'error_message' => $image['error_message'] ?? null,
                'created_at' => $image['created_at'] ?? null,
                'started_at' => $image['started_at'] ?? null,
                'finished_at' => $image['finished_at'] ?? null,
                'leads' => $this->serializeTemporaryImageLeads($image, $isSaved),
            ])->values()->all(),
        ];
    }

    private function serializeTemporaryImageLeads(array $image, bool $isSaved): array
    {
        return collect(data_get($image, 'raw_response.records', []))
            ->map(function (array $record) use ($isSaved) {
                return [
                    'id' => null,
                    'name' => $record['name'] ?? null,
                    'phone_number' => $record['phone_number'] ?? null,
                    'normalized_phone_number' => $record['normalized_phone_number'] ?? null,
                    'confidence_score' => isset($record['confidence']) ? round((float) $record['confidence'] * 100, 2) : null,
                    'review_status' => (($record['confidence'] ?? 0) < (float) config('services.extraction.low_confidence_threshold', 0.85) ? 'needs_review' : 'pending_approval'),
                    'is_saved' => $isSaved,
                ];
            })
            ->values()
            ->all();
    }

    private function batchElapsed(array $batch): array
    {
        $images = collect($batch['images'] ?? []);
        $batchCreatedAt = $this->parseTimestampMs($batch['created_at'] ?? null);
        $startedAt = $images
            ->map(fn (array $image) => $this->parseTimestampMs($image['started_at'] ?? null))
            ->filter()
            ->min() ?? $batchCreatedAt;
        $endedAt = $images
            ->map(fn (array $image) => $this->parseTimestampMs($image['finished_at'] ?? null))
            ->filter()
            ->max();

        if (! $startedAt) {
            return [
                'seconds' => 0,
                'label' => '0s elapsed',
            ];
        }

        $effectiveEnd = in_array($batch['status'] ?? 'queued', ['completed', 'completed_with_failures'], true)
            ? ($endedAt ?? $startedAt)
            : (int) round(microtime(true) * 1000);
        $elapsedSeconds = max(0, (int) ceil(($effectiveEnd - $startedAt) / 1000));

        return [
            'seconds' => $elapsedSeconds,
            'label' => $this->formatElapsedLabel($elapsedSeconds),
        ];
    }

    private function parseTimestampMs(?string $timestamp): ?int
    {
        if (! $timestamp) {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp)->valueOf();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatElapsedLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s elapsed';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($remainingSeconds === 0) {
            return $minutes.'m elapsed';
        }

        return $minutes.'m '.$remainingSeconds.'s elapsed';
    }
}