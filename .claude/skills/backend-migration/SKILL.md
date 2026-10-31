---
name: backend-migration
description: Write a safe PostgreSQL/PostGIS Laravel migration for DriveQ (school_id tables, CHECK constraints, triggers, indexes, backfills). Use when adding or altering tables/columns in backend/database/migrations.
---

# Add a migration (DriveQ)

Targets PostgreSQL 16 + PostGIS only (tests and prod). SQLite (the `.env.example` default) cannot run the existing migrations, so never rely on it.

1. **Create**: `php artisan make:migration create_x_table` in `backend/`. Files are dated `2026_MM_DD_00000N_description.php`; keep chronological order after the latest (`2026_10_03_000001_add_language_to_outreach_campaigns.php`). Use the anonymous-class form (`return new class extends Migration`).
2. **Header docblock** with the ticket (`DIQ-nnn`) and the why, like `2026_09_30_000007_create_lead_notes_table.php`.
3. **School-owned table checklist**:
   - `$table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();` (nullable only if truly global; the model then must not use `BelongsToSchool`).
   - Index `['school_id', 'created_at']` (plus any status/lookup index); FK users use `->nullable()->constrained('users')->nullOnDelete()`.
   - Model: add `use BelongsToSchool` (`app/Models/Traits`) and `$fillable` incl. `school_id`.
4. **Enumerations**: add a CHECK via `DB::statement("ALTER TABLE ... ADD CONSTRAINT ... CHECK (col IN (...))")` as in `2026_10_02_000001_add_listing_status_to_schools.php`; drop it in `down()`. Mirror the allowed values in a model constant (e.g. `School::STATUSES`, `Inquiry::STATUSES`).
5. **PostGIS / triggers**: raw `DB::statement`. `schools.location` is `geography(Point,4326)` filled by trigger `schools_sync_location` from `latitude/longitude` (GIST index `idx_schools_location`); never write it from the app. For new geo columns copy `2026_09_29_000002_add_postgis_location_to_schools.php` (extension, column, function, trigger, backfill, GIST index, full `down()`).
6. **audit_logs is append-only** (trigger from `2026_09_29_000003`); never add migrations that UPDATE/DELETE its rows (only FK nullOnDelete is permitted).
7. **Backfills** in the same migration, set-based SQL (see `..._backfill_owner_email_verification.php`, `..._unify_lead_statuses.php`). Make them idempotent and keep `up()`/`down()` symmetrical. Adding a NOT NULL column to a populated table: add nullable -> backfill -> `ALTER ... SET NOT NULL`, or give a default.
8. **Retention**: if the table holds personal data, register it in `config/retention.php` (`months`) and `RetentionCommand`, and mention it in `docs/DPDP-Compliance.md`.
9. **Plan/route side**: new gated feature -> `config/plans.php`; new seed data for demos -> `database/seeders/*` (seeders run with `WithoutModelEvents`).
10. **Verify** (needs Postgres): `php artisan migrate`, `php artisan migrate:rollback --step=1`, `php artisan migrate`, then `php artisan test`. CI runs `php artisan migrate --force` against `postgis/postgis:16-3.4` before PHPUnit, so a broken migration fails the whole suite.

## Security checklist for migrations
- **Tenant + index**: school-owned table has `school_id` FK (cascade) and `['school_id','created_at']`. Model uses `BelongsToSchool`. Models that cannot (global tables, token/claim lookups, notifications, audit, invoices) must be listed in `backend/CLAUDE.md` section 2 and every query on them filters `school_id` or `user_id` explicitly; prefer adding a `scopeForSchool($schoolId)` on such a model.
- **Constraints over trust**: CHECK constraints for enums/status; unique indexes for idempotency (webhook ids, payment references, one open invoice per school via a partial unique index); `lower(email)` unique index, never a case-sensitive email column.
- **Secrets and PII**: never store raw tokens, OTP codes or API keys; store `sha256`/bcrypt hashes with an expiry column and an attempts counter; provider tokens use the `encrypted` cast and `$hidden`. Phone and email columns that are only needed for dedupe get a hash column; display copies are masked. Do not put PII in `audit_logs` payloads.
- **Retention**: new PII table -> `config/retention.php` + `RetentionCommand` + `docs/DPDP-Compliance.md` in the same PR; files in storage need a delete path when the row or school is deleted.
- **audit_logs**: never UPDATE/DELETE/TRUNCATE; do not widen the append-only trigger. App DB role should not need DDL (migrations run as a separate role in production).
- **Backfills**: set-based, idempotent, no inline user input; destructive `down()` must not drop data that cannot be rebuilt.
- **Raw `DB::statement`**: constants only, no interpolated variables; triggers/functions get `CREATE OR REPLACE` and a symmetric `down()`.
