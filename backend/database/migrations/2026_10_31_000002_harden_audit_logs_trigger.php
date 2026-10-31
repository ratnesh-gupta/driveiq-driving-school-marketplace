<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BSEC-17: tighten the append-only audit trail.
 *
 *  - Clearing user_id / school_id is only accepted while the referenced row is
 *    really gone (what ON DELETE SET NULL does). Before, any UPDATE that
 *    NULLed them passed, so attribution could be erased by hand.
 *  - TRUNCATE is rejected (it bypasses row triggers).
 *  - Both triggers are ENABLE ALWAYS, so they also fire when a privileged
 *    session sets session_replication_role = replica (the usual way restore /
 *    replication tools skip triggers). A superuser can still DISABLE or DROP the
 *    trigger: that needs a separate migration owner and a restricted app role,
 *    see docs/security/AUDIT-LOG-HARDENING.md.
 *
 * Data risk: none (no rows are touched). Deploying role must own the table.
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

                IF (NEW.user_id IS NOT DISTINCT FROM OLD.user_id
                        OR (NEW.user_id IS NULL AND NOT EXISTS (SELECT 1 FROM users WHERE id = OLD.user_id)))
                   AND (NEW.school_id IS NOT DISTINCT FROM OLD.school_id
                        OR (NEW.school_id IS NULL AND NOT EXISTS (SELECT 1 FROM schools WHERE id = OLD.school_id)))
                   AND NEW.id = OLD.id
                   AND NEW.model_type = OLD.model_type
                   AND NEW.model_id IS NOT DISTINCT FROM OLD.model_id
                   AND NEW.action = OLD.action
                   AND NEW.old_values::text IS NOT DISTINCT FROM OLD.old_values::text
                   AND NEW.new_values::text IS NOT DISTINCT FROM OLD.new_values::text
                   AND NEW.ip_address IS NOT DISTINCT FROM OLD.ip_address
                   AND NEW.created_at IS NOT DISTINCT FROM OLD.created_at
                   AND NEW.updated_at IS NOT DISTINCT FROM OLD.updated_at
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'audit_logs is append-only: UPDATE is not allowed';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_no_truncate() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only: TRUNCATE is not allowed';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_logs_no_truncate
            BEFORE TRUNCATE ON audit_logs
            FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_no_truncate()
        SQL);

        DB::statement('ALTER TABLE audit_logs ENABLE ALWAYS TRIGGER audit_logs_append_only');
        DB::statement('ALTER TABLE audit_logs ENABLE ALWAYS TRIGGER audit_logs_no_truncate');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_no_truncate()');
        DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_append_only');

        // Back to the original (looser) function from 2026_09_29_000003.
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
    }
};
