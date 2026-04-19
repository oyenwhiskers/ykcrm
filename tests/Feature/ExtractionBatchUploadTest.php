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

    private ?string $originalQueueWorkerProcesses = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalQueueWorkerProcesses = env('QUEUE_WORKER_PROCESSES');
    }

    protected function tearDown(): void
    {
        $this->setQueueWorkerProcesses($this->originalQueueWorkerProcesses);

        parent::tearDown();
    }

    public function test_it_creates_a_batch_and_dispatches_only_available_slots(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['services.extraction.workers' => 3]);
        $this->setQueueWorkerProcesses('1');

        $response = $this->post(route('extraction-batches.store'), [
            'images' => [
                UploadedFile::fake()->image('lead-one.png'),
                UploadedFile::fake()->image('lead-two.png'),
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total_images', 2);

        $this->assertDatabaseCount('extraction_batches', 1);
        $this->assertDatabaseCount('extraction_images', 2);
        $this->assertDatabaseCount('extracted_leads', 0);

        Queue::assertPushed(ProcessExtractionImage::class, 1);

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
        Queue::fake();
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
        $this->assertDatabaseCount('extraction_batches', 1);
        $this->assertDatabaseCount('extraction_images', 1);
        $this->assertDatabaseCount('extracted_leads', 0);
        $this->assertSame('Jane Doe', data_get($updatedImage, 'raw_response.records.0.name'));
    }

    public function test_commit_leads_saves_records_without_duplicating_batches_or_images(): void
    {
        Queue::fake();
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

        (new ProcessExtractionImage($batch['id'], $image['id']))->handle(
            $client,
            app(TemporaryExtractionBatchStore::class),
            app(\App\Services\Extraction\ExtractionBatchScheduler::class),
        );

        $response = $this->post(route('extraction-batches.commit-leads', $batch['id']));

        $response->assertOk();
        $response->assertJsonPath('created_count', 1);
        $response->assertJsonPath('data.persisted_batch_id', (int) $batch['id']);

        $this->assertDatabaseCount('extraction_batches', 1);
        $this->assertDatabaseCount('extraction_images', 1);
        $this->assertDatabaseCount('extracted_leads', 1);
        $this->assertDatabaseHas('extracted_leads', [
            'extraction_image_id' => (int) $image['id'],
            'name' => 'Jane Doe',
            'normalized_phone_number' => '+60123456789',
        ]);
    }

    private function setQueueWorkerProcesses(?string $value): void
    {
        if ($value === null || $value === '') {
            putenv('QUEUE_WORKER_PROCESSES');
            unset($_ENV['QUEUE_WORKER_PROCESSES'], $_SERVER['QUEUE_WORKER_PROCESSES']);

            return;
        }

        putenv('QUEUE_WORKER_PROCESSES='.$value);
        $_ENV['QUEUE_WORKER_PROCESSES'] = $value;
        $_SERVER['QUEUE_WORKER_PROCESSES'] = $value;
    }
}