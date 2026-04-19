<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'extraction_batch_id',
    'storage_disk',
    'path',
    'original_name',
    'mime_type',
    'size',
    'status',
    'attempts',
    'extracted_records',
    'confidence_avg',
    'raw_response',
    'error_message',
    'started_at',
    'finished_at',
])]
class ExtractionImage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function extractionBatch(): BelongsTo
    {
        return $this->belongsTo(ExtractionBatch::class);
    }

    public function extractedLeads(): HasMany
    {
        return $this->hasMany(ExtractedLead::class);
    }
}