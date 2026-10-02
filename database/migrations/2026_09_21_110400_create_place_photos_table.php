<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id');
            $table->string('path');
            $table->unsignedBigInteger('uploader_id')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->foreign('place_id')->references('id')->on('places')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_photos');
    }
};