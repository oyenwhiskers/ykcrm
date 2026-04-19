<?php

namespace App\Services\Extraction;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BatchEtaEstimator
{
    public function estimate(array $batch): array
    {
        $images = collect($batch['images'] ?? []);
        $processingImages = $images->where('status', 'processing')->values();
        $queuedImages = $images->where('status', 'queued')->values();
        $completedImages = $images->where('status', 'completed')->values();

        if ($processingImages->isEmpty() && $queuedImages->isEmpty()) {
            $this->forgetSmoothingState($batch['id']);

            return [
                'eta_seconds_raw' => 0,
                'eta_seconds_display' => 0,
                'eta_label' => 'Finished',
                'confidence_level' => 'high',
                'estimation_basis' => 'batch',
            ];
        }

        $completedCount = $completedImages->count();
        $globalRuntime = $this->computeEffectiveRuntime($completedImages->map(fn (array $image) => $this->imageDurationMs($image))->filter(), null, $completedCount);

        $processingDurations = $processingImages
            ->map(fn (array $image) => $this->estimateProcessingRemaining(
                $image,
                $this->runtimeForImage($image, $completedImages, $globalRuntime, $completedCount),
            ));
        $queuedDurations = $queuedImages
            ->map(fn (array $image) => $this->runtimeForImage($image, $completedImages, $globalRuntime, $completedCount)['runtime_ms']);

        $rawEtaSeconds = $this->distributeAcrossWorkers(
            $processingDurations->all(),
            $queuedDurations->all(),
            $this->effectiveWorkerCount($processingImages->count() + $queuedImages->count()),
        );
        $displayEtaSeconds = $this->smoothEta((string) $batch['id'], $rawEtaSeconds);
        $confidence = $this->computeConfidence($completedCount, $processingImages->count(), $queuedImages->count(), $globalRuntime['spread_ratio']);

        return [
            'eta_seconds_raw' => $rawEtaSeconds,
            'eta_seconds_display' => $displayEtaSeconds,
            'eta_label' => $this->formatEtaLabel($displayEtaSeconds),
            'confidence_level' => $confidence,
            'estimation_basis' => $globalRuntime['basis'],
        ];
    }

    private function runtimeForImage(array $image, Collection $completedImages, array $globalRuntime, int $completedCount): array
    {
        $bucket = $this->classifyImageBucket($image);
        $bucketRuntime = $this->computeEffectiveRuntime(
            $completedImages
                ->filter(fn (array $completedImage) => $this->classifyImageBucket($completedImage) === $bucket)
                ->map(fn (array $completedImage) => $this->imageDurationMs($completedImage))
                ->filter(),
            $bucket,
            $completedCount,
        );

        if ($bucketRuntime['sample_count'] >= 2 || $globalRuntime['basis'] === 'historical') {
            return $bucketRuntime;
        }

        return [
            'runtime_ms' => (int) round(($bucketRuntime['runtime_ms'] + $globalRuntime['runtime_ms']) / 2),
            'basis' => $globalRuntime['basis'] === 'batch' ? 'blended' : $globalRuntime['basis'],
            'sample_count' => $bucketRuntime['sample_count'],
            'spread_ratio' => max($bucketRuntime['spread_ratio'], $globalRuntime['spread_ratio']),
        ];
    }

    private function classifyImageBucket(array $image): string
    {
        $metadata = Arr::get($image, 'raw_response.meta', []);

        if (Arr::get($metadata, 'ai_fallback') === true) {
            return 'ai_fallback';
        }

        $sizeKb = ((int) ($image['size'] ?? 0)) / 1024;
        $smallMaxKb = (int) config('extraction_eta.buckets.small.max_kb', 80);
        $mediumMaxKb = (int) config('extraction_eta.buckets.medium.max_kb', 140);

        return match (true) {
            $sizeKb <= $smallMaxKb => 'small',
            $sizeKb <= $mediumMaxKb => 'medium',
            default => 'large',
        };
    }

    private function computeEffectiveRuntime(Collection $durationsMs, ?string $bucket, int $completedCount): array
    {
        $historicalRuntimeMs = $this->historicalRuntimeMs($bucket);
        $robustRuntimeMs = $this->computeRobustRuntime($durationsMs);
        $minSamples = (int) config('extraction_eta.min_samples_for_batch_trust', 5);
        $sampleCount = $durationsMs->count();

        if ($robustRuntimeMs === null) {
            return [
                'runtime_ms' => $historicalRuntimeMs,
                'basis' => 'historical',
                'sample_count' => 0,
                'spread_ratio' => 0.0,
            ];
        }

        if ($sampleCount >= $minSamples) {
            return [
                'runtime_ms' => $robustRuntimeMs,
                'basis' => 'batch',
                'sample_count' => $sampleCount,
                'spread_ratio' => $this->runtimeSpreadRatio($durationsMs),
            ];
        }

        $weight = min(1, max(0, $completedCount / $minSamples));
        $blendedRuntimeMs = $this->computeBlendedRuntime($robustRuntimeMs, $historicalRuntimeMs, $weight);

        return [
            'runtime_ms' => $blendedRuntimeMs,
            'basis' => 'blended',
            'sample_count' => $sampleCount,
            'spread_ratio' => $this->runtimeSpreadRatio($durationsMs),
        ];
    }

    private function computeRobustRuntime(Collection $durationsMs): ?int
    {
        if ($durationsMs->isEmpty()) {
            return null;
        }

        $sorted = $durationsMs->sort()->values();
        $count = $sorted->count();

        if ($count >= (int) config('extraction_eta.trim_outliers_at', 8)) {
            $trimmed = $sorted->slice(1, $count - 2);

            return (int) round($trimmed->avg());
        }

        if ($count >= 3) {
            return (int) $sorted[(int) floor($count / 2)];
        }

        return (int) round($sorted->avg());
    }

    private function computeBlendedRuntime(int $batchRuntimeMs, int $historicalRuntimeMs, float $batchWeight): int
    {
        return (int) round(($batchRuntimeMs * $batchWeight) + ($historicalRuntimeMs * (1 - $batchWeight)));
    }

    private function estimateProcessingRemaining(array $image, array $runtimeProfile): int
    {
        $nowMs = (int) round(microtime(true) * 1000);
        $elapsedMs = $this->imageElapsedMs($image, $nowMs);
        $predictedTotalMs = $runtimeProfile['runtime_ms'];
        $minRemainingMs = (int) config('extraction_eta.processing_min_remaining_seconds', 3) * 1000;
        $stragglerRatio = (float) config('extraction_eta.straggler_ratio', 1.35);
        $stragglerMultiplier = (float) config('extraction_eta.straggler_multiplier', 1.2);

        if ($elapsedMs > ($predictedTotalMs * $stragglerRatio)) {
            $predictedTotalMs = (int) round($elapsedMs * $stragglerMultiplier);
        }

        return max($minRemainingMs, $predictedTotalMs - $elapsedMs);
    }

    private function distributeAcrossWorkers(array $processingDurationsMs, array $queuedDurationsMs, int $workerCount): int
    {
        $lanes = array_fill(0, max(1, $workerCount), 0);

        foreach (array_merge($processingDurationsMs, $queuedDurationsMs) as $durationMs) {
            $leastBusyIndex = array_keys($lanes, min($lanes), true)[0] ?? 0;
            $lanes[$leastBusyIndex] += (int) $durationMs;
        }

        return (int) ceil((max($lanes) ?: 0) / 1000);
    }

    private function smoothEta(string $batchId, int $rawEtaSeconds): int
    {
        $cacheKey = 'extraction-eta-display:'.$batchId;
        $state = Cache::store('file')->get($cacheKey);

        if (! is_array($state) || ! array_key_exists('display_seconds', $state)) {
            Cache::store('file')->put($cacheKey, ['display_seconds' => $rawEtaSeconds], (int) config('extraction_eta.smoothing.ttl_seconds', 21600));
            return $rawEtaSeconds;
        }

        $currentDisplay = (int) $state['display_seconds'];
        $upStep = (int) config('extraction_eta.smoothing.up_step_seconds', 6);
        $downStep = (int) config('extraction_eta.smoothing.down_step_seconds', 12);

        $nextDisplay = match (true) {
            $rawEtaSeconds > $currentDisplay => min($rawEtaSeconds, $currentDisplay + $upStep),
            $rawEtaSeconds < $currentDisplay => max($rawEtaSeconds, $currentDisplay - $downStep),
            default => $currentDisplay,
        };

        Cache::store('file')->put($cacheKey, ['display_seconds' => $nextDisplay], (int) config('extraction_eta.smoothing.ttl_seconds', 21600));

        return $nextDisplay;
    }

    private function computeConfidence(int $completedCount, int $processingCount, int $queuedCount, float $spreadRatio): string
    {
        $mediumAt = (int) config('extraction_eta.confidence.medium_at', 5);
        $highAt = (int) config('extraction_eta.confidence.high_at', 15);

        $confidence = match (true) {
            $completedCount >= $highAt => 'high',
            $completedCount >= $mediumAt => 'medium',
            default => 'low',
        };

        if ($spreadRatio > 0.65 && $confidence !== 'low') {
            return $confidence === 'high' ? 'medium' : 'low';
        }

        if (($processingCount + $queuedCount) > max(1, $completedCount * 2) && $confidence === 'high') {
            return 'medium';
        }

        return $confidence;
    }

    private function effectiveWorkerCount(int $remainingJobs): int
    {
        return max(1, min((int) config('extraction_eta.workers', 2), max(1, $remainingJobs)));
    }

    private function historicalRuntimeMs(?string $bucket): int
    {
        $seconds = $bucket
            ? (int) config('extraction_eta.buckets.'.$bucket.'.historical_seconds', config('extraction_eta.historical_default_runtime_seconds', 18))
            : (int) config('extraction_eta.historical_default_runtime_seconds', 18);

        return max(5, $seconds) * 1000;
    }

    private function imageDurationMs(array $image): ?int
    {
        $startedAt = $this->parseTimestampMs(Arr::get($image, 'started_at'));
        $finishedAt = $this->parseTimestampMs(Arr::get($image, 'finished_at'));

        if ($startedAt === null || $finishedAt === null || $finishedAt <= $startedAt) {
            return null;
        }

        return $finishedAt - $startedAt;
    }

    private function imageElapsedMs(array $image, int $nowMs): int
    {
        $startedAt = $this->parseTimestampMs(Arr::get($image, 'started_at'))
            ?? $this->parseTimestampMs(Arr::get($image, 'created_at'))
            ?? $nowMs;

        return max(0, $nowMs - $startedAt);
    }

    private function runtimeSpreadRatio(Collection $durationsMs): float
    {
        if ($durationsMs->count() < 2) {
            return 0.0;
        }

        $min = (float) $durationsMs->min();
        $max = (float) $durationsMs->max();

        if ($min <= 0) {
            return 0.0;
        }

        return max(0.0, ($max - $min) / $min);
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

    private function formatEtaLabel(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Finished';
        }

        if ($seconds < 60) {
            return $seconds.'s left';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($remainingSeconds === 0) {
            return $minutes.'m left';
        }

        return $minutes.'m '.$remainingSeconds.'s left';
    }

    private function forgetSmoothingState(string $batchId): void
    {
        Cache::store('file')->forget('extraction-eta-display:'.$batchId);
    }
}