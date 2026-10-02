<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('barangay_id')->nullable();
            $table->geometry('geom', 'point');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->enum('source', ['manual', 'overpass', 'nominatim', 'official_list', 'google_ref', 'community'])->default('manual');
            $table->string('source_ref')->nullable();
            $table->string('source_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'archived'])->default('pending');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('verified_tier', ['verified', 'community', 'imported'])->default('community');
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'status']);
            $table->index('source_ref');
            $table->spatialIndex('geom');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('barangay_id')->references('id')->on('boundary_areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};