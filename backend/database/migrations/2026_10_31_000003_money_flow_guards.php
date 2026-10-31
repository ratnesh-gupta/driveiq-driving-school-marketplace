<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BSEC-20 / BSEC-21: money-flow guards enforced in PostgreSQL.
 *
 * - payments / invoices gain amount_paise (bigint, source of truth for new rows;
 *   the whole-rupee `amount` stays for API compatibility). Backfilled amount*100.
 * - platform_invoices: one open invoice per school, one use per payment
 *   reference, totals consistent.
 * - payments: (provider, provider_payment_id) unique.
 * - Triggers: invoices cannot be edited once issued (only their status moves
 *   issued -> paid | void), payments can only go pending -> paid | failed and are
 *   frozen afterwards, platform invoices cannot be deleted.
 * - Status CHECK constraints are added NOT VALID: enforced for new/changed rows
 *   without failing on legacy rows.
 *
 * Data risk on a populated DB: the unique indexes FAIL the migration (nothing is
 * changed) when duplicate data exists; the exception names the table and count so
 * an operator can resolve it. Existing rows are never rewritten except the
 * amount_paise backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->preflight();

        Schema::table('payments', fn (Blueprint $t) => $t->unsignedBigInteger('amount_paise')->default(0));
        Schema::table('invoices', fn (Blueprint $t) => $t->unsignedBigInteger('amount_paise')->default(0));
        DB::statement('UPDATE payments SET amount_paise = amount::bigint * 100 WHERE amount_paise = 0 AND amount > 0');
        DB::statement('UPDATE invoices SET amount_paise = amount::bigint * 100 WHERE amount_paise = 0 AND amount > 0');

        // ---- checks -------------------------------------------------------
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending','paid','failed','refunded')) NOT VALID");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount >= 0 AND amount_paise >= 0 AND amount_paise <= 10000000000) NOT VALID');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('issued','paid','void','cancelled')) NOT VALID");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amount_check CHECK (amount >= 0 AND amount_paise >= 0 AND amount_paise <= 10000000000) NOT VALID');
        DB::statement("ALTER TABLE platform_invoices ADD CONSTRAINT platform_invoices_status_check CHECK (status IN ('issued','paid','void')) NOT VALID");
        DB::statement('ALTER TABLE platform_invoices ADD CONSTRAINT platform_invoices_totals_check CHECK (subtotal >= 0 AND gst_amount >= 0 AND total = subtotal + gst_amount) NOT VALID');

        // ---- uniqueness (BSEC-20/21) ---------------------------------------
        DB::statement("CREATE UNIQUE INDEX platform_invoices_one_open_per_school ON platform_invoices (school_id) WHERE status = 'issued' AND school_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX platform_invoices_payment_reference_unique ON platform_invoices (lower(payment_reference)) WHERE payment_reference IS NOT NULL AND status = 'paid'");
        DB::statement('CREATE UNIQUE INDEX payments_provider_payment_unique ON payments (provider, provider_payment_id) WHERE provider_payment_id IS NOT NULL');

        // ---- platform_invoices: immutable once issued -----------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION platform_invoices_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'platform_invoices rows cannot be deleted (void them instead)';
                END IF;

                -- Content of an issued invoice never changes. School / plan / user links may only
                -- be cleared (ON DELETE SET NULL); the number may be set once, right after insert.
                IF NEW.id IS DISTINCT FROM OLD.id
                   OR (NEW.invoice_number IS DISTINCT FROM OLD.invoice_number AND OLD.invoice_number IS NOT NULL)
                   OR NEW.plan_code IS DISTINCT FROM OLD.plan_code
                   OR NEW.months IS DISTINCT FROM OLD.months
                   OR NEW.billed_to_name IS DISTINCT FROM OLD.billed_to_name
                   OR NEW.billed_to_gstin IS DISTINCT FROM OLD.billed_to_gstin
                   OR NEW.seller::text IS DISTINCT FROM OLD.seller::text
                   OR NEW.subtotal IS DISTINCT FROM OLD.subtotal
                   OR NEW.gst_rate IS DISTINCT FROM OLD.gst_rate
                   OR NEW.gst_amount IS DISTINCT FROM OLD.gst_amount
                   OR NEW.total IS DISTINCT FROM OLD.total
                   OR NEW.currency IS DISTINCT FROM OLD.currency
                   OR NEW.issued_at IS DISTINCT FROM OLD.issued_at
                   OR NEW.due_at IS DISTINCT FROM OLD.due_at
                   OR (NEW.school_id IS DISTINCT FROM OLD.school_id AND NEW.school_id IS NOT NULL)
                   OR (NEW.plan_id IS DISTINCT FROM OLD.plan_id AND NEW.plan_id IS NOT NULL)
                   OR (NEW.requested_by IS DISTINCT FROM OLD.requested_by AND NEW.requested_by IS NOT NULL)
                THEN
                    RAISE EXCEPTION 'platform_invoices: an issued invoice cannot be edited';
                END IF;

                IF NEW.status IS DISTINCT FROM OLD.status THEN
                    IF OLD.status <> 'issued' OR NEW.status NOT IN ('paid', 'void') THEN
                        RAISE EXCEPTION 'platform_invoices: invalid status change % -> %', OLD.status, NEW.status;
                    END IF;
                    IF NEW.status = 'paid' AND (NEW.payment_reference IS NULL OR NEW.paid_at IS NULL) THEN
                        RAISE EXCEPTION 'platform_invoices: a paid invoice needs a payment reference and date';
                    END IF;
                    IF NEW.status = 'void' AND NEW.voided_at IS NULL THEN
                        RAISE EXCEPTION 'platform_invoices: a void invoice needs voided_at';
                    END IF;
                ELSIF OLD.status <> 'issued' THEN
                    IF NEW.payment_reference IS DISTINCT FROM OLD.payment_reference
                       OR NEW.paid_at IS DISTINCT FROM OLD.paid_at
                       OR NEW.voided_at IS DISTINCT FROM OLD.voided_at
                       OR NEW.void_reason IS DISTINCT FROM OLD.void_reason THEN
                        RAISE EXCEPTION 'platform_invoices: a % invoice is final', OLD.status;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER platform_invoices_guard BEFORE UPDATE OR DELETE ON platform_invoices FOR EACH ROW EXECUTE FUNCTION platform_invoices_guard()');
        DB::statement('ALTER TABLE platform_invoices ENABLE ALWAYS TRIGGER platform_invoices_guard');

        // ---- invoices (learner): immutable once issued -----------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION invoices_guard() RETURNS trigger AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                   OR (NEW.invoice_number IS DISTINCT FROM OLD.invoice_number AND OLD.invoice_number NOT LIKE 'tmp-%')
                   OR NEW.purpose IS DISTINCT FROM OLD.purpose
                   OR NEW.amount IS DISTINCT FROM OLD.amount
                   OR NEW.amount_paise IS DISTINCT FROM OLD.amount_paise
                   OR NEW.currency IS DISTINCT FROM OLD.currency
                   OR NEW.issued_at IS DISTINCT FROM OLD.issued_at
                   OR NEW.line_items::text IS DISTINCT FROM OLD.line_items::text
                   OR (NEW.school_id IS DISTINCT FROM OLD.school_id AND NEW.school_id IS NOT NULL)
                   OR (NEW.learner_id IS DISTINCT FROM OLD.learner_id AND NEW.learner_id IS NOT NULL)
                   OR (NEW.package_id IS DISTINCT FROM OLD.package_id AND NEW.package_id IS NOT NULL)
                   OR (NEW.plan_id IS DISTINCT FROM OLD.plan_id AND NEW.plan_id IS NOT NULL)
                THEN
                    RAISE EXCEPTION 'invoices: an issued invoice cannot be edited';
                END IF;

                IF NEW.status IS DISTINCT FROM OLD.status
                   AND (OLD.status <> 'issued' OR NEW.status NOT IN ('paid', 'void', 'cancelled')) THEN
                    RAISE EXCEPTION 'invoices: invalid status change % -> %', OLD.status, NEW.status;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER invoices_guard BEFORE UPDATE ON invoices FOR EACH ROW EXECUTE FUNCTION invoices_guard()');
        DB::statement('ALTER TABLE invoices ENABLE ALWAYS TRIGGER invoices_guard');

        // ---- payments: pending -> paid | failed, then frozen -----------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION payments_guard() RETURNS trigger AS $$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                   OR NEW.amount IS DISTINCT FROM OLD.amount
                   OR NEW.amount_paise IS DISTINCT FROM OLD.amount_paise
                   OR NEW.currency IS DISTINCT FROM OLD.currency
                   OR NEW.purpose IS DISTINCT FROM OLD.purpose
                   OR NEW.receipt IS DISTINCT FROM OLD.receipt
                   OR (NEW.school_id IS DISTINCT FROM OLD.school_id AND NEW.school_id IS NOT NULL)
                   OR (NEW.learner_id IS DISTINCT FROM OLD.learner_id AND NEW.learner_id IS NOT NULL)
                   OR (NEW.invoice_id IS DISTINCT FROM OLD.invoice_id AND NEW.invoice_id IS NOT NULL)
                   OR (NEW.package_id IS DISTINCT FROM OLD.package_id AND NEW.package_id IS NOT NULL)
                THEN
                    RAISE EXCEPTION 'payments: amount, currency and links of a payment cannot be edited';
                END IF;

                IF NEW.status IS DISTINCT FROM OLD.status THEN
                    IF OLD.status <> 'pending' OR NEW.status NOT IN ('paid', 'failed') THEN
                        RAISE EXCEPTION 'payments: invalid status change % -> %', OLD.status, NEW.status;
                    END IF;
                ELSIF OLD.status <> 'pending' THEN
                    IF NEW.paid_at IS DISTINCT FROM OLD.paid_at
                       OR NEW.provider_payment_id IS DISTINCT FROM OLD.provider_payment_id
                       OR NEW.provider_order_id IS DISTINCT FROM OLD.provider_order_id
                       OR NEW.method IS DISTINCT FROM OLD.method THEN
                        RAISE EXCEPTION 'payments: a % payment is final', OLD.status;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER payments_guard BEFORE UPDATE ON payments FOR EACH ROW EXECUTE FUNCTION payments_guard()');
        DB::statement('ALTER TABLE payments ENABLE ALWAYS TRIGGER payments_guard');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS payments_guard ON payments');
        DB::statement('DROP FUNCTION IF EXISTS payments_guard()');
        DB::statement('DROP TRIGGER IF EXISTS invoices_guard ON invoices');
        DB::statement('DROP FUNCTION IF EXISTS invoices_guard()');
        DB::statement('DROP TRIGGER IF EXISTS platform_invoices_guard ON platform_invoices');
        DB::statement('DROP FUNCTION IF EXISTS platform_invoices_guard()');

        DB::statement('DROP INDEX IF EXISTS payments_provider_payment_unique');
        DB::statement('DROP INDEX IF EXISTS platform_invoices_payment_reference_unique');
        DB::statement('DROP INDEX IF EXISTS platform_invoices_one_open_per_school');

        DB::statement('ALTER TABLE platform_invoices DROP CONSTRAINT IF EXISTS platform_invoices_totals_check');
        DB::statement('ALTER TABLE platform_invoices DROP CONSTRAINT IF EXISTS platform_invoices_status_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_amount_check');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_amount_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');

        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('amount_paise'));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('amount_paise'));
    }

    /** Refuse to half-apply on data that would break the unique indexes. */
    private function preflight(): void
    {
        $checks = [
            'platform_invoices: more than one open (issued) invoice for a school' => "SELECT count(*) FROM (SELECT school_id FROM platform_invoices WHERE status = 'issued' AND school_id IS NOT NULL GROUP BY school_id HAVING count(*) > 1) d",
            'platform_invoices: a payment reference used on more than one paid invoice' => "SELECT count(*) FROM (SELECT lower(payment_reference) FROM platform_invoices WHERE payment_reference IS NOT NULL AND status = 'paid' GROUP BY 1 HAVING count(*) > 1) d",
            'payments: a provider payment id used more than once' => 'SELECT count(*) FROM (SELECT provider, provider_payment_id FROM payments WHERE provider_payment_id IS NOT NULL GROUP BY 1, 2 HAVING count(*) > 1) d',
        ];

        $problems = [];
        foreach ($checks as $label => $sql) {
            $n = (int) DB::scalar($sql);
            if ($n > 0) {
                $problems[] = "{$label} ({$n} groups)";
            }
        }
        if ($problems) {
            throw new RuntimeException("Money-flow migration refused, resolve duplicates first:\n - ".implode("\n - ", $problems));
        }
    }
};
