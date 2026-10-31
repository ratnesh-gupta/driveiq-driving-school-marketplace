# DriveQ — DPDP-oriented compliance notes

**Not legal advice.** Align with counsel and official MeitY notifications / DPDP Rules.

## Roles

| Role | DriveQ |
|------|---------|
| Data Fiduciary | DriveQ platform operator |
| Data Principal | Learners, school users, instructors, admins, public users who submit data |
| Data Processors | Cloud host, email, Redis/Postgres provider, payment gateway (when live) |

## Product surfaces (consent & notice)

| Surface | What |
|---------|------|
| Cookie / storage banner | Essential localStorage vs Accept (future optional analytics) |
| Register | Separate Terms, Privacy, processing-purpose checkboxes + itemised notice |
| Login | Link to Privacy / Terms; role gate may still show |
| Role portals (school, instructor, learner, admin) | First-entry purpose notice + affirmative consent |
| Search “near me” | Purpose dialog before browser geolocation |
| `/privacy`, `/terms` | Policies |
| `/privacy/data-request` | Access, correction, deletion, nomination |
| Admin `/admin/data-requests` | Staff fulfilment workflow |

## Grievance

- **Email:** privacy@driveq.in (publish/replace with real monitored mailbox before launch)
- **Form:** `/privacy/data-request` (type: other / grievance)
- **Target:** acknowledge and resolve within **90 days** where Rules require

## Indicative retention (document & enforce operationally)

| Category | Indicative period | Notes |
|----------|-------------------|--------|
| Account credentials | Life of account + 30 days after deletion request (or legal hold) | Hashed passwords |
| Inquiries / leads | 24 months from last activity | School ops |
| Training records (schedules, attendance, progress) | 36 months after course end or legal hold | Disputes / RTO context |
| Messages | 24 months | Unless legal hold |
| Payment / invoice records | 8 years or as tax law requires | Prefer longer for accounts |
| Audit logs | 12–24 months | Security |
| Data-subject request tickets | 36 months from closure | Accountability |
| Optional GPS for search | Not stored as history | Request-time only |

Erase when purpose is served or consent withdrawn, unless law requires retention. Prefer 48-hour advance notice before irreversible erasure where Rules apply.

## Breach (ops runbook outline)

1. Contain (rotate secrets, isolate systems).
2. Assess personal data involved and principals affected.
3. Notify **Data Protection Board** and affected principals as Rules require (often discussed as without delay + detail within **72 hours**).
4. Log incident; post-mortem; improve controls.

## Implemented (2026-09, M4)

- **Consent records server-side** (`consents` table, `POST/GET /api/consents`, `DELETE /api/consents/{purpose}`): register (terms, privacy, processing), role portal notice, and the cookie opt-in (anonymous, random device id). Grants are new rows; withdrawal stamps `withdrawn_at`. localStorage is only a cache. Admins see the consent history on `/admin/data-requests`.
- **Retention job** `php artisan driveiq:retention` (daily; report-only unless `RETENTION_EXECUTE=true` or `--execute`): inquiries 24 months after last activity, messages 24 months, closed contact messages 24 months, closed data-subject requests 36 months. `RETENTION_LEGAL_HOLD_SCHOOLS` excludes schools under legal hold. Audit logs, payments and training records are never deleted by it.
- **Private documents** (ID, licence, medical) stored off the public web root, served only through 5-minute signed links after an access check.
- **Account deactivation** by admins revokes all tokens and blocks login.

## Implemented (2026-10, WP-8, BSEC-13/14/15)

- **Verified data-subject requests**: `POST /api/data-requests` e-mails a one-time link (48 h, only its SHA-256 is stored) to the address; `POST /api/data-requests/verify {token}` marks it verified. Admins cannot move a request to `in_progress`/`completed` or erase until `verifiedAt` is set (409 `verification_required`); rejecting is always allowed. Each request has `dueAt` (30 days). Every admin status change and verification writes an `AuditLog` entry (e-mails are logged as hashes). Max 3 requests per address per day.
- **Erasure = anonymisation** (`App\Services\ErasureService`; admin `POST /api/admin/data-requests/{id}/erase` for verified deletion requests, or `php artisan driveiq:erase-user {email} [--execute]`, dry run by default, idempotent). Erased: account identity, learner/trainer PII, ID documents (rows and files), enquiries, notifications, sent message bodies, consent IP/user agent, review author name. Kept: payments and invoices (tax, 8 years, now tied to an anonymised learner), consent rows, training records without identity, `audit_logs`, the request itself. Refused: platform admins, school owners, and anyone linked to a school in `RETENTION_LEGAL_HOLD_SCHOOLS`.
- **Retention gaps closed** (`config/retention.php`, months, env-overridable, `0` disables, dry run by default, the command prints counts per table): notifications 6, training records of non-active learners 36, inactive learner accounts 36 (anonymised, never hard deleted), payments and learner invoices 96, consent IP/user agent 12, tokens of deactivated users. `audit_logs` stay (legal obligation / legitimate security interest) and must not carry contact details; `platform_invoices` stay until accounting review.
- **Encryption at rest for private uploads** (`DOCUMENTS_ENCRYPT`, default true): new files are encrypted with `APP_KEY` before reaching the disk and end in `.enc`; downloads are decrypted by the app behind the same signed link. Old plain files keep working; `php artisan driveiq:encrypt-documents [--execute]` converts them (dry run first, idempotent). Keep old keys in `APP_PREVIOUS_KEYS` when rotating `APP_KEY`. A private, server-side-encrypted bucket is still required for production.

## Still open / counsel-led

- A self-service "withdraw consent" screen (the API exists; today withdrawal is by data request)
- Automated data export package (access requests are handled by hand), 48-hour notice before erasure where Rules apply
- Multilingual notices (Eighth Schedule languages)
- Processor contracts (DPAs)
- Verifiable parental consent if minors ever onboard

## Related code

- `frontend/src/lib/consent.ts`
- `frontend/src/components/legal/*`
- `backend` `data_subject_requests` and `consents` tables + APIs
- `backend/app/Console/Commands/RetentionCommand.php`, `backend/config/retention.php`
