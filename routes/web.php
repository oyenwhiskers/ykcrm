<?php

use App\Http\Controllers\ExtractionBatchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/extraction-batches', [ExtractionBatchController::class, 'store'])
    ->name('extraction-batches.store');
Route::get('/extraction-batches/{batchKey}', [ExtractionBatchController::class, 'show'])
    ->name('extraction-batches.show');
Route::post('/extraction-batches/{batchKey}/abandon', [ExtractionBatchController::class, 'abandon'])
    ->name('extraction-batches.abandon');
Route::delete('/extraction-batches/{batchKey}/images/{imageKey}', [ExtractionBatchController::class, 'destroyImage'])
    ->name('extraction-batches.images.destroy');
Route::post('/extraction-batches/{batchKey}/commit-leads', [ExtractionBatchController::class, 'commitLeads'])
    ->name('extraction-batches.commit-leads');
