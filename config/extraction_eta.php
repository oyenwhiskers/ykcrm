<?php

return [
    'workers' => max(1, (int) env('EXTRACTION_SERVICE_WORKERS', 2)),
    'historical_default_runtime_seconds' => max(5, (int) env('EXTRACTION_DEFAULT_IMAGE_SECONDS', 18)),
    'min_samples_for_batch_trust' => max(1, (int) env('EXTRACTION_ETA_MIN_SAMPLES', 5)),
    'trim_outliers_at' => max(3, (int) env('EXTRACTION_ETA_TRIM_OUTLIERS_AT', 8)),
    'processing_min_remaining_seconds' => max(1, (int) env('EXTRACTION_ETA_MIN_REMAINING_SECONDS', 3)),
    'straggler_ratio' => max(1.05, (float) env('EXTRACTION_ETA_STRAGGLER_RATIO', 1.35)),
    'straggler_multiplier' => max(1.05, (float) env('EXTRACTION_ETA_STRAGGLER_MULTIPLIER', 1.2)),
    'smoothing' => [
        'up_step_seconds' => max(1, (int) env('EXTRACTION_ETA_SMOOTH_UP_STEP', 6)),
        'down_step_seconds' => max(1, (int) env('EXTRACTION_ETA_SMOOTH_DOWN_STEP', 12)),
        'ttl_seconds' => max(60, (int) env('EXTRACTION_ETA_SMOOTH_TTL', 21600)),
    ],
    'confidence' => [
        'medium_at' => max(1, (int) env('EXTRACTION_ETA_CONFIDENCE_MEDIUM_AT', 5)),
        'high_at' => max(1, (int) env('EXTRACTION_ETA_CONFIDENCE_HIGH_AT', 15)),
    ],
    'buckets' => [
        'small' => [
            'max_kb' => max(1, (int) env('EXTRACTION_ETA_BUCKET_SMALL_MAX_KB', 80)),
            'historical_seconds' => max(5, (int) env('EXTRACTION_ETA_BUCKET_SMALL_SECONDS', 14)),
        ],
        'medium' => [
            'max_kb' => max(1, (int) env('EXTRACTION_ETA_BUCKET_MEDIUM_MAX_KB', 140)),
            'historical_seconds' => max(5, (int) env('EXTRACTION_ETA_BUCKET_MEDIUM_SECONDS', 18)),
        ],
        'large' => [
            'historical_seconds' => max(5, (int) env('EXTRACTION_ETA_BUCKET_LARGE_SECONDS', 24)),
        ],
        'ai_fallback' => [
            'historical_seconds' => max(5, (int) env('EXTRACTION_ETA_BUCKET_AI_FALLBACK_SECONDS', 32)),
        ],
    ],
];