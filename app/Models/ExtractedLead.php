<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'extraction_image_id',
    'name',
    'phone_number',
    'normalized_phone_number',
    'confidence_score',
    'raw_text',
    'review_status',
    'metadata',
])]
class ExtractedLead extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function extractionImage(): BelongsTo
    {
        return $this->belongsTo(ExtractionImage::class);
    }
}