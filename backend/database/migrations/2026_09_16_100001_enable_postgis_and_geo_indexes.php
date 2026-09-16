<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enable PostGIS when the extension is available (postgis/postgis image).
        // SQLite / plain postgres installs skip this safely.
        try {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            }
        } catch (\Throwable) {
            // Extension not available — Haversine path remains the default.
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->index(['latitude', 'longitude'], 'idx_schools_lat_lng');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropIndex('idx_schools_lat_lng');
        });
    }
};
