<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_photos', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_cover');
            $table->index(['place_id', 'sort_order']);
        });

        // Initialize existing photos sort_order to match their id sequence per place
        DB::statement('UPDATE place_photos SET sort_order = id WHERE sort_order = 0');
    }

    public function down(): void
    {
        Schema::table('place_photos', function (Blueprint $table) {
            $table->dropIndex(['place_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
