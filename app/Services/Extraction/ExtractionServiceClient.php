<?php

namespace App\Services\Extraction;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExtractionServiceClient
{
    public function extract(string $storageDisk, string $path, string $originalName, array $context = []): array
    {
        $request = Http::baseUrl(rtrim((string) config('services.extraction.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('services.extraction.timeout', 60));

        if ($token = config('services.extraction.token')) {
            $request = $request->withToken($token);
        }

        $response = $request
            ->attach(
                'file',
                Storage::disk($storageDisk)->get($path),
                $originalName,
            )
            ->post('/v1/extractions/images', [
                'batch_id' => $context['batch_id'] ?? null,
                'extraction_image_id' => $context['extraction_image_id'] ?? null,
                'original_name' => $originalName,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Extraction service request failed: '.$response->body());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! array_key_exists('records', $payload)) {
            throw new RuntimeException('Extraction service returned an invalid payload.');
        }

        return [
            'engine' => Arr::get($payload, 'engine'),
            'raw_text' => Arr::get($payload, 'raw_text'),
            'records' => Arr::get($payload, 'records', []),
            'meta' => Arr::get($payload, 'meta', []),
        ];
    }
}