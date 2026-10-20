<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * audit_logs is append-only (PROJECT-PLAN §0.4 "Immutable audit log").
 *
 * DELETE is always rejected. UPDATE is rejected unless it only clears
 * user_id and/or school_id, which is what the nullOnDelete foreign keys do
 * when a user or school is deleted; everything that was recorded stays intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'audit_logs is append-only: DELETE is not allowed';
                END IF;

                IF (NEW.user_id IS NOT DISTINCT FROM OLD.user_id OR NEW.user_id IS NULL)
                   AND (NEW.school_id IS NOT DISTINCT FROM OLD.school_id OR NEW.school_id IS NULL)
                   AND NEW.id = OLD.id
                   AND NEW.model_type = OLD.model_type
                   AND NEW.model_id IS NOT DISTINCT FROM OLD.model_id
                   AND NEW.action = OLD.action
                   AND NEW.old_values::text IS NOT DISTINCT FROM OLD.old_values::text
                   AND NEW.new_values::text IS NOT DISTINCT FROM OLD.new_values::text
                   AND NEW.ip_address IS NOT DISTINCT FROM OLD.ip_address
                   AND NEW.created_at IS NOT DISTINCT FROM OLD.created_at
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'audit_logs is append-only: UPDATE is not allowed';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_logs_append_only
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_append_only()');
    }
};
