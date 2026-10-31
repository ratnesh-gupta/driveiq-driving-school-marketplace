# audit_logs and money tables: what the database enforces (BSEC-17, 20, 21)

Enforced by migrations `2026_10_31_000002` and `2026_10_31_000003` (PostgreSQL triggers, `ENABLE ALWAYS`, so they also fire under `session_replication_role = replica`):

- `audit_logs`: no DELETE, no TRUNCATE, no UPDATE except clearing `user_id` / `school_id` while that user/school no longer exists (the foreign-key `SET NULL`).
- `platform_invoices`: content frozen after issue; status only `issued -> paid | void`; paid/void final; never deleted. One open invoice per school; a payment reference settles one invoice.
- `invoices` (learner): content frozen after issue; `issued -> paid | void | cancelled`.
- `payments`: amount/currency/links frozen; `pending -> paid | failed`, then final; `(provider, provider_payment_id)` unique.

What a trigger cannot stop: the table owner or a superuser can `ALTER TABLE ... DISABLE TRIGGER` or drop it. Close that at deploy time (infra task):

1. Run migrations as a separate owner role (`driveiq_owner`); the app connects as `driveiq_app`.
2. `GRANT SELECT, INSERT ON audit_logs TO driveiq_app;` plus `UPDATE (user_id, school_id)` is not needed (the FK action runs as the owner of the referenced table). Grant the app role no `TRUNCATE`, `TRIGGER` or `ALTER`.
3. Grant `SELECT, INSERT, UPDATE` (no `TRUNCATE`, no `DELETE` on `platform_invoices`) on the money tables; `DELETE` stays on `payments`/`invoices` only for the retention job.
4. Ship the audit table to backups with PITR; a hash chain over rows is not implemented (not specified in the audit); consider it if an external auditor requires tamper evidence beyond role separation.
