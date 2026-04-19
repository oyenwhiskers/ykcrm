<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('extraction:cleanup-stale {--minutes= : Override the stale-batch cutoff in minutes}', function () {
    $minutes = $this->option('minutes');
    $cutoffMinutes = is_numeric($minutes)
        ? max(1, (int) $minutes)
        : (int) config('services.extraction.stale_batch_minutes', 30);

    $deleted = app(\App\Support\TemporaryExtractionBatchStore::class)
        ->cleanupStaleBatches($cutoffMinutes);

    $this->info("Removed {$deleted} stale extraction batch(es).");
})->purpose('Delete unfinished unsaved extraction batches that exceeded the stale timeout');

Schedule::command('extraction:cleanup-stale')->everyFiveMinutes()->withoutOverlapping();
