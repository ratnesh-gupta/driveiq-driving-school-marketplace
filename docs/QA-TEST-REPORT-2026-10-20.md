# DriveQ — Full QA Report (browser, flow, security audit, stress)

**Date:** 2026-10-20 · **Branch:** `claude/optimistic-franklin-fczosz` @ `f05d3cb` · **Tester:** Claude (acting as QA engineer)
**Scope:** every role in the product — Visitor, Learner, Trainer (instructor), School Owner, School Manager, Platform Admin.
**Evidence:** `docs/qa/2026-10-20/` (scripts, raw results, screenshots). All scripts are re-runnable.

---

## 1. Verdict

| Area | Result |
|---|---|
| Backend test suite (`make test` equivalent) | **311 / 311 passed** (1,816 assertions) |
| Frontend `make frontend-check` (typecheck + build) | **Passes** (1 bundle warning, see BUG-06) |
| RBAC (177 API routes × 7 principals = 1,204 checks) | **No role leaks.** Only finding: a 500 on a malformed id |
| Cross-school isolation (IDOR, 236 checks + 37 targeted) | **Solid — no leak found** |
| Uploads, signed links, brute-force lock-out, token revocation, mass-assignment | **Solid** |
| Core flows (inquiry → lead → learner → schedule → pay → review) | **Work**, with the defects below |
| Pilot-launch readiness | **Not ready.** 2 launch blockers (infra), 4 high-severity defects, 8 medium |

**Headline:** the application logic and security model are strong. What breaks is (a) one whole school-dashboard page, (b) anything that grows past a few dozen rows (unpaginated lists), (c) the SEO and speed requirements, and (d) the production deployment config (single-process server, no proxy trust, no security headers). A destructive hard-delete of a school wipes financial records, contradicting the stated retention policy.

**Counts:** 2 Critical (launch-blocking infra) · 4 High · 8 Medium · 9 Low · 6 Info. Details in §4.

---

## 2. Environment and how the Makefile was used

The sandbox has no running Docker daemon by default. I started `dockerd` and used the Makefile wherever it could work:

| Make target | Result |
|---|---|
| `make up` | **Failed — environment, not a project bug.** Docker Hub returned `429 Too Many Requests` for `node:24-bullseye`, and `apt-get` inside the backend image build could not reach Debian mirrors through the sandbox proxy |
| `make up-infra` | ✅ PostGIS 16 + Redis 7 containers healthy |
| `make test-db` | ✅ created `driveiq_test` |
| `make frontend-check` | ✅ typecheck + build pass |
| `make test`, `make migrate`, `make seed`, `make fresh`, `make artisan`, `make demo` | ❌ all `exec` into the missing `backend` container. I ran the identical artisan commands natively (`php artisan test`, `migrate`, `db:seed`, `migrate:fresh --seed`, `schedule:list`, `driveiq:retention`, `driveiq:ops-reminders`) against the Makefile-started Postgres/Redis |

Deviation, stated plainly: **backend** ran as native PHP 8.3 (`artisan serve`, queue worker, scheduler) and **frontend** as the Vite dev server (and `vite preview` of the production build for performance). Data came from `db:seed` (OpsDemoSeeder: 1 school, 6 total, 1 learner, 1 trainer). Browser: Playwright 1.56 / Chromium. External hosts (Unsplash images, Google) are blocked by the sandbox proxy; those console errors are ignored throughout.

> Two Makefile nits: `demo` is missing from `.PHONY`, and no target runs the frontend or Playwright checks.

---

## 3. What was tested

### 3.1 Browser testing (Chromium, desktop 1366×850 and mobile 390×844)
- **122 page loads** across all roles: 18 public routes, 12 admin, 16 school-owner, 6 trainer, 5 learner. Checked console errors, page errors, failed API calls, redirects, horizontal overflow, empty/thin pages, error text. After accepting the consent gates, **no page has overflow, JS exceptions or empty content**.
- **Route-guard matrix (16 combinations):** each role against every other role's portal; visitors against all portals. Wrong-role users are bounced to their own portal; visitors go to `/auth/login`. ✅
- **UI flows:** visitor search → school detail → inquiry; contact form; compare; learner registration; wrong password; weak password (submit disabled); owner lead lifecycle (status change, notes, follow-up filter, sort); trainer attendance + leave request; learner document upload; admin user deactivate (native `confirm()` dialog), school delete confirm; geolocation granted and denied; session-expiry; mobile nav drawer; Hindi/Marathi switch; stored-XSS rendering (inert ✅).

### 3.2 API / flow / audit testing
RBAC matrix, IDOR, auth hardening, uploads, business rules, concurrency races, manager invite lifecycle, email verification and password reset (using the emailed links from the log mail driver), plan gating (trial expiry), retention and scheduler commands.

### 3.3 Stress testing
Node load generator on PostGIS seeded with **5,008 schools and 20,008 inquiries**, at 1 / 10 / 40 concurrent users. Race tests with 12 simultaneous writers. Inquiry flood (30 requests). Web-vitals under throttled mobile 4G.

### 3.4 Not tested (be aware)
Claim links and one-time codes, prospects CSV import, outreach sending, Google Ads webhook, Google Business, WhatsApp provider (no provider configured), Razorpay (not integrated), realtime/Reverb, real email delivery, real SMS/OTP (doesn't exist), Playwright on Safari/Firefox, screen-reader testing. Admin GET endpoints for these were smoke-hit by the RBAC sweep with no 5xx.

---

## 4. Defects

Severity: **Critical** blocks pilot launch · **High** breaks a stated requirement or a core flow · **Medium** degrades a flow/requirement · **Low** polish/robustness · **Info** observation.

### Critical (launch blockers — infrastructure)

**INF-01 — No trusted-proxy config; rate limits will be keyed on the proxy IP.**
`bootstrap/app.php` never calls `trustProxies`, and limiters use `$request->ip()` (`AppServiceProvider.php:60-75`). Behind Cloudflare/Nginx (the planned setup) every visitor shares one IP, so the **whole platform** gets 5 inquiries/min, 5 public-form posts/min and 10 auth attempts/min. India's CGNAT mobile networks make this worse even with correct config.
*Fix:* `->withMiddleware(fn ($m) => $m->trustProxies(at: <cloudflare/LB ranges>))`, then re-verify limits behind the proxy.

**INF-02 — Production compose serves the app with `php artisan serve` (single process), publishes port 8000, runs `composer install` at boot.**
Measured on this stack: ~12–25 req/s per endpoint, p95 ≈ 3.5 s at 40 concurrent users, and one slow request blocks everyone (head-of-line blocking). With 4 PHP workers throughput rose 3–4× (45–85 req/s), proving it is process-bound, not query-bound. See §5.
*Fix:* PHP-FPM + Nginx (or FrankenPHP/Octane) with OPcache; build dependencies into the image; don't publish 8000.

### High

**BUG-01 — School dashboard › Reviews page never loads reviews.** `/dashboard/reviews` calls `GET /api/reviews?schoolId=1&includePending=true`. Laravel's `boolean` rule rejects the string `"true"` (accepts `1/0/"1"/"0"/true/false`), so the API answers 422. The generated client sends no `Accept: application/json`, so Laravel turns the validation error into a **302 redirect to the frontend**, which the browser reports as a CORS failure. The owner sees an empty page: school owners **cannot view or respond to reviews** (CLAUDE.md §4.2 "Reviews Management").
*Repro:* log in as `info@skylinedrive.in` → Reviews. `curl ".../api/reviews?schoolId=1&includePending=true" -H "Accept: application/json"` → 422; with `includePending=1` → 200.
*Fix:* send `1/0` (or normalise in the request class); add `Accept: application/json` to the generated client so validation errors never redirect.
Screenshot: `screenshots/BUG-01-school-reviews-page.png`.

**BUG-02 — `GET /inquiries` is unbounded.** With 20,000 leads for one school: 7 MB response, 1.7–2.0 s server time for **one** request (2 SQL queries; time is serialization), 13 s p50 at 10 users, 38 s at 40 users. The leads page then renders all 20,003 rows in the DOM and the tab does not become interactive within 120 s. `/schools/1/learners` has the same shape (no pagination). One heavy school can stall the whole single-process server (INF-02).
*Fix:* server-side pagination + filters for leads and learners; virtualise or page the table.

**BUG-03 — Search shows only the first page; no pagination; wrong count.** The UI prints `{schools?.length} schools found` (page size = 20), has no pagination controls and ignores the `X-Total-Count` header the API sends (5,007) — so with more than 20 schools in Pune the rest are **unreachable except via filters**, and the "N schools found" label is false. Invisible in the 6-school seed; will bite immediately in the pilot (`pages/search.tsx:383`).
Screenshot: `screenshots/BUG-03-…png`.

**BUG-04 — Admin hard-delete of a school wipes financial/training records.** `DELETE /api/schools/{id}` calls `$school->delete()`; FKs `payments`, `invoices`, `learners`, `attendance`, `schedules`, `reviews`, `subscriptions` → `schools` are all `ON DELETE CASCADE`. This contradicts `driveiq:retention`'s own statement ("audit_logs, payments and training records are never deleted"), CLAUDE.md "soft deletes where applicable", and Indian GST record-keeping. The UI gates it with a confirm dialog; **the API does not** — one call as admin deletes the school and orphans its owner (`users.school_id` → null). I triggered this by accident with the test harness (admin is legitimately allowed `DELETE`), which is how I found it.
*Fix:* soft-delete (suspend/archive) schools; `RESTRICT` instead of `CASCADE` for payments/invoices/learners; require a confirmation token for hard deletion.

### Medium

| ID | Finding | Evidence / repro | Suggested fix |
|---|---|---|---|
| **BUG-05** | **Sessions can be created in the past, and a trainer can mark a future session "present" — which flips it to *completed*.** | `POST /schools/1/schedules` with `sessionDate:2020-01-01` → 201. Trainer marked the 21-Oct 07:00 session present on 20-Oct → 200, `sessionStatus: completed`. Attendance fraud / wrong progress data. | Reject past dates on create; allow attendance only from session start. |
| **BUG-06** | **Web-vitals miss the project's own targets.** Production build = one **1.55 MB JS bundle (437 KB gzip)**, no code-splitting; admin/trainer/learner code ships to every visitor. Slow-4G mobile: **LCP 3.4–4.0 s** (target < 2.5 s), FCP = LCP (blank until JS runs). **CLS 0.32 (school detail, desktop), 0.58–0.65 (search/school, mobile)**; target "minimal" (< 0.1). | `scripts/perf.js`. Desktop unthrottled is fine (LCP 0.3–0.8 s). CLS partly because images load late and have no reserved box. | Route-level `lazy()`, manualChunks; fixed aspect-ratio image boxes; preload hero. |
| **BUG-07** | **SEO: no per-page `<title>`, meta description, canonical or Open Graph tags; no `sitemap.xml`.** Every URL — home, `/locality/baner`, `/school/…` — serves the identical shell with the home page title. Only JSON-LD is injected client-side. WhatsApp/Facebook previews of school links show the generic home card — material because WhatsApp is the core channel. CLAUDE.md Phase E marks "Metadata/canonical strategy" ✅, but there is no canonical/meta code in `frontend/src`. | `curl localhost:4173/school/skyline-driving-academy` vs `/` — same `<title>`/OG. `grep -ri canonical frontend/src` → nothing. | Per-route head management (react-helmet-async / `useEffect`) at minimum; SSR/prerender (or a Laravel-rendered meta endpoint) for crawlers; add `sitemap.xml` + sitemap line in `robots.txt`. |
| **BUG-08** | **No security headers.** API and SPA responses lack `X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`, `Strict-Transport-Security`, `Content-Security-Policy`, `Referrer-Policy`, `Permissions-Policy`. Clickjacking is possible. (Download endpoint does send `nosniff`.) | `scripts/sec.js` S7. | Add at the proxy (preferred) or a middleware. |
| **BUG-09** | **Homepage shows hard-coded fake social proof.** Hero card: "Skyline Driving ★ 4.8 · **124 reviews** · Starting **₹3,500**" — actual: 2 reviews, ₹4,500 (`pages/home.tsx:74-76`). The "Happy Learners" stat is `totalInquiries` (`home.tsx:170`) — inquiries are not learners. For a platform whose pitch is verified reviews, this is a trust/legal (consumer-protection) risk. | Screenshot `BUG-14-…png`. | Bind to real data or remove. |
| **BUG-10** | **In-app messaging cannot be started.** Learner, trainer, school and admin "Messages" pages show "No threads yet" and the UI has no new-conversation action anywhere; `POST /messages` is only used to reply inside an existing thread. Seed data has no threads, so the feature is unreachable. (Domain 7 "Internal Messaging".) | `frontend/src/pages/*/messages.tsx`. | Add "Message my trainer/learner/school" entry points. |
| **BUG-11** | **`POST /notifications/{id}/read` returns 500 for any non-UUID id** (Postgres `invalid input syntax for type uuid`), and with `APP_DEBUG=true` returns the SQL. Found by the RBAC sweep (every role, 6 calls). | `curl -X POST .../notifications/1/read -H "Authorization: …"`. | `->whereUuid('id')` on the route (→ 404). |
| **BUG-12** | **Home page lacks the "Near me" entry point** (Phase B open item, confirmed). Near-me itself works on `/search` with a proper consent dialog (graceful for deny/grant, distances shown). | `shots: geo-consent-dialog.png`. | Add the CTA to the hero. |

### Low

| ID | Finding |
|---|---|
| **BUG-13** | A newly registered learner (not enrolled) sees a **grey skeleton for ~7 s** on Overview/Documents before the "You're not enrolled" empty state: `GET /learner/me` 404 is retried by TanStack Query. Set `retry: false` for 404. |
| **BUG-14** | Inquiry `phone` is only `string|max:255`: `"abc"` is accepted and becomes a lead (+ WhatsApp targets). Add an Indian mobile format rule. Also: no duplicate detection for learners (12 identical learners created concurrently) or duplicate pending package invoices for the same learner+package. |
| **BUG-15** | Duplicate vehicle registration under concurrent submit → one request gets **500** (unique-index violation) instead of 422; data stays consistent. |
| **BUG-16** | Accessibility: viewport meta has `maximum-scale=1` (blocks pinch-zoom, WCAG 1.4.4); on `/search`, 5 buttons without accessible names and 1 unlabelled input; the mobile hamburger has no `aria-label`. |
| **BUG-17** | Cookie banner overlaps the registration consent checkboxes at 850 px height (fresh session) — the checkboxes can't be clicked until it is dismissed. |
| **BUG-18** | School card images are hot-linked from Unsplash with no fallback; if the CDN blocks/fails you get broken-image alt text over a grey box (seen in the sandbox). Also dependent on a third party for the main listing visual. |
| **BUG-19** | After a token is invalidated server-side the user lands on `/auth/login` without the "session expired" notice. |
| **BUG-20** | Duplicate DB indexes: `idx_schools_lat_lng` and `schools_latitude_longitude_index` (same columns); `inquiries(school_id)` is covered by composite indexes. Write overhead, no benefit. |
| **BUG-21** | Admin "Deactivate" uses native `window.confirm()` while "Delete school" uses a styled dialog — inconsistent and untranslatable. |

### Info (decisions for the product owner)
- Auth token is kept in `localStorage` (`driveiq_auth_token`) — acceptable with React's escaping (tested: stored XSS payloads in lead names, notes and reviews render inert) but readable by any XSS. Consider httpOnly cookies later.
- Admin `PATCH /admin/schools/{id}/listing-status` can publish a **draft whose owner has not verified their email**, bypassing the gate that applies to owners. Probably intended as an override.
- A **Manager** may read subscription, invoices and Google Business status (writes are correctly owner-only). Confirm whether billing visibility should be owner-only.
- Login failure returns 422 (not 401) with a validation-style body — fine, just note for API consumers.
- `POST /inquiries` bot guard works as designed (form must be open ≥ 3 s; honeypot `website`).
- Auth limiter is shared across register/login/forgot/reset per IP (10/min) — see INF-01 for the shared-IP risk.

---

## 5. Stress / performance results

Dataset: 5,008 published schools, 20,008 inquiries (school 1). Machine: 4 vCPU shared with the load generator, so absolute numbers are conservative; the *shape* is what matters.

**A. Production-compose style: single `php artisan serve` process** (p50 / p95 ms, req/s)

| Endpoint | 1 user | 10 users | 40 users |
|---|---|---|---|
| `/healthz` | 14 / 19 · 70 rps | 112 / 135 · 88 rps | 457 / 541 · 91 rps |
| `/schools` (list) | 86 / 114 · 11 rps | 893 / 1023 · 12 rps | 3,473 / 3,592 · 16 rps |
| geo 5 km (PostGIS) | 84 / 107 · 12 rps | 847 / 1001 · 13 rps | 3,246 / 3,359 · 17 rps |
| geo 10 km + filters | 69 / 92 · 14 rps | 693 / 857 · 15 rps | 3,100 / 3,266 · 18 rps |
| `/schools/{slug}` | 47 / 61 · 21 rps | 422 / 487 · 25 rps | 1,746 / 1,787 · 28 rps |
| owner dashboard | 87 / 106 · 11 rps | 801 / 891 · 13 rps | 3,259 / 3,726 · 17 rps |
| **`/inquiries` (20 k leads)** | **1,663 / 1,732 · 0.6 rps** | **12,971 / 18,981** | **38,333 / 67,591** |

**B. Same app, 4 PHP workers** (10 / 40 users): `/schools` 233 / 915 ms p50 at 44–49 rps; geo 5 km 224 / 845 ms at 46–52 rps; slug 119 / 497 ms at 84–86 rps; dashboard 276 / 997 ms at 39–45 rps. ≈ 3.5× throughput; no errors at any level.

**C. Query efficiency (in-process):** `/schools` 3 queries, geo search 3 queries / 39 ms, dashboard 22 queries / 53 ms, no N+1. The PostGIS GiST index (`idx_schools_location`) is used; 5,000 schools is not a problem for the database. Cost is PHP bootstrap per request (CLI server, no OPcache) and unbounded serialization (BUG-02).

**D. Concurrency correctness (12 simultaneous writers, 4 workers):**
✅ identical overlapping sessions → exactly 1 booked · ✅ duplicate vehicle → exactly 1 (but 2 requests got 500, BUG-15) · ✅ concurrent `mark-paid` → exactly 1 transition · ✅ concurrent reviews → ≤ 1 · ✅ inquiry flood of 30 → 5 accepted, 25 × `429`.
ℹ️ 12 duplicate learners and 6 duplicate pending invoices were all accepted (BUG-14).

**E. Mobile web vitals (production build, throttled 1.6 Mbps / 150 ms RTT / 4× CPU)** — see BUG-06. Desktop unthrottled: LCP 0.31–0.77 s.

---

## 6. Security audit summary

**Passed (tested, not assumed):**
- RBAC on all 177 routes: unauthenticated → 401, wrong role → 403, no role leaks.
- Tenant isolation: a second school owner (school 7) got **no** data and no write access to school 1 through 59 endpoints × real IDs; cross-school IDs injected in schedule/learner/vehicle/payment bodies are rejected (`422/403/404`). Learner ↔ learner and trainer ↔ unassigned-learner/trainer access blocked (403) on every route tested.
- Mass assignment: owner PATCH with `verified`, `premiumVerified`, `rating`, `reviewCount`, `listingStatus`, `featured`, `plan`, `school_id` — **ignored** (verified in the DB: `verified=false, rating=0, listing_status=draft`).
- Auth: login brute-force locked after 3–5 attempts (429); identical responses for known/unknown users on login and forgot-password (no enumeration); cannot self-register as admin; server-side password policy; logout, password reset and account deactivation **revoke tokens immediately**; reset token single-use; email-verify link signed, tamper-proof and idempotent; manager invite single-use; removed manager loses access instantly.
- Uploads: `.php`, `.svg`, PHP-in-`.jpg`, > 5 MB rejected; path-traversal filename sanitised; document links are signed, tamper/ID-swap → 403; other school / other learner / anonymous → 403/401; `nosniff` + `Content-Disposition` on download.
- Review integrity: only enrolled learners (or the one-time inquiry link) can review; duplicates blocked; abuse auto-hide at 3 reports works; anonymous `includePending` does not expose pending reviews.
- Plan gating: after trial expiry reads stay `200`, writes return `402 plan_required`, advanced analytics `402`; trainer/learner portals unaffected.
- SQLi probes on 9 search params → no 500s; absurd geo parameters → 422. CORS allows only the configured origin.
- XSS: `<img onerror>` payloads in lead names, notes and reviews render inert in the UI.
- DPDP: consent gates present for each portal and cookies; retention job dry-run safe; scheduler registers all 7 jobs.

**Failed / open:** INF-01, INF-02, BUG-04, BUG-08, BUG-11, BUG-14 (phone validation). Everything else is in §4.

---

## 7. Requirements traceability — where the system breaks versus our own docs

| Requirement (source) | Status | Ref |
|---|---|---|
| Reviews Management: school can view/respond (CLAUDE §4.2) | **Broken in UI** | BUG-01 |
| Search/discovery: all schools reachable, correct counts (§4.1) | **Broken beyond 20 schools** | BUG-03 |
| Lead Management: pipeline usable at scale (Domain 2) | **Breaks at ~thousands of leads** | BUG-02 |
| Payments/training records never deleted; soft deletes (retention policy, §9 API design) | **Violated** by cascade hard-delete | BUG-04 |
| Scheduling integrity & attendance (Domain 5) | **Gaps:** past sessions, future attendance | BUG-05 |
| LCP < 2.5 s, minimal CLS, ISR/SSG for SEO pages (§9 Performance Targets) | **Not met** | BUG-06, BUG-07 |
| Phase E: metadata/canonical strategy ✅ (marked done) | **Not implemented** | BUG-07 |
| Phase B: near-me entry on homepage (open) | **Still open** | BUG-12 |
| Verified-trust positioning | **Hard-coded fake stats on home** | BUG-09 |
| Domain 7 internal messaging | **Cannot start a conversation** | BUG-10 |
| M11 pilot infra: secure, resilient | **Blocked** | INF-01, INF-02, BUG-08 |
| RBAC permission matrix (§9) | **Met** (manager profile edit permitted by matrix) | — |
| School-id isolation (§7) | **Met** | — |
| OTP/mobile login | Known limitation, not tested | — |

---

## 8. Recommended fix order

1. **INF-01, INF-02, BUG-08** — before any public traffic (one afternoon of proxy/FPM/headers work).
2. **BUG-01** (one-line client fix) and **BUG-11** (route constraint) — minutes each.
3. **BUG-04** — change FKs/soft-delete before any real customer data exists; it is a migration.
4. **BUG-02 + BUG-03** — pagination for leads, learners, search.
5. **BUG-05, BUG-09, BUG-10** — data integrity and trust.
6. **BUG-06, BUG-07** — performance/SEO (largest effort; needed for the organic-acquisition thesis).
7. Lows as capacity allows.

The leave-behind: `docs/qa/2026-10-20/scripts/*` can be rerun after fixes (`tokens.js` first to mint test users, then `rbac.js`, `idor.js`, `sec*.js`, `biz.js`, `race.js`, `load.js`, `perf.js`, `sweep2.js`). **Warning:** `rbac.js` calls every endpoint as every allowed role, including `DELETE`s — run it only against a disposable database (`make fresh` afterwards).
