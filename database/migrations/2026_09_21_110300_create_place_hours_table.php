<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id');
            $table->tinyInteger('day');
            $table->time('opens')->nullable();
            $table->time('closes')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('place_id');
            $table->foreign('place_id')->references('id')->on('places')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_hours');
    }
};