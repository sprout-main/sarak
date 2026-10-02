<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->unsignedInteger('karma_score')->default(0)->after('verified_tier');
            $table->boolean('is_claimed')->default(false)->after('karma_score');
            $table->foreignId('claimed_by')->nullable()->after('is_claimed')->constrained('users')->nullOnDelete();
            $table->index(['status', 'lat', 'lng'], 'places_status_lat_lng_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('karma_points')->default(0)->after('role');
            $table->string('reputation_badge')->default('Apprentice Scout')->after('karma_points');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex('places_status_lat_lng_index');
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropColumn(['karma_score', 'is_claimed']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['karma_points', 'reputation_badge']);
        });
    }
};
