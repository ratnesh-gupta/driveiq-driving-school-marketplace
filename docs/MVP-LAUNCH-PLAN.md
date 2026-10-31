# DriveQ — MVP Launch Plan (Pune pilot)

**Date:** 2026-10-31  **Author:** architecture review  **Status:** proposal, awaiting owner decisions (section 8)
**Scope:** the smallest set of work that lets real Pune schools and learners use DriveQ safely, plus a way to seed listings from Google. Plan only; no code changed.

Verified against code on 2026-10-31: `backend/routes/api.php`, `routes/channels.php`, `routes/console.php`, `bootstrap/app.php`, `config/services.php`, `config/messaging.php`, `.env.example`, `app/Models/*`, `app/Services/{ProspectService,GoogleBusinessService,ListingOnboarding}.php`, `frontend/public/`.

---

## 1. Documents used and which are stale

| Document | Used for | Verdict |
|---|---|---|
| `docs/PROJECT-PLAN.md` s2 (updated 2026-10-02) | Status of record, "Still Open" table | Current. Header/version and phase tick-boxes stale |
| `docs/ROADMAP.md` v2.0 (20 Oct) | M11 tickets DIQ-1301..1313, M12 DIQ-1401..1407, tracks DIQ-18xx | Current, but untracked in git; M11 omits some items below (model scoping, SEO, mailer, channel leak) |
| `docs/SYSTEM-FLOW.md` (31 Oct) | Runtime topology, flows, doc-vs-reality table | Current |
| `docs/DriveQ-Backlog.xlsx` (Oct) | Remaining / milestone sheets mirror ROADMAP | Current (same content as ROADMAP) |
| `docs/DEPLOY.md` | Prod checklist | Stale: says Laravel 11, no worker/scheduler/Reverb supervision, no M11 gaps |
| `docs/DriveIQ-Documentation-Implementation-Audit-Report.docx` (18 Aug) | Original audit | Historical. Its CRITICAL findings (no BelongsToSchool, no audit log, Haversine, mock compare) are all fixed; the remaining live themes are 7 unscoped models and test discipline |
| `docs/DriveIQ-Backlog.xlsx` (Aug) | n/a | Stale, superseded by DriveQ-Backlog.xlsx |
| `docs/DriveIQ-Realtime-Notification-Message-Broker-Plan.docx` | Reverb design | Partly built; see channel leak below |
| `docs/Features/PRD-Google-Business.md` | GBP scope | Connect/import shipped (DIQ-1107); reviews sync/posts still future. Contains no Places API design |
| `docs/DPDP-Compliance.md` | Consent/outreach rules | Current; privacy mailbox must be real before launch |
| `docs/Features/Geo-Aware.md`, `docs/PHASE-1-GEO-RANKING.md` | Geo, near-me | Geo-Aware has planned items; ranking doc partly historical |
| `BACKEND-ASSESSMENT.md`, `PHASE-A-IMPLEMENTATION.md`, `ARCHITECTURE.md` table lists | n/a | Historical or aspirational; do not use for status |

Note: there is no document specifying Google Places API ingestion. The "fetch driving institutes from Google" requirement is only partly covered by M9 (CSV import with `google_place_id`, GBP OAuth import by the owner). Section 4 designs the missing piece.

---

## 2. True MVP launch gap

Product features are essentially complete (M1-M10, 311 tests). What blocks a real launch is production safety, delivery of notifications, a supply of listings, and discoverability. Findings below were checked in code.

Verified findings:
- `routes/channels.php`: `school.{id}` admits any user whose `school_id` matches, which includes instructors and learners. `NotificationCreated` broadcasts to `school.{id}` when `notification.school_id` is set, so once Reverb is on, staff-only payloads (leads, billing) reach trainers and learners. Not exploitable today (`BROADCAST_CONNECTION=log`) but must be fixed before Reverb (DIQ-1404).
- Models with a `school_id` column and no `BelongsToSchool`: `AppNotification`, `AuditLog`, `FeaturedPlacement`, `OutboundMessage`, `GoogleBusinessConnection`, `ListingClaim`, `PlatformInvoice` (grep confirms 0 uses). Some are intentionally cross-school for admin (AuditLog, FeaturedPlacement, PlatformInvoice, ListingClaim pre-claim), so the fix is a per-model decision, not a blanket trait (see WP-B2).
- No trusted proxies, security headers or custom middleware (only `EnforcePlanFeatures`, `RoleMiddleware`); `/healthz` is static.
- `.env.example` defaults `DB_CONNECTION=sqlite`, `MAIL_MAILER=log`, `MESSAGING_DRIVER=log`, `BROADCAST_CONNECTION=log`; the app needs PostGIS, so sqlite is a trap.
- Frontend has no per-route meta (no helmet/`document.title` anywhere), no sitemap, and `robots.txt` is only `Allow: /`. Locality landing pages exist but cannot rank.
- No OpenAPI spec; hand-written client types.
- Messaging driver layer has only `log`/`null` classes. Mail is `log`. Payments are manual.

### 2.1 MUST for launch

Pilot definition: 10-30 Pune schools, learners using search/enquiry, school owners getting leads by email (and WhatsApp if a provider is chosen), manual billing.

| # | Item | Effort | Owner | Source |
|---|---|---|---|---|
| M1 | Fix broadcast channel leak: staff-only channel `school.{id}.staff` vs `school.{id}`; only owner/manager (and admin) may join; payloads carry no PII beyond need (DIQ-1404 precondition) | S | backend | review |
| M2 | Scope or deliberately exempt the 7 unscoped `school_id` models, with a test that proves each choice | M | backend | review |
| M3 | Trusted proxies + correct client IP (DIQ-1303) | S | backend | ROADMAP |
| M4 | Security headers and CSP verified on live app (DIQ-1304) | S | backend+infra | ROADMAP |
| M5 | Deep health check, plus queue-lag and scheduler-heartbeat signals (DIQ-1307) | S | backend | ROADMAP |
| M6 | Production seed: localities, plan matrix, admin bootstrap only; hard refusal of demo seeders (DIQ-1309) | S | backend | ROADMAP |
| M7 | `.env.example` defaults to pgsql; add `.env.production.example`; boot-time assertion that prod is not using log mailer/sqlite/APP_DEBUG | S | backend | review |
| M8 | Real transactional mailer (password reset, invites, lead emails, review links) and separate outreach mailbox with SPF/DKIM/DMARC | S code, M ops | infra/ops | decision D2 |
| M9 | Production images, reverse proxy/TLS, deploy with rollback, supervised worker+scheduler, error tracking, uptime (DIQ-1301, 1302, 1308, 1310, 1311) | L | infra | ROADMAP |
| M10 | Object storage for documents; backups with a performed restore (DIQ-1305, 1306) | M | infra+backend | ROADMAP |
| M11 | Homepage "Near me" entry (DIQ-1312) | S | frontend | ROADMAP |
| M12 | Per-route SEO meta (title, description, canonical, OG), sitemap.xml, robots.txt with sitemap and disallow of `/dashboard`, `/admin`, `/claim` | M | frontend+backend | review |
| M13 | Google seeding pipeline (section 4): `driveiq:google-seed`, admin review queue | M-L | backend+frontend | owner request |
| M14 | Seed supply operations: ~50 curated unclaimed listings, claim flow dry-run end to end with a real mailbox | M | ops | owner request |
| M15 | Launch review / go-no-go incl. isolation regression run, restore, rollback, DPDP page and privacy mailbox check (DIQ-1313) | S | QA/ops | ROADMAP |

### 2.2 SHOULD (within 2-4 weeks after pilot start)

| Item | Effort | Owner |
|---|---|---|
| Real WhatsApp/SMS provider driver, STOP handling, delivery receipts (DIQ-1401-1403). Email-only lead alerts are acceptable for a pilot but weaker; promote to MUST if D3 chooses WhatsApp as the main alert channel | M | backend |
| Reverb install and live bell (DIQ-1404/1405), after M1 | M | infra+frontend |
| OpenAPI spec and generated client (DIQ-1808) | S | backend |
| Outreach bounce/complaint handling (DIQ-1803) | M | backend |
| Native-speaker review of Hindi/Marathi copy (DIQ-1805) | S | ops |
| Admin review bulk moderation (DIQ-1606) | S | frontend |
| Logo/banner upload, profile-view tracking (DIQ-1609, 1612) | M | backend |
| Erasure automation for training records (DIQ-1810) | M | backend |

### 2.3 LATER

Razorpay checkout/webhook/IRN (M13) unless D5 says otherwise; OTP login; learner online package payments; trainer capacity/hours; certificates; RTO directory; GBP review sync/posts; portal i18n; react-icons removal; lead auto-assignment.

---

## 3. What already exists for Google data (inspection)

| Piece | State | Relevant code |
|---|---|---|
| `GoogleBusinessConnection` + OAuth | Owner connects own Google account; `locations()` lists locations the owner manages; `import()` copies name, address, phone, hours, coordinates into the school; also proves ownership at claim (`/claims/{token}/google`) | `GoogleBusinessService`, `GoogleBusinessController`, `config/services.php` (`google.client_id/secret`) |
| Prospects | `google_place_id` unique and nullable, `source` in manual/csv/ads/gbp/outreach, `findDuplicate(phone,email,placeId)`, `meta` json, `stage` incl. `do_not_contact` | `Prospect`, `ProspectService` |
| CSV import | Header aliases (incl. place id, lat/lng), 2000 row cap, dry-run preview with statuses new/duplicate/repeated/invalid, locality match by name | `ProspectService::parseCsv/import`, `POST /admin/prospects/import` |
| Prospect to listing | `createListing()` builds `unclaimed` School, copies place id, lat/lng; never published | `ProspectService::createListing`, `ListingOnboarding` |
| Claim and outreach | One-time claim links, email/SMS/GBP verification, outreach mailer with suppression and unsubscribe | `ListingClaimService`, `OutreachService` |
| Google Ads lead webhook | Lead-form leads, unrelated to supply | `GoogleAdsLeadController` |
| Missing | Any Places API client, API key config, search-grid/query runner, source/attribution fields, refresh policy, admin "Google discovery" UI, usage/cost counter | none |

Gap: nothing finds institutes that have not already been typed into a CSV. The owner-side GBP import cannot seed (needs the owner's login).

---

## 4. Google institute seeding design

### 4.1 Options compared

| | A. Places API (New) Text/Nearby Search | B. Business Profile API import | C. CSV (manual research / export) |
|---|---|---|---|
| Who authorises | Our API key, no owner involved | The business owner via OAuth | Our staff |
| Discovery of unknown schools | Yes (queries like "driving school in Baner Pune", grid of nearby searches) | No (only businesses the signed-in user manages) | Yes but manual labour |
| Data quality | Name, address, lat/lng, types, business status, rating; phone and website cost more | Authoritative, owner-consented, full profile | Whatever researcher captures |
| ToS posture | Restrictive on storing Places content (see 4.3) | Clean: owner's own data, used for the owner's listing | Clean if sourced lawfully |
| Cost | Pay per request (4.4) | Free | Staff time |
| Exists | No | Yes | Yes |

### 4.2 Recommendation: hybrid, with Places used for discovery and place_id, not as a content warehouse

1. Discovery: new command and admin tool call Places API (New) Text Search with a narrow field mask (place id, name, formatted address, location, business status, primary type). Results go into a staging table, not directly into Prospects.
2. Store permanently only what is clearly allowed or low risk: `place_id` (Google documents place IDs as exempt from the caching limits, but says to refresh IDs older than about 12 months; confirm, see 4.3), our own research notes, and the admin's decision. Name/address/lat-lng fetched from Google are held as a short-lived cache (see 4.3) and used to help a human verify the match.
3. Contact details (phone, email, website) for the Prospect come from (a) the school's own website/contact page, researched by staff or an assistant and entered via the existing CSV import, (b) a staff-triggered Place Details call shown on screen to dial/verify, not bulk-stored, or (c) most importantly the owner during claim: GBP OAuth import (Option B) writes the owner-consented profile into the listing. This keeps the unclaimed listing thin until the owner claims it, which also fits DPDP and the "never auto-publish" rule.
4. Unclaimed listings keep showing only fields we are entitled to show; the map on school pages already uses our own stored coordinates and the Maps JS API.

Rationale: it gets near-complete discovery of Pune schools at low cost, keeps the highest-risk step (bulk storing Google-derived phone/address) out of the design, and reuses the claim and GBP OAuth flows. If the owner decides after legal check that storing Places fields is acceptable, step 2/3 can be relaxed with a config flag; the schema below supports both.

### 4.3 Maps Platform terms: what I believe, what must be confirmed

I am working from memory of Google Maps Platform Service Specific Terms and the Places API policies; I have not re-verified them against the live pages, so the owner (or counsel) should confirm each of these before committing to storage:

- Believed: Places content is not to be pre-fetched, indexed, stored or exported beyond limited temporary caching. The documented exception is the Place ID, which may be stored indefinitely and is expected to be refreshed if older than about 12 months. Latitude/longitude are, I believe, allowed to be cached for up to about 30 days. Confirm exact durations.
- Believed: Places content must be shown with attribution and, in most cases, alongside a Google map; building a standalone database or lead list from Places content is not a permitted use. Using it to cold-contact businesses may also conflict with the terms. Confirm.
- Believed: the Business Profile API is a separate product with its own policy that permits managing a profile the user owns. Confirm that importing the owner's data into our listing is within the Business Profile API policy (it is already shipped as DIQ-1107).
- Uncertain: whether reviews and ratings from Places may be displayed or stored by us (assume no; never import them).
- Uncertain: whether a hybrid "ID only plus human re-entry" approach is treated as compliant. It is the conservative reading; a legal read is advised.

Owner action: read the current Google Maps Platform Terms (section on restrictions/caching) and the Places API policies page, and record the conclusion in `docs/DPDP-Compliance.md` or a new decision record.

### 4.4 Cost and quota (estimate, verify in the Cloud console)

Pricing changed in 2025 (per-SKU free monthly allowances replaced the flat 200 USD credit). I recall Text Search (New) being billed by the highest SKU the field mask touches: Pro for basic location fields, Enterprise once phone/website/rating fields are included, at roughly 30-40 USD per 1,000 requests with a few thousand free requests per month per SKU. Treat these as order-of-magnitude figures.

Pune estimate: roughly 80 Pune localities x 3 queries ("driving school", "motor driving training school", "car driving classes") = about 240 first-page requests, each up to 20 results; follow the next-page token up to 3 pages when a page is full: at most about 720 requests. At about 35 USD per 1,000 that is about 25 USD for a full sweep with an Enterprise mask, less with a Pro mask, and possibly within free allowance. A monthly refresh sweep is the same order. Place Details for staff verification: about 100-300 calls, negligible. Set a hard daily request cap in config and a Google Cloud budget alert and quota limit on the key. Expected unique results: 300-600 institutes after dedupe (many are trainers).

### 4.5 Fields

Staging table `google_place_candidates` (admin only, no `school_id`):
`id, place_id (unique), query, locality_id (nullable, guessed), display_name, formatted_address, lat, lng, primary_type, business_status, fetched_at, expires_at (cache expiry), match_status (new, matched_prospect, matched_school, ignored, promoted), matched_prospect_id, matched_school_id, reviewed_by, reviewed_at, reject_reason`.
Prospect additions (new migration, do not edit shipped ones): `source` gains `google_places` (it is a string-checked list in the model; add to `Prospect::SOURCES` and any DB check), `google_place_id` already exists, add `google_place_id_checked_at`, and `meta.places = {query, fetched_at}`. School already has `google_place_id`.
Do not store: photos, reviews, ratings, opening hours from Places.

### 4.6 Dedupe

Order of match for each candidate: (1) `schools.google_place_id` or `prospects.google_place_id` equal; (2) same normalised phone or email if known (from Details or CSV); (3) fuzzy name (trigram `pg_trgm` or normalised lowercase without "driving school/motor/academy") within 150 m using the existing PostGIS `location`; (4) otherwise new. Fuzzy hits are never auto-merged; they become `needs_review` with the candidate shown beside the existing record. Re-running a sweep is idempotent through the unique `place_id`. Also check `prospects.stage = do_not_contact` and the outreach suppression list so a removed business never reappears.

### 4.7 Landing path (never auto-published)

```
driveiq:google-seed (or admin button)
  -> google_place_candidates (new)
  -> admin reviews (approve / ignore / merge)
  -> Prospect (source=google_places, stage=new, owner_admin_id)   [existing ProspectService]
  -> optional createListing() => School listing_status=unclaimed     [already hidden by School::public()]
  -> claim link / outreach (email only unless opt-in)
  -> owner claims, verifies, GBP OAuth import fills the profile, owner sets draft -> published through the existing gate
```
Unclaimed listings never reach `School::public()`. Add a test asserting no Places-created school appears in `/schools`, search, sitemap or compare.

### 4.8 Consent and outreach compliance

- No WhatsApp or SMS to prospects. Consent-before-WhatsApp rule stands; first contact is a human phone call or the outreach email with the existing unsubscribe link, postal address and suppression list.
- Business contact details of sole proprietors are personal data under DPDP: record `source`, purpose and date; honour `do_not_contact` and deletion requests (`markDoNotContact` already removes unclaimed listings); include prospects in the retention job (add a retention window for stale never-contacted prospects, for example 12 months).
- Cold email in India: keep sending-hour limits, daily cap and unsubscribe already in `OutreachService`; do not email addresses scraped from third-party sites without a lawful basis decision (owner to confirm; see D6).
- Audit every seed run and every promote action via `AuditLog::log`.

### 4.9 Command and admin UI

`php artisan driveiq:google-seed {--query=*} {--locality=*} {--max-requests=200} {--dry-run} {--mask=basic}`: refuses to run without `GOOGLE_PLACES_API_KEY`, refuses when `PLACES_DAILY_CAP` reached, logs requests used, writes candidates, prints a summary (new, matched, needs review). Config in `config/services.php` under `google_places` (`api_key`, `daily_cap`, `cache_days`, `enabled`). A service class `GooglePlacesService` wraps the HTTP calls with timeout, retry and field mask, and is faked in tests with `Http::fake`.
Admin UI: new tab "Google discovery" on `/admin/prospects`: run-a-search form (query + locality) with remaining daily budget, candidate table with status filter, side-by-side duplicate view, bulk approve to Prospects, ignore with reason, and a "promote to unclaimed listing" action. Show Google attribution text where Places data is displayed.

---

## 5. Work packages (MUST items)

Conventions: DIQ numbers continue the roadmap block; new items use `DIQ-19xx` ("launch block") to avoid clashing with ROADMAP M11-M15. Backend and frontend lanes touch disjoint paths; dependencies are explicit. Every backend ticket needs PHPUnit on PostgreSQL; every ticket that touches school data needs a cross-school denial test (school A user cannot read or write school B).

### Wave 0: security preconditions (backend lane, start immediately)

**DIQ-1901 Staff-only broadcast channel (M1).** Effort S.
- Contract: private channel `school.{id}.staff` (owner/manager of that school, admin). `school.{id}` kept only for non-sensitive school-wide events or removed. `NotificationCreated` publishes to `user.{id}` always; to the staff channel only when the notification is staff-scoped.
- AC: instructor and learner of school A are denied `school.A.staff`; owner of school B denied; admin allowed; no payload field beyond id/type/title for school-wide broadcast.
- Files: `routes/channels.php`, `app/Events/NotificationCreated.php`, `app/Support/SchoolAccess.php` (helper `isStaffOf`), `config/broadcasting.php` untouched.
- Tests: new `tests/Feature/BroadcastChannelAuthTest.php` with four roles x two schools using `Broadcast::channel` callbacks.

**DIQ-1902 Model scoping audit for 7 models (M2).** Effort M.
- Decide per model: `AppNotification` (scope by `school_id` for school users, but queries are per-user: add the trait plus `withoutGlobalScopes` in admin paths); `OutboundMessage`, `GoogleBusinessConnection` (add trait; admin log uses `withoutGlobalScope('school')` explicitly); `ListingClaim` (system/token-keyed pre-claim, add explicit `forSchool` scope and test, trait only if claims always have a school; document exemption otherwise); `AuditLog` (append-only, admin reads all, owner reads own: keep explicit `SchoolAccess` filtering, do not use the trait, document); `FeaturedPlacement` and `PlatformInvoice` (platform-admin tables with school FK: school owners read through `SchoolAccess`; trait not applied, document and test). Write the decision per model in the PR.
- AC: for each model there is a test where a school A owner cannot list, show or update school B's row through every route that touches it; unauthenticated queue/console paths still work (the trait fails closed, so jobs must be audited: check `NewLeadNotification`, `driveiq:outreach`, `driveiq:ops-reminders`, `driveiq:subscriptions`).
- Files: the 7 models, any controller using them (`AdminMonetizationController`, `NotificationController`, `GoogleBusinessController`, `ListingClaimController`), `tests/Feature/Isolation*Test.php`.
- Depends on: none. Overlaps with DIQ-1901 only in `SchoolAccess.php`; do 1901 first.

**DIQ-1903 Trusted proxies, security headers, deep health (M3, M4, M5).** Effort M.
- `bootstrap/app.php`: `trustProxies(at: env('TRUSTED_PROXIES'))` with forwarded headers; middleware `SecurityHeaders` (HSTS only when `APP_ENV=production` and https, `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`; CSP sent by the proxy for the SPA, API sets a strict `default-src 'none'`).
- `GET /api/healthz` stays static; add `GET /api/health/deep` (db, redis, queue lag via Redis queue length, last scheduler heartbeat key written by a scheduled closure each minute, disk write test) protected by an `X-Health-Key` or internal network, returns 503 on any failure and never leaks versions.
- AC: `request()->ip()` returns the forwarded client IP behind a faked proxy; headers asserted in tests; deep check returns 503 when Redis down (use a mock).
- Files: `bootstrap/app.php`, `app/Http/Middleware/SecurityHeaders.php`, `routes/api.php`, `app/Http/Controllers/Api/HealthController.php`, `routes/console.php`, `config/health.php`.

**DIQ-1904 Production config safety and seed (M6, M7).** Effort S.
- `.env.example` set to pgsql/redis defaults; new `.env.production.example` with required keys listed; `AppServiceProvider` boot assertion (production only): fail boot or log critical if `MAIL_MAILER=log`, `DB_CONNECTION=sqlite`, `APP_DEBUG=true`, `MESSAGING_DRIVER` not set deliberately.
- `ProductionSeeder` (localities, plans from `config/plans.php`, `MarketplaceSetting` defaults) called by `php artisan driveiq:install-production`; `DemoShowcaseSeeder` and `DatabaseSeeder` demo branches throw in production (DemoCommand already refuses).
- AC: running demo seeders with `APP_ENV=production` throws; production seed is idempotent; no `password123` account exists after it.
- Files: `.env.example`, `.env.production.example`, `app/Providers/AppServiceProvider.php`, `database/seeders/ProductionSeeder.php`, `DatabaseSeeder.php`, `routes/console.php`.
- Depends on: none.

### Wave 1: Google seeding (backend) and SEO (frontend), in parallel

**DIQ-1910 Places client and config (backend).** Effort M.
- `GooglePlacesService` (Text Search New with field mask, pagination up to 3 pages, request counter in Redis per day, daily cap, 429/5xx retry, `Http::fake` friendly); `config/services.php` `google_places`.
- AC: refuses without a key; stops at the daily cap; only requests fields from the mask; logs request count.
- Files: `app/Services/GooglePlacesService.php`, `config/services.php`, `.env.example`.
- Tests: `Http::fake` for pages, cap, errors, no key.

**DIQ-1911 Candidate staging, dedupe, `driveiq:google-seed` (backend).** Effort M. Depends on 1910.
- Migration `google_place_candidates` (4.5), `Prospect::SOURCES` + `google_places`, `google_place_id_checked_at`. `GooglePlaceSeeder` service: match order from 4.6 using `pg_trgm` extension (new migration enabling it, guard `CREATE EXTENSION IF NOT EXISTS`) and PostGIS 150 m. Command with `--dry-run`. Audit log row per run.
- AC: re-run creates no duplicates; a candidate whose `place_id` equals an existing school/prospect is `matched_*`; fuzzy hit becomes `needs_review`; do_not_contact and suppressed prospects are never re-created; no candidate ever creates a published school.
- Files: `database/migrations/2026_11_xx_*`, `app/Models/GooglePlaceCandidate.php`, `app/Services/GooglePlaceSeeder.php`, `app/Console/Commands/GoogleSeedCommand.php`, `app/Models/Prospect.php`.
- Tests: dedupe matrix, idempotency, dry run, cap, `School::public()` exclusion (`GET /schools` and sitemap do not list promoted listings).

**DIQ-1912 Admin candidate API and promote action (backend).** Effort M. Depends on 1911.
- Routes (role `admin`): `GET /admin/google-candidates`, `POST /admin/google-candidates/search` (runs a limited query inline, queue for more than N), `POST /admin/google-candidates/{id}/approve` (creates Prospect through `ProspectService`), `POST /admin/google-candidates/{id}/ignore`, `POST /admin/google-candidates/{id}/promote` (Prospect then `createListing()`), `GET /admin/google-candidates/budget`.
- Errors: 403 non-admin, 409 already promoted, 422 daily cap reached, 503 key missing.
- AC: only admin; school/trainer role gets 403; audit rows for approve/ignore/promote.
- Files: `app/Http/Controllers/Api/AdminGoogleCandidateController.php`, `routes/api.php`.

**DIQ-1913 Admin "Google discovery" UI (frontend).** Effort M. Depends on contract of 1912 (can start with mocked responses).
- Tab in `pages/admin/prospects.tsx` or new `pages/admin/google-discovery.tsx`; form, budget meter, candidate table with status filter, duplicate side-by-side, bulk approve, ignore with reason, promote; attribution text; i18n keys en/hi/mr for labels (admin pages are English-only today, English acceptable).
- Files: `frontend/src/pages/admin/google-discovery.tsx`, `frontend/src/App.tsx` (route), `frontend/src/lib/api/*` (hand-typed client until OpenAPI), admin nav.
- AC: typecheck and build pass; empty, loading and error states; non-admin cannot reach route (existing guard).

**DIQ-1914 Per-route SEO meta, sitemap, robots (frontend + small backend).** Effort M. Independent.
- Frontend: `useSeo({title, description, canonical, image, noindex})` hook (or `react-helmet-async`) applied to home, search (canonical without volatile params), school detail (title "School name, Locality | DriveQ", description from profile), locality pages, `/for-schools`, `/for-trainers`, privacy/terms; `noindex` on dashboard, admin, claim, login. Fix existing JSON-LD to share the hook.
- Backend: `GET /sitemap.xml` (published schools, localities, static pages; uses `School::public()`; cached 1 h) and `robots.txt` with `Sitemap:` line served by the web layer; note SPA is Vite, so the sitemap route must be proxied from the same origin (infra WP) or generated at build/deploy by a command `driveiq:sitemap` writing to `frontend/public`.
- AC: unclaimed, draft, suspended listings are absent from the sitemap (test); canonical tags unique per route; `noindex` on portals.
- Files: `frontend/src/hooks/use-seo.ts`, page files, `frontend/index.html`, `frontend/public/robots.txt`, `backend/app/Console/Commands/SitemapCommand.php` or `SitemapController`, `routes/web.php`.
- Tests: backend sitemap exclusion test; frontend typecheck.

**DIQ-1915 Homepage near-me (DIQ-1312, frontend).** Effort S. Independent. Reuse the location store from `features/geo-rules/stores/location-store.ts` and the search page's geolocation consent; one-tap button routing to `/search?nearLat&nearLng&radiusKm=5`, permission-denied fallback to locality picker. Files: `frontend/src/pages/home.tsx`, search hook. AC: consent prompt text, denial handled, i18n en/hi/mr.

### Wave 2: infrastructure and operations (infra lane; backend help where noted)

**DIQ-1920 Production images, proxy/TLS, compose.prod (infra).** DIQ-1301/1302. Files: `backend/Dockerfile`, `frontend/Dockerfile`, `docker-compose.prod.yaml`, `deploy/Caddyfile`. AC: non-root, pinned bases, only proxy exposed, forced cert renewal proven, CSP header set at proxy, `/sitemap.xml` and `/api` routed.

**DIQ-1921 Object storage for documents (backend+infra).** DIQ-1305. `DOCUMENTS_DISK=s3` (DO Spaces), signed URL 5 min intact, private bucket. Tests: existing upload tests with `Storage::fake`, one manual restart-survival check.

**DIQ-1922 Deploy, supervision, backups, monitoring (infra).** DIQ-1306, 1308, 1310, 1311. GitHub Actions deploy job with migration and rollback; supervisor/systemd or compose restart policies for worker and scheduler; failed-job alert; nightly `pg_dump` plus bucket snapshot, retention 14 days, restore drill recorded; Sentry (or equivalent) with release tag; Uptime Kuma on `/api/health/deep`.

**DIQ-1923 Real mailer and outreach mailbox (ops + backend config).** Pick provider (D2); set `MAIL_*`; separate subdomain mailbox for outreach; SPF, DKIM, DMARC; test reset email, invite, lead email, claim email land in inbox (not spam); queue worker running. AC: checklist in PR with screenshots of headers (SPF/DKIM pass).

**DIQ-1924 Seed supply (ops).** Run `driveiq:google-seed` for 15-20 high-density localities, review, promote 50 unclaimed listings, run the claim flow with 3 friendly schools, record funnel in admin acquisition view.

### Wave 3: gate

**DIQ-1930 Launch review (DIQ-1313 extended).** Run full PHPUnit, the isolation suite (1901, 1902), manual two-school script, forced TLS renewal, restore drill, rollback drill, CSP console check, DPDP privacy mailbox and data request flow test, verify `School::public()` exposes only published listings, verify no demo accounts, verify the Places spend cap and Cloud budget alert exist. Sign-off recorded in the PR.

### Dependency graph

```
1901 -> 1902 (SchoolAccess overlap)        1904 independent        1903 independent
1910 -> 1911 -> 1912 -> (1913 uses contract)       1914 independent       1915 independent
1920 -> 1922 ; 1921, 1923 independent ; 1924 needs 1911, 1912, 1913, 1923
1930 needs everything above
```

### Parallel lanes

- backend-engineer: 1901, 1902, 1903, 1904, 1910, 1911, 1912, backend half of 1914, 1921.
- frontend-engineer: 1913 (after contract in 1912 is agreed), 1914 frontend half, 1915. Frontend files are `frontend/src/**` only; backend files `backend/**` only, so no overlap. Shared touch point: `routes/web.php`/`robots.txt` for 1914, assigned to backend.
- infra/ops: 1920, 1922, 1923, 1924, supported by backend on 1921.

SHOULD items in 2.2 become their own tickets after the pilot starts; none block 1930.

---

## 6. Test requirements summary

- Cross-school denial for every WP that touches a school row (1901, 1902, 1921), covering owner, manager, instructor, learner of another school.
- Google seeding: `Http::fake` only, never a live call in CI; assert cap, idempotency, dedupe, no publish, audit rows, do_not_contact respected.
- Existing suites (311) must stay green; Pint lint; frontend typecheck/build.
- Production assertions: boot guard test with `app()->detectEnvironment`, demo seeder refusal.

---

## 7. Docs to update when the work lands

`CLAUDE.md` (phase line, Google Places and sitemap, health route), `docs/SYSTEM-FLOW.md` (new candidate flow in 3.4, broadcast channel change in 3.7), `docs/DEPLOY.md` (Laravel 13, worker, scheduler, health, backups), `docs/PROJECT-PLAN.md` s2, `docs/DPDP-Compliance.md` (Places terms conclusion, prospect retention), `docs/ROADMAP.md` (map DIQ-19xx to M11).

---

## 8. Decisions needed from the owner

1. **Google Places key and billing (D1):** create a Cloud project with billing, restrict the key by API and IP, set a monthly budget alert and quota cap. Approve the hybrid approach in 4.2, and confirm in the Maps Platform terms what may be stored (4.3). If you want phone/website stored in bulk, get a written/legal read first.
2. **Mail provider (D2):** transactional (Postmark, SES, Resend or similar) and the outreach mailbox on its own subdomain; confirm `driveq.in` DNS access.
3. **WhatsApp/SMS provider (D3):** MSG91, Gupshup, Twilio, other; or launch the pilot on email-only lead alerts. This decides whether DIQ-1401-1403 move into MUST. DLT registration for SMS takes days to weeks, start now if SMS is wanted.
4. **Hosting (D4):** one DigitalOcean droplet plus managed Postgres/Redis and Spaces (matches the plan), or a single-box compose. Region (Bangalore/Mumbai), backup budget, who is on call.
5. **Payments (D5):** stay with manual UPI/bank and GST invoices for the pilot (recommended) or build Razorpay first (M13, about 11 days).
6. **Outreach legal basis (D6):** confirm that cold email to businesses discovered via Google or the web is acceptable for DriveQ under your DPDP reading, and the lawful sourcing of email addresses; confirm no WhatsApp/SMS to prospects without opt-in.
7. **Pilot scope (D7):** target number of schools, localities to open first, and the date for go/no-go.
8. **Housekeeping (D8):** commit or discard the untracked docs (`ROADMAP.md`, `SYSTEM-FLOW.md`, xlsx/docx) and the deleted `docs/files.zip`.
