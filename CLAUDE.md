# DriveQ — Driving School Marketplace Platform

**Status:** M1–M10 shipped (89 `DIQ-` tickets); next is **M11 pilot launch readiness** (infrastructure)
**Market:** Pune, India (pilot)
**Last verified against code:** 2026-10-31
**Status of record:** `docs/PROJECT-PLAN.md` section 2 (built vs open). Remaining-work plan: `docs/ROADMAP.md`.
**Deep dives:** `backend/CLAUDE.md` (Laravel), `frontend/CLAUDE.md` (React), `docs/SYSTEM-FLOW.md` (end-to-end flows + docs-vs-reality table).

> Naming: the user-facing product name is **DriveQ**. Code identifiers, `driveiq_*` storage keys, `driveiq:*` artisan commands, the `driveiq-` container names and demo logins keep the old name. Do not rename them. Many docs and filenames still say "DriveIQ".

---

## 1. What DriveQ Is

A hyperlocal marketplace connecting driving learners with verified driving schools and independent trainers, plus an operations suite (leads, learners, trainers, schedules, fleet, payments) sold to schools on tiered plans.

- **Learners:** geo search (2/5/10 km), compare schools, enquire (form / WhatsApp / callback), review after eligibility.
- **Schools / trainers:** claim or create a listing, receive and work leads, run training operations, buy a plan.
- **Platform admin:** verify and moderate, run acquisition (prospects, outreach), sponsored slots, billing console, DPDP requests.

Revenue: Basic (free) / Featured / Premium / Enterprise plans (manual UPI/bank invoices today), sponsored slots, later pay-per-lead.

---

## 2. Tech Stack (verified)

| Layer | Reality |
|-------|---------|
| Frontend | React 19, Vite, Tailwind 4, shadcn/Radix, Wouter, TanStack Query, Zustand, RHF + Zod, Framer Motion, Recharts, Sonner. **pnpm** (CI uses `pnpm`). Generated API client in `src/api-client/generated` plus hand-written `src/lib/*-api.ts`. |
| Backend | Laravel 13, PHP 8.3, Sanctum (expiring tokens), PHPUnit. REST under `/api`. There is no Express code. |
| Data | PostgreSQL 16 + PostGIS (`schools.location geography`, trigger-synced), ~64 migrations |
| Queue / cache | Redis 7 (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`); `worker` and `scheduler` services in compose |
| Realtime | Laravel Reverb optional (`BROADCAST_CONNECTION=log` by default; channels `user.{id}`, `school.{id}`); frontend `lib/realtime.ts` |
| Messaging | `App\Messaging` provider-neutral driver layer (`log`, `null`); no real WhatsApp/SMS provider yet |
| Infra | Docker Compose (local + prod file), `Makefile`, GitHub Actions CI (Pint + PHPUnit on PostGIS 16, frontend typecheck + build). Planned: DigitalOcean, Cloudflare, Spaces, Uptime Kuma |

---

## 3. Actors and Roles

`users.role` has **four** values: `admin`, `school`, `instructor`, `learner`. "Owner vs manager" is not a role value: it is a `school_admins` row checked by `SchoolAccess` (owner-only actions: team, billing, settings). Managers are invite-only. Docs that describe "5 roles" (PBAC, PROJECT-PLAN) map to this as above. Trainer listings (`schools.type = trainer`) are owned by a `school` user.

---

## 4. Verified System Flow (summary; diagrams in `docs/SYSTEM-FLOW.md`)

1. **Discovery.** Visitor opens home/search/locality. `GET /api/schools` runs PostGIS `ST_DWithin` plus SQL ranking (`config/geo.php`: distance 0.40, rating 0.25, reviews 0.12, verified 0.08, premium 0.15; sponsored slots first and labelled). Only `School::public()` (listing_status = published) is visible. Compare, locality pages, JSON-LD.
2. **Enquiry.** `POST /api/inquiries` (throttled, honeypot + min fill time, `channel` stored; consent posted separately to `/api/consents`) creates an `Inquiry` and fires `InquiryCreated`. Queued listeners notify owner + managers (in-app, email, WhatsApp per school settings) and send the learner a confirmation with a one-time review link.
3. **Lead work.** School moves the lead New, Contacted, Follow-up, Interested, Converted or Lost; notes, follow-ups, timeline, response-time stats (`LeadResponseStats`) feed the "usually replies within" badge. `driveiq:lead-reminders` nags on unanswered leads.
4. **Conversion to operations.** `POST /api/inquiries/{id}/convert` creates a `Learner` (then package, trainer, vehicle assignment) which unlocks schedules, attendance, progress, driving tests, payments, documents (private disk, 5-minute signed links), messages. These modules are plan-gated by `plan.features` middleware (`config/plans.php`): locked modules are read-only, writes return 402 `plan_required`.
5. **Reviews.** Eligible only via enrolled learner or the one-time inquiry link (`ReviewEligibilityService`); 3 abuse reports auto-hide; admin moderates; ratings recomputed.
6. **Supply onboarding.** Register as school/trainer (owner email verification gates publishing) or admin imports `Prospect`s, pre-builds unclaimed listings, runs outreach campaigns (`driveiq:outreach`, separate `outreach` mailer, suppression list). Owner claims via `/claim/:token` plus one-time code (email, SMS, or Google Business). Listing goes unclaimed, draft, published (or suspended). Google Ads lead webhook and Google Business import feed the same funnel.
7. **Monetization.** Every school gets a 30-day Premium-feature trial. Admin records manual payment, `BillingService`/`SubscriptionService` issue GST invoices; `driveiq:subscriptions` handles reminders and expiry. Featured placements and campaign windows (search/homepage/locality) are admin-controlled and capped.
8. **Compliance.** `Consent` records (server-side), data-subject requests, contact inbox, `driveiq:retention` (dry run unless `RETENTION_EXECUTE=true`), append-only `audit_logs` (model guard + PostgreSQL trigger).
9. **Messaging.** `MessagingService` renders templates to `outbound_messages`, sends through the configured driver; opt-in stored as consent; admin sees a masked log.

### Scheduled commands (`backend/routes/console.php`)
`sanctum:prune-expired` daily; `driveiq:retention` 02:30; `driveiq:subscriptions` 03:15; `driveiq:lead-reminders`, `driveiq:outreach` every 15 min; `driveiq:ops-reminders` and response-badge refresh hourly. Non-scheduled: `driveiq:create-admin`, `driveiq:demo` (refuses production).

---

## 5. Hard Rules (apply to every change)

1. **School isolation.** Operational tables carry `school_id`. Models use `App\Models\Traits\BelongsToSchool` (global scope: admin sees all, school-less users see nothing, unauthenticated requests are not filtered so public endpoints must filter explicitly). All authorization goes through `App\Support\SchoolAccess`; foreign IDs in payloads are verified with `SchoolScopedIds`. Force `school_id` from the authenticated user, never from input.
2. **Public listings** always use `School::public()`. Never expose unclaimed, draft or suspended listings.
3. **Tests with two schools.** Every new endpoint needs a cross-school denial test.
4. **Plan gating.** New school-facing modules are registered in `config/plans.php`, not ad hoc.
5. **Consent and PII.** Marketing or WhatsApp sends require recorded consent; never log raw PII in outbound logs (masked).
6. **Audit.** Admin actions and school profile changes write `AuditLog` (never update or delete rows).
7. **Migrations** are PostgreSQL/PostGIS-specific; do not edit shipped migrations, add new ones. Index operational tables on `(school_id, created_at)`.
8. **Lint/CI.** Backend: `vendor/bin/pint --test` and `php artisan test`. Frontend: `pnpm typecheck && pnpm build`.
9. **Ticket convention.** Commits and PRs are `DIQ-NNN: summary` within milestone `Mnn`. Branches `feature/...`, `bugfix/...`.

---

## 6. Commands

```bash
make up | down | logs | migrate | seed | fresh      # Docker stack (see `make help`)
make test | test-filter FILTER=Name                 # PHPUnit in container
make frontend-check                                 # pnpm typecheck + build
cd backend  && composer install && php artisan serve && php artisan queue:listen
cd frontend && pnpm install && pnpm dev && pnpm typecheck
php artisan driveiq:create-admin you@example.com    # only way to create an admin
php artisan driveiq:demo                            # showcase data (never production)
```

`make seed` demo accounts use `password123`. Never seed production.

---

## 7. Demo / Non-Production Behaviors (read before launch)

- Mail defaults to `log`; password reset, invites, review links and lead alerts need real `MAIL_*` and a running queue worker.
- `MESSAGING_DRIVER=log` sends nothing. A provider driver and DLT/WhatsApp template ids are required.
- Outreach goes to the log until `OUTREACH_MAILER=outreach` and `OUTREACH_MAIL_*` point to a mailbox on its own subdomain (SPF/DKIM/DMARC).
- Plan payments are manual; Razorpay not integrated (`BILLING_*` unset). `PLANS_ENFORCE` is the kill switch.
- Google Ads webhook and Google Business need `GOOGLE_ADS_WEBHOOK_KEY` / `GOOGLE_OAUTH_*`.
- Reverb realtime is off by default.
- Some API client types are hand-written; regenerate from OpenAPI when a spec exists.

---

## 8. Known Gaps (from PROJECT-PLAN "Still Open")

M11 launch readiness (reverse proxy/TLS, trusted proxies, security headers, Spaces, backups, deep health check, deploy job, production seed); homepage "near me" entry point; real WhatsApp/SMS provider with inbound STOP and receipts; OTP/mobile login; Razorpay and GST IRN; review moderation bulk actions; lead auto-assignment; trainer working hours/capacity; public school photo upload; OpenAPI regeneration; SEO admin tooling and programmatic locality templates; Google Business review sync; portal pages are English-only (public pages are en/hi/mr).

---

## 9. Docs Index (staleness noted; full detail in `docs/SYSTEM-FLOW.md` section 6)

| Doc | Purpose | Trust |
|-----|---------|-------|
| `docs/PROJECT-PLAN.md` | Master plan, phases 0-10, section 2 = status of record | Current for section 2 (2026-10-02); header still says v1.0 June, phase checklists partly unticked |
| `docs/ROADMAP.md` | v2.0 shipped-ticket ledger and M11-M15 plan | Current (2026-10-20); untracked in git |
| `docs/DEPLOY.md` | Production checklist | Mostly current; says Laravel 11 (code is 13) |
| `docs/DPDP-Compliance.md` | Consent, DSR, retention design | Current |
| `docs/I18N.md` | en/hi/mr coverage | Current |
| `docs/PHASE-1-GEO-RANKING.md` | Geo query/ranking contract | Current |
| `docs/ARCHITECTURE.md` | Shared-DB + school_id pattern, ERD | Concept valid; 2026-06 snapshot, table list is aspirational |
| `docs/BACKEND-ASSESSMENT.md` | June gap analysis (authz gaps) | Historical: gaps are closed |
| `docs/PHASE-A-IMPLEMENTATION.md` | Phase 0 step-by-step code | Historical: shipped, do not copy snippets |
| `docs/requirements/PBAC.md`, `PRD-DSOS.md`, `Core-Business-ERd.md` | Target RBAC matrix, 10 domains, ERD | Vision; "5 roles" and table names differ from code |
| `docs/Features/Geo-Aware.md` | Driving rules / RTO feature spec | Partly built (static India/MH/Pune data) |
| `docs/Features/School-Comparison-Engine.md` | Compare spec | Built (compare API and page) |
| `docs/Features/PRD-Google-Business.md` | Growth-suite spec | Partly built (import/claim only, no review sync) |
| `docs/END-USER-SEARCH-ENQUIRY-PERSONA.md`, `SCHOOL-PERSONAS-UI.md`, `LEARNER-SCENARIOS*.md` | Personas and flows | Draft/June 2026; use for intent, not status |
| `docs/DriveIQ-Documentation-Implementation-Audit-Report.docx` | 2026-08-18 audit | Superseded: says isolation missing, ops "not started" |
| `docs/DriveIQ-Realtime-Notification-Message-Broker-Plan.docx` | Reverb + Redis plan | Partly built (`notifications`, events, channels, Echo helper); Reverb off |
| `docs/DriveIQ-Backlog.xlsx` | Aug 2026 backlog (Backlog/Findings/Risks) | Superseded by `DriveQ-Backlog.xlsx` |
| `docs/DriveQ-Backlog.xlsx` | 2026-10-20 remaining work + shipped ledger | Current |
| `docs/DriveIQ-School-Admin-Dashboard-Design.pptx`, `DriveIQ-Platform-Admin-Dashboard.html` | UI design specs | Reference mockups |

Skills: `.claude/skills/driveiq-architecture`, `.claude/skills/driveiq-docs-map`. Subagents: `.claude/agents/{architect,backend-engineer,frontend-engineer}.md`.

---

## 10. Contact

Project owner: Ratnesh Gupta (ratnesh.k.gupta@icloud.com). Update this file when milestones close, architecture changes, or a doc becomes stale.
