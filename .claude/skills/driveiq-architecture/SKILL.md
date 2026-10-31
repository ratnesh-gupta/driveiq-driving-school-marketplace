---
name: driveiq-architecture
description: DriveQ system architecture cheat sheet (actors, roles, school_id isolation, core flows, queues/schedule, plan gating, integrations). Use when designing or reviewing any change that crosses backend, frontend, billing, consent or messaging.
---

# DriveQ architecture

Full detail: `CLAUDE.md` (rules) and `docs/SYSTEM-FLOW.md` (diagrams, docs-vs-reality).

**Stack:** React 19/Vite SPA -> Laravel 13 REST (`/api`, Sanctum) -> PostgreSQL 16 + PostGIS; Redis queue/cache; `worker` + `scheduler` containers; optional Reverb; messaging driver layer (`log`/`null`).

**Roles:** `users.role` in admin, school, instructor, learner. Owner vs manager = `school_admins`.

**Isolation chain:** `auth:sanctum` -> `role:` -> `plan.features` (`config/plans.php`) -> `SchoolAccess` -> `BelongsToSchool` global scope; foreign IDs via `SchoolScopedIds`. Public reads: `School::public()`.

**Main flow:** search (PostGIS rank, `config/geo.php`) -> `POST /inquiries` -> `InquiryCreated` -> queued notifications -> lead lifecycle (new, contacted, follow-up, interested, converted, lost) -> `POST /inquiries/{id}/convert` -> Learner -> schedules/attendance/progress/payments/docs.

**Supply flow:** Prospect CRM -> unclaimed listing -> outreach -> `/claim/:token` + code -> draft -> published (owner email verified, phone, locality, map location).

**Money:** 30-day trial, plan feature gating (reads allowed, writes 402), manual GST invoices, sponsored slots/campaigns. Razorpay not integrated.

**Compliance:** consents, data-subject requests, `driveiq:retention`, append-only audit log.

**Schedule:** retention 02:30, subscriptions 03:15, lead-reminders and outreach every 15 min, ops-reminders and response badges hourly.

**Naming:** user-facing DriveQ; code, keys, commands stay `driveiq`.

When in doubt, check `backend/routes/api.php` and `config/*.php` before docs.
