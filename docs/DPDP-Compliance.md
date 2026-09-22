# DriveIQ — DPDP-oriented compliance notes

**Not legal advice.** Align with counsel and official MeitY notifications / DPDP Rules.

## Roles

| Role | DriveIQ |
|------|---------|
| Data Fiduciary | DriveIQ platform operator |
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

- **Email:** privacy@driveiq.in (publish/replace with real monitored mailbox before launch)
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

## Still open / counsel-led

- Server-side storage of consent artifacts (beyond localStorage)
- Automated data export package and hard erase job with legal holds
- Multilingual notices (Eighth Schedule languages)
- Processor contracts (DPAs)
- Verifiable parental consent if minors ever onboard

## Related code

- `frontend/src/lib/consent.ts`
- `frontend/src/components/legal/*`
- `backend` `data_subject_requests` table + API
