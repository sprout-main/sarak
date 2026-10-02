<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boundary_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['barangay', 'municipality'])->default('barangay');
            $table->geometry('geom', 'polygon')->nullable();
            $table->string('boundary_source')->nullable();
            $table->string('psgc')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boundary_areas');
    }
};