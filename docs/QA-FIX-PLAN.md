# DriveQ — QA Fix Plan

**Source:** `docs/QA-TEST-REPORT-2026-10-20.md` (IDs below refer to it) · **Goal:** make the Pune pilot (M11) launchable.
**Approach:** one branch + one PR per slice, merged in order. Each PR is small enough to review in one sitting and ships with tests. Regression check for every PR: `make test`, `make frontend-check`, plus the matching QA script from `docs/qa/2026-10-20/scripts/` re-run against a disposable DB.

Branch naming (per CLAUDE.md §9): `bugfix/<slug>` for defects, `feature/<slug>` for new UI, `infra/<slug>` for deployment.

---

## Priority overview

| Pri | PR | Branch | Fixes | Effort | Blocks launch? |
|---|---|---|---|---|---|
| P0 | 1 | `infra/prod-hardening` | INF-01, INF-02, BUG-08 | 1–2 d | **Yes** |
| P0 | 2 | `bugfix/quick-wins-reviews-notifications` | BUG-01, BUG-11, BUG-15, BUG-13 | 0.5 d | No (BUG-01 breaks a core page) |
| P1 | 3 | `bugfix/school-soft-delete-retention` | BUG-04 | 1 d | Yes, before real data |
| P1 | 4 | `bugfix/pagination-leads-learners-search` | BUG-02, BUG-03 | 2 d | Yes at scale |
| P2 | 5 | `bugfix/scheduling-integrity` | BUG-05 | 0.5–1 d | No |
| P2 | 6 | `bugfix/homepage-trust-and-near-me` | BUG-09, BUG-12 | 0.5–1 d | Trust/legal |
| P2 | 7 | `feature/start-conversation` | BUG-10 | 1–2 d | No |
| P3 | 8 | `feature/seo-metadata` | BUG-07 | 2–4 d | Organic acquisition |
| P3 | 9 | `feature/frontend-performance` | BUG-06 | 2–3 d | Targets in §9 |
| P4 | 10 | `bugfix/low-polish` | BUG-14, 16–21 | 1–2 d | No |

Recommended order: 1 and 2 in parallel (independent), then 3 → 4 → 5/6/7 in any order → 8 and 9 (9 benefits from 4) → 10.

---

## P0 — Before any public traffic

### PR 1 — Production hardening (INF-01, INF-02, BUG-08)
**Depends on a decision:** the hosting layout (DigitalOcean droplet + Cloudflare per CLAUDE.md §3). Assume Nginx/Caddy in front of PHP-FPM.

1. **Trusted proxies (INF-01)** — `backend/bootstrap/app.php`: add `$middleware->trustProxies(at: env('TRUSTED_PROXIES'), headers: <X-Forwarded-For|Proto|Host|Port>)`; document `TRUSTED_PROXIES` (Cloudflare ranges or LB IP) in `.env.example` and `docs/DEPLOY.md`. When behind Cloudflare, also trust `CF-Connecting-IP`.
2. **Real app server (INF-02)** — `docker-compose.prod.yaml` + `backend/Dockerfile`: multi-stage production image (composer `--no-dev --optimize-autoloader`, OPcache on, `config/route/view:cache` at build), PHP-FPM, Nginx service as the only published port (80/443). Remove `composer install`/`artisan serve`/port 8000 from the prod command. Keep `docker-compose.yaml` (local) unchanged.
3. **Security headers (BUG-08)** — set at the proxy (Nginx/Cloudflare): `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` (or CSP `frame-ancestors 'none'`), `Strict-Transport-Security`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(self)`, and a CSP (start in `Report-Only`; the app needs Google Maps/Unsplash origins). Add a small Laravel middleware for the API as defence in depth.
4. Rate limiters: leave limits as is; add a test that the key follows `X-Forwarded-For` only from trusted proxies.

**Tests / acceptance**
- Feature test: request with `X-Forwarded-For` from a trusted proxy keys the limiter on the client IP; from an untrusted source it does not.
- `scripts/sec.js` S7 passes; `scripts/load.js` against the new stack: ≥ 45 req/s on `/schools` at 40 users, p95 < 1.2 s on the same hardware as the report.
- Deployment smoke: `docs/DEPLOY.md` steps run end-to-end on a staging droplet.

**Risk:** CSP can break Maps/images — ship `Report-Only` first, enforce after one clean week.

### PR 2 — Quick wins (BUG-01, BUG-11, BUG-15, BUG-13)
- **BUG-01** — `frontend/src/pages/dashboard/reviews.tsx:39`: send `includePending: 1` (or fix the generated client's boolean serialisation so `true` → `1`; check other `boolean` params such as `followUpDue`, `createLogin`). Add `Accept: application/json` to the generated client's default headers so validation errors never redirect. Backend: keep `boolean` rule.
- **BUG-11** — `backend/routes/api.php` (notifications routes): `->whereUuid('id')` → 404 instead of 500.
- **BUG-15** — `VehicleController::store/update`: catch the unique-violation (`UniqueConstraintViolationException`) and return the same 422 as the validation rule.
- **BUG-13** — learner overview/documents queries: `retry: (n, err) => err.status !== 404 && n < 2`.

**Tests**
- PHPUnit: `GET /reviews?schoolId=…&includePending=1` as owner returns pending reviews; as anonymous it does not (regression for existing behaviour).
- PHPUnit: `POST /notifications/abc/read` → 404.
- PHPUnit: concurrent-style duplicate vehicle (second insert after the validator passes) → 422.
- Playwright: `/dashboard/reviews` shows the seeded reviews; new learner sees the empty state in < 1 s.

---

## P1 — Data integrity and scale

### PR 3 — Soft delete + retention (BUG-04)
1. Migration: add `deleted_at` to `schools` (`SoftDeletes` on `School`); change FKs `payments`, `invoices`, `learners`, `attendance`, `schedules`, `subscriptions` → `schools` from `CASCADE` to `RESTRICT` (reviews can stay cascade only if the product agrees; default: RESTRICT too). Backfill: none (no data loss).
2. `SchoolService::delete` → archive (soft delete + `listing_status = suspended` + revoke owner/staff tokens + clear owner `school_id` only on hard purge). Public queries already use `School::public()`; confirm `BelongsToSchool` scope and all `School::find` call sites handle trashed rows (admin lists get `withTrashed()` with a "Restore" action — the UI already has `button-restore-school-*`).
3. Hard purge becomes an explicit artisan command `driveiq:purge-school {id} --force`, refusing when payments/invoices exist and the retention window (see `config/retention.php`) hasn't elapsed.
4. `DELETE /schools/{id}` for admin: require `?confirm=<slug>` (mirror of the UI dialog).

**Tests:** admin delete of a school with payments → school trashed, payments/invoices/learners intact, owner token revoked; restore works; purge refused inside retention; `schools` queries elsewhere ignore trashed rows; migration up/down on PostGIS CI.
**Risk:** every `School::` lookup is affected — grep and test the sitemap, search, compare, claim, outreach and webhook paths.

### PR 4 — Pagination (BUG-02, BUG-03)
**Backend**
- `GET /inquiries`, `GET /schools/{id}/learners` (and review other list endpoints: payments, schedules, audit logs, admin lists): add `page`/`perPage` (default 25, max 100), keep the `{data, meta}`/`X-Total-Count` convention already used by `/schools`; filters (`status`, `search`, date range) server-side. Preserve `sort=oldest_waiting` and `followUpDue`.
- Keep a bounded `limit` fallback so existing callers don't break until the frontend ships.
**Frontend**
- `pages/dashboard/leads.tsx`, `learners.tsx`: paged table (or "load more"), filters wired to the query; keep timeline sheet and convert flow unchanged.
- `pages/search.tsx:383`: read `X-Total-Count` (extend the API client to surface headers), show the real total, add pagination (or infinite scroll), preserve filters in the URL.
**Tests**
- PHPUnit: pagination meta, per-school isolation preserved, `perPage` cap.
- Re-run `scripts/load.js`: `/inquiries` at 20k leads < 150 ms p50, response < 100 KB.
- Playwright: seed 100 schools → search shows total, page 2 reachable; leads page with 20k rows becomes interactive < 2 s.

---

## P2 — Correctness and trust

### PR 5 — Scheduling integrity (BUG-05)
- `ScheduleController::store/update` (and the shared `schedules` service): reject `sessionDate + startTime` in the past (allow an admin/owner override flag only if the product wants back-dating — default: no).
- `attendance`: allow marking only when `now() >= session start`; `present` additionally requires the session not to be `cancelled`. Surface the reason in the 422.
- Frontend: disable the attendance buttons before start, show hint.
**Tests:** extend `SchedulingIntegrityTest` (past create → 422; future attendance → 422; same-day after start → 200). Update `biz.js` B5/B10 expectations.

### PR 6 — Homepage trust + near-me CTA (BUG-09, BUG-12)
- `pages/home.tsx:74-76`: replace the hard-coded hero card with the top featured school from `/schools/featured` (real rating, review count, price) or remove it; rename the "Happy Learners" stat to an accurate label ("Enquiries sent") or compute enrolled learners via `/stats/overview` (add `totalLearners`).
- Add the "Near me" CTA to the hero that reuses the existing search-page consent dialog and navigates to `/search` with geo applied (closes the open Phase B checkbox in CLAUDE.md).
**Tests:** Playwright: hero numbers equal API data; Near me → consent → results with distances; denied permission shows the fallback message.

### PR 7 — Start a conversation (BUG-10)
- UI entry points: learner → "Message my trainer / school" (learner overview), trainer → "Message learner" (learner list), owner/manager → "Message" on learner and trainer rows. Opens the messages page with a composer that calls `POST /messages` (receiver resolved server-side by role rules in `MessageController`).
- Decide product rules first: who may message whom (current API enforces school scope; confirm learner → school staff only).
**Tests:** PHPUnit already covers permissions; add Playwright for the three entry points and unread badge.

---

## P3 — Growth targets

### PR 8 — SEO metadata (BUG-07)
Phase 1 (1 d): per-route head management (`react-helmet-async` or a small `useDocumentMeta` hook): title, description, canonical, Open Graph/Twitter per page for home, search, locality, school detail, for-schools/for-trainers; `robots.txt` sitemap line; Laravel `GET /sitemap.xml` (published schools + localities + static pages, cached).
Phase 2 (2–3 d): crawler/WhatsApp-preview rendering. Options in order of effort: (a) edge/proxy rule that serves a pre-rendered meta shell for bot user-agents via a Laravel endpoint (`/seo/school/{slug}`), (b) build-time prerender of locality pages, (c) SSR. **Recommend (a)** for the pilot — WhatsApp link previews are the main need.
**Tests:** `curl -A WhatsApp` on `/school/<slug>` returns the school title/description/image; Playwright asserts title/canonical per route; Lighthouse SEO ≥ 95 on school and locality pages; update CLAUDE.md Phase E checkboxes only once true.

### PR 9 — Frontend performance (BUG-06)
- Route-level `React.lazy` + `Suspense` for each portal (admin, dashboard, instructor, learner) and heavy libs (Recharts, Maps); `build.rollupOptions.output.manualChunks` for vendor splits; target first-load JS ≤ 250 KB gzip for public pages.
- CLS: fixed aspect-ratio boxes for school card/hero images (`aspect-[4/3]` + `width/height`), reserved space for the cookie banner and skeletons that match final layout; `loading="lazy"` below the fold, `fetchpriority="high"` on the LCP image.
- Self-host or proxy listing images with a fallback placeholder (also closes BUG-18).
**Acceptance:** `scripts/perf.js` — mobile slow-4G LCP < 2.5 s, CLS < 0.1 on `/`, `/search`, `/school/<slug>`; bundle report attached to the PR.

---

## P4 — Polish (PR 10, `bugfix/low-polish`)

| ID | Work |
|---|---|
| BUG-14 | Indian mobile validation (`regex:/^(\+91)?[6-9]\d{9}$/`) on inquiry/contact/learner; duplicate-learner warning (same school + mobile); block duplicate pending invoice for the same learner + package |
| BUG-16 | Remove `maximum-scale=1` from `frontend/index.html`; add `aria-label`s (hamburger, 5 icon buttons on `/search`), associate the unlabelled input |
| BUG-17 | Make the cookie banner non-overlapping on forms (stack above content / `inert` consent area) |
| BUG-18 | Covered by PR 9 (image fallback) |
| BUG-19 | Show the "session expired" notice whenever a 401 clears the session |
| BUG-20 | Migration dropping `idx_schools_lat_lng` and the redundant `inquiries(school_id)` index |
| BUG-21 | Replace `window.confirm()` in admin users with the shared `AlertDialog` (i18n) |

## Decisions needed from the product owner (Info items)
1. Admin override: may an admin publish a draft whose owner hasn't verified email? (default keep)
2. Should managers see subscription/invoices and Google Business status? (default: invoices owner-only)
3. Token storage: stay on `localStorage` for the pilot, or move to httpOnly cookies post-pilot?
4. School delete semantics for PR 3: archive-only for the pilot, hard purge by command only?
5. Hosting target for PR 1 (droplet + Nginx + FPM assumed).

## Definition of done (per PR)
- Tests added for every fixed defect; `make test` and `make frontend-check` green; Pint clean.
- The matching QA script re-run and the result noted in the PR body; the finding's row in the QA report marked **Fixed in #<PR>**.
- CLAUDE.md status updated when a checklist item is genuinely complete (Phase B near-me, Phase E metadata).
- No schema change without a reversible migration and a PostGIS CI run.

## Final gate (after PR 10)
Re-run the full QA suite on a staging stack built from `docker-compose.prod.yaml`: sweep, RBAC, IDOR, security, business, race, load (single `/inquiries` ≤ 150 ms, `/schools` ≥ 45 req/s at 40 users), perf (LCP < 2.5 s). Pilot go/no-go on a clean report.
