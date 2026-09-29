<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Near-me search uses PostGIS (ST_DWithin / ST_Distance on geography).
 *
 * schools.location is derived from latitude/longitude by a trigger, so every
 * write path (Eloquent, seeders, raw DB::table) keeps it in sync and the
 * application never writes it directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        DB::statement('ALTER TABLE schools ADD COLUMN location geography(Point, 4326)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION schools_sync_location() RETURNS trigger AS $$
            BEGIN
                IF NEW.latitude IS NULL OR NEW.longitude IS NULL THEN
                    NEW.location := NULL;
                ELSE
                    NEW.location := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER schools_sync_location
            BEFORE INSERT OR UPDATE OF latitude, longitude ON schools
            FOR EACH ROW EXECUTE FUNCTION schools_sync_location()
        SQL);

        // Backfill existing rows.
        DB::statement(<<<'SQL'
            UPDATE schools
            SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        SQL);

        DB::statement('CREATE INDEX idx_schools_location ON schools USING GIST (location)');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS schools_sync_location ON schools');
        DB::statement('DROP FUNCTION IF EXISTS schools_sync_location()');
        DB::statement('DROP INDEX IF EXISTS idx_schools_location');
        DB::statement('ALTER TABLE schools DROP COLUMN IF EXISTS location');
    }
};
