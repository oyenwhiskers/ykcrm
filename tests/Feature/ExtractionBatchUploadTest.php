<?php

namespace Tests\Feature;

use App\Jobs\ProcessExtractionImage;
use App\Services\Extraction\ExtractionServiceClient;
use App\Support\TemporaryExtractionBatchStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtractionBatchUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_batch_and_dispatches_one_job_per_image(): void
    {
        Queue::fake();
        Storage::fake('local');

        $response = $this->post(route('extraction-batches.store'), [
            'images' => [
                UploadedFile::fake()->image('lead-one.png'),
                UploadedFile::fake()->image('lead-two.png'),
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total_images', 2);

        $this->assertDatabaseCount('extraction_batches', 0);
        $this->assertDatabaseCount('extraction_images', 0);
        $this->assertDatabaseCount('extracted_leads', 0);

        Queue::assertPushed(ProcessExtractionImage::class, 2);

        $batchId = $response->json('data.id');
        $batch = app(TemporaryExtractionBatchStore::class)->find($batchId);

        $this->assertNotNull($batch);
        $this->assertCount(2, $batch['images']);

        foreach ($batch['images'] as $image) {
            Storage::disk($image['storage_disk'])->assertExists($image['path']);
        }
    }

    public function test_processing_an_image_does_not_store_extracted_leads_until_commit(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('extraction-batches/test-image.png', UploadedFile::fake()->image('test-image.png')->getContent());

        $batch = app(TemporaryExtractionBatchStore::class)->create([[
            'storage_disk' => 'local',
            'path' => 'extraction-batches/test-image.png',
            'original_name' => 'test-image.png',
            'mime_type' => 'image/png',
            'size' => Storage::disk('local')->size('extraction-batches/test-image.png'),
        ]], null);

        $image = $batch['images'][0];

        $client = $this->mock(ExtractionServiceClient::class, function ($mock) use ($batch, $image): void {
            $mock->shouldReceive('extract')
                ->once()
                ->with(
                    'local',
                    'extraction-batches/test-image.png',
                    'test-image.png',
                    [
                        'batch_id' => $batch['id'],
                        'extraction_image_id' => $image['id'],
                    ],
                )
                ->andReturn([
                    'engine' => 'fake-test-engine',
                    'raw_text' => 'Jane Doe 0123456789',
                    'records' => [
                        [
                            'name' => 'Jane Doe',
                            'phone_number' => '0123456789',
                            'normalized_phone_number' => '+60123456789',
                            'confidence' => 0.96,
                            'raw_text' => 'Jane Doe 0123456789',
                            'metadata' => ['source' => 'unit-test'],
                        ],
                    ],
                    'meta' => [],
                ]);
        });

        (new ProcessExtractionImage($batch['id'], $image['id']))->handle($client, app(TemporaryExtractionBatchStore::class));

        $updatedBatch = app(TemporaryExtractionBatchStore::class)->find($batch['id']);
        $updatedImage = collect($updatedBatch['images'])->firstWhere('id', $image['id']);

        $this->assertSame('completed', $updatedImage['status']);
        $this->assertSame(1, $updatedImage['extracted_records']);
        $this->assertSame('completed', $updatedBatch['status']);
        $this->assertDatabaseCount('extraction_batches', 0);
        $this->assertDatabaseCount('extracted_leads', 0);
        $this->assertSame('Jane Doe', data_get($updatedImage, 'raw_response.records.0.name'));
    }
}