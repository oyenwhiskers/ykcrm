<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracted_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extraction_image_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('normalized_phone_number')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('raw_text')->nullable();
            $table->string('review_status')->default('pending_review');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracted_leads');
    }
};