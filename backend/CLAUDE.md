# backend/ — DriveQ Laravel API (AI-assistant guide)

Laravel 13 / PHP 8.3 / PostgreSQL 16 + PostGIS / Sanctum / Redis queue. JSON API only (`routes/api.php`, prefix `/api`).
Status of record is `docs/PROJECT-PLAN.md` section 2 (M1-M10 shipped, next M11 launch readiness). Everything below was verified against code.
User-facing brand is **DriveQ**; code identifiers, `driveiq:*` commands and `driveiq_*` keys keep the old name.

## 1. Architecture map

| Path | What lives there |
|------|------------------|
| `routes/api.php` | All routes. Groups: public, `auth:sanctum`, then `role:` + optional `plan.features` sub-groups |
| `routes/console.php` | `driveiq:create-admin` + the whole scheduler (see section 6) |
| `routes/channels.php` | Private `user.{id}` / `school.{id}` broadcast auth |
| `app/Http/Controllers/Api/*` | 38 controllers (thin-to-medium; some hold query logic: Learner, Instructor, Schedule) |
| `app/Http/Requests/Api/*` | `BaseFormRequest` + FormRequests with `toSnakeCase()`; `Concerns/GuardsAgainstBots` (hidden `website` honeypot + `formStartedAt` min-fill time; send `formStartedAt`/empty `website` from public forms) |
| `app/Http/Resources/*` | Only 6 resources (Inquiry, Locality, Notification, Package, Review, School); other controllers build arrays inline |
| `app/Http/Middleware` | `RoleMiddleware` (`role:`), `EnforcePlanFeatures` (`plan.features`) - aliases in `bootstrap/app.php` |
| `app/Support` | `SchoolAccess` (authorization), `SchoolScopedIds` (FK validation rules), `Entitlements` (plan features), `DocumentStorage`, `Phone` |
| `app/Models` (45) + `Traits/BelongsToSchool` | Eloquent; `School` itself is not scoped |
| `app/Services` | Domain logic: School (search/ranking SQL), Subscription, Billing, Payment, Schedule, Progress, Analytics, Stats, Notification, Messaging, ReviewEligibility, LeadResponseStats, Prospect, ListingClaim, ListingOnboarding, Outreach(+Suppression), GoogleBusiness |
| `app/Messaging` | Provider-neutral WhatsApp/SMS: `Messenger` -> `MessageSender` driver (`Drivers/LogSender`, `NullSender`) |
| `app/Notifications`, `app/Listeners`, `app/Events` | Lead/billing/invite mails; `InquiryCreated` -> 2 queued listeners; `NotificationCreated` (broadcast) |
| `app/Console/Commands` | `driveiq:{retention,lead-reminders,subscriptions,ops-reminders,outreach,demo}` |
| `config/` | `plans.php` (entitlements + route->feature map), `geo.php` (ranking weights), `messaging.php` (templates), `billing.php`, `retention.php`, `outreach*.php` |
| `database/migrations` | 64 migrations; PostGIS + triggers + CHECK constraints via raw `DB::statement` |
| `tests/Feature` (65 files, ~311 tests), `tests/Support/FakeMessageSender` | PHPUnit on PostgreSQL |

There is **no `app/Jobs` and no `app/Policies`**: async work = queued Listeners/Notifications + scheduled commands; authorization = route middleware + `SchoolAccess` + `BelongsToSchool`.

## 2. Auth, roles, school isolation (read before touching any endpoint)

- Sanctum bearer tokens (expiry `SANCTUM_EXPIRATION`, default 7 days). `AppServiceProvider` rejects tokens of users with `deactivated_at`. No OTP/mobile login.
- `users.role` is one of `admin | school | instructor | learner` (register accepts only `school`, `learner`; admins via `php artisan driveiq:create-admin`). Owner vs manager is **not** a role: it is `school_admins.role` (`owner|manager`, status active/invited); `SchoolAccess::isOwner()` also honours legacy `schools.user_id`.
- Isolation is 3 layers; use all that apply:
  1. Route middleware: `role:...` then `plan.features`.
  2. `BelongsToSchool` global scope on school-owned models: admin = unscoped; user with `school_id` = filtered; authenticated user without school = `1=0` (fail closed); **unauthenticated, console, queue = NOT filtered** (scope keys off `auth()->check()`). Public endpoints and commands must filter explicitly.
  3. Controller guard: `if ($deny = $this->access()->school($request, $schoolId)) return $deny;` (404 unknown school, 403 otherwise). Variants: `->learner()`, `->instructor()`, `->schedule()`, `school(..., ownerOnly: true)`.
- When the id comes from the URL/body of a *different* resource, fetch with `Model::withoutGlobalScope('school')->find($id)`, then authorize against `$model->school_id` (pattern in `VehicleController::update`). Never trust a client `school_id`: take it from the route after `access()->school()` or from `$request->user()`.
- Foreign IDs in request bodies (learner, package, instructor, vehicle, session) must validate with `SchoolScopedIds::*($schoolId)`.
- Models with `school_id` but **no** `BelongsToSchool`: `AppNotification, AuditLog, FeaturedPlacement, OutboundMessage, GoogleBusinessConnection, ListingClaim, PlatformInvoice` (+`User`, `School`). Scope them by hand.
- Public listing visibility: always `School::public()` (`listing_status='published'`). `unclaimed|draft|suspended` must never leak. Admin-only override: `includeHidden`.
- Audit: `AuditLog::log($action, $modelType, $id, $old, $new, $schoolId?)` on every sensitive mutation. Table is append-only (Eloquent guard + PG trigger `audit_logs_append_only`; migration `2026_09_29_000003`).

## 3. Conventions

- JSON in/out is **camelCase** (`schoolId`, `vehicleType`); DB is snake_case. FormRequests map via `camelToSnake()`/`toSnakeCase()`; inline controllers map by hand.
- Responses are plain JSON (arrays/objects, `X-Total-Count` header for school lists), **not** `{data, meta}` as root CLAUDE.md says. Errors: `{message}`; validation 422 is `{errors}` from `BaseFormRequest` but `{message, errors}` from inline `$request->validate()` (handler in `bootstrap/app.php`) - inconsistent, keep existing endpoint shapes stable for the frontend.
- Plan gating: add the route URI (`api/...`, as `route()->uri()` reports it) to `config/plans.php` `routes` => feature. Writes 402 `plan_required`; reads stay open unless feature is in `gate_reads`. Admin is never gated; `PLANS_ENFORCE=false` disables.
- Rate limiters (named, in `AppServiceProvider`): `auth, inquiries, public-forms, consents, public-lookups`. Every new unauthenticated mutation/lookup needs one.
- Style: Pint (`vendor/bin/pint`, CI runs `--test`), docblocks cite ticket ids `DIQ-nnn`. Commit style `DIQ-nnnn: ...`.
- Time-sensitive/background work: scheduled command + `withoutOverlapping()` + idempotency columns (`reminder_sent_at`, `OutboundMessage` unique slot), never send inside the request.

## 4. Domain flows (where to look)

- **Lead engine**: `POST /api/inquiries` (throttle `inquiries`, bot guard, school must be published) -> `Inquiry` (status `pending`) -> `InquiryCreated` -> `SendInquiryCreatedNotification` (in-app + mail + WhatsApp to staff per `SchoolSetting` prefs) and `SendInquiryConfirmation` (learner mail with one-time review link + optional WhatsApp). Status changes in `InquiryController::update` write `LeadStatusHistory`, call `markResponded()`; `driveiq:lead-reminders` (15 min) nudges once; `LeadResponseStats` hourly badges. Convert = `LearnerController::convertInquiry` (gated by `learners` feature; PATCH to `converted` is rejected when the school has that feature).
- **Geo search**: `SchoolService::search` -> `applyGeo` (PostGIS `ST_DWithin`/`ST_Distance` on `schools.location`, a geography column kept in sync by trigger from lat/lng; never write it) + `withPlanMeta` (joins active plan + live `featured_placements`). Score weights `config/geo.php` (distance .40, rating .25, reviews .12, verified .08, premium .15). Premium/enterprise + live campaigns sort first ("top placement"). Docs: `docs/PHASE-1-GEO-RANKING.md`.
- **Monetization**: `Plan`/`Subscription` (30-day trial on `premium` features via `School::created` hook -> `SubscriptionService::startTrial`), `Entitlements`, manual GST `PlatformInvoice` (`BillingService`, admin records payment), `driveiq:subscriptions` daily. Razorpay not integrated.
- **Operations (M7)**: learners, instructors (trainers), vehicles, schedules, leave, progress, driving tests, payments (`PaymentService`); `ScheduleService` clash detection; `driveiq:ops-reminders` hourly.
- **Messaging**: `Messenger::send` validates E.164 (`Phone`), claims `(template, subject, to_hash)` slot via insertOrIgnore (dedupe), renders `config/messaging.php` template, calls driver, logs masked row in `outbound_messages`. Notifications use channel `whatsapp` (`toWhatsApp(): Message`); recipients need `whatsapp_opt_in_at` (consent). Only `log`/`null` drivers exist.
- **Acquisition (M9)**: `Prospect` CRM (+CSV import) -> admin creates `unclaimed` listing -> `ListingClaim` token + SMS one-time code -> owner verifies email -> `draft` auto-publishes in `School::saving` when `publishBlockers()==[]`. Outreach: `OutreachService`/`OutreachEmail` on separate `outreach` mailer, suppression list, daily cap + send hours; Google Ads webhook (`google_key` hash_equals); Google Business OAuth.
- **DPDP**: `Consent`, `DataSubjectRequest`, `ContactMessage`, `driveiq:retention` (report-only unless `--execute`/`RETENTION_EXECUTE=true`; honours `RETENTION_LEGAL_HOLD_SCHOOLS`).
- **Uploads**: private disk `DOCUMENTS_DISK`; server picks path `schools/{school}/{kind}/{owner}/uuid.ext`; download only via 5-min signed URL (`GET /documents/{kind}/{id}/link` -> `/file`). Rule: `DocumentStorage::FILE_RULE` (5 MB, pdf/jpg/png/webp).

## 5. Commands

```bash
# stack (repo root)           make up | make migrate | make seed | make test | make test-filter FILTER=PaymentTest
cd backend && composer install
php artisan migrate --force   # needs PostgreSQL + PostGIS (migrations use postgis, triggers, geography)
php artisan test [--filter=X] # needs DB driveiq_test (make test-db creates it)
vendor/bin/pint               # lint (CI: --test)
php artisan route:list --path=api
php artisan driveiq:demo [--fresh]   # showcase data; refuses production
php artisan queue:work --tries=3     # required: emails/confirmations are queued
php artisan schedule:work            # or the scheduler container in docker-compose
```

## 6. Scheduler (`routes/console.php`)
`sanctum:prune-expired` daily; `driveiq:retention` 02:30; `driveiq:lead-reminders` /15m; badges hourly; `driveiq:subscriptions` 03:15; `driveiq:ops-reminders` hourly; `driveiq:outreach` /15m. All `withoutOverlapping()`.

## 7. Env vars that matter
`DB_*` (pgsql only; `.env.example` now defaults to pgsql). With `APP_ENV=production` the app refuses to boot (`App\Support\ProductionGuard`) on debug, missing key, log/array mailer, `MESSAGING_DRIVER` unset/log while `MESSAGING_REQUIRE_REAL=true`, wildcard CORS (`CORS_ALLOWED_ORIGINS`), non-pgsql DB, short `GOOGLE_ADS_WEBHOOK_KEY`; `DOCUMENTS_ENCRYPT`, `RETENTION_MONTHS_*`, `QUEUE_CONNECTION`, `FRONTEND_URL` (CORS + email links), `SANCTUM_EXPIRATION`, `DOCUMENTS_DISK`, `MAIL_*`, `MESSAGING_DRIVER` (`log` default) + `MSG_TPL_*`/`DLT_TPL_*`, `PLANS_ENFORCE`, `PLAN_TRIAL_DAYS`, `BILLING_*`, `OUTREACH_*` (+`OUTREACH_MAILER=outreach`), `GOOGLE_ADS_WEBHOOK_KEY`, `GOOGLE_OAUTH_*`, `RETENTION_*`, `BROADCAST_CONNECTION` (`log`; Reverb not installed).

## 8. Gotchas
- `phpunit.xml` forces `DB_CONNECTION=pgsql`, `DB_DATABASE=driveiq_test`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `MESSAGING_DRIVER=null`; tests need a live Postgres (127.0.0.1:5432, user/pass `driveiq`). No DB = every feature test errors.
- Only `UserFactory` exists. Tests create `School::create([...])` + `$user->update(['school_id'=>...])` by hand. A fake `school_id` violates the FK; use `TestCase::otherSchoolUser()`.
- `DatabaseSeeder` uses `WithoutModelEvents`, so trials/profile completeness are applied manually there. Seeded accounts use `password123` - never seed production.
- Route-uri keys in `config/plans.php` must match exactly or the route is silently ungated.
- `auth()` in the global scope uses the default guard; it only works on routes behind `auth:sanctum`. Optional-auth public routes use `$request->user('sanctum')`.
- `docker-compose.yaml` backend container runs `composer install && migrate --force` on every start; a separate `worker` (queue:work) and `scheduler` container exist.

## 9. Known risks / tech debt (ranked)
1. **Broadcast leak (latent)**: `NotificationCreated` also broadcasts to `school.{id}` and `channels.php` lets *any* user with that `school_id` (instructors, learners) subscribe -> lead name/phone visible. Harmless while `BROADCAST_CONNECTION=log`; fix (staff-only channel) before enabling Reverb (DIQ-1404).
2. Production hardening still open (M11): trusted proxies, security headers, backups, deep health check (`/api/healthz` is static), Spaces, deploy job. Mail/messaging/outreach all default to log; no real WhatsApp/SMS provider.
3. Fail-open surfaces by design: global scope ignores console/queue/unauthenticated contexts; each such path must filter by hand (reviewed paths are fine, new code is the risk).
4. Inconsistent response/error shapes and only 6 API Resources; no OpenAPI spec (client types hand-written).
5. Controllers carry logic (Learner 713 lines, Instructor 643, Schedule 448); no Policies/Gates, so authorization is convention-enforced - missing `access()` call = hole. Cover with a cross-school test.
6. `DEPLOY.md` says Laravel 11 (actual 13) and Razorpay "optional" (not integrated); `ARCHITECTURE.md`, `BACKEND-ASSESSMENT.md`, audit docx and `PHASE-A-IMPLEMENTATION.md` describe a pre-M1 state (trait absent, Haversine, no tests) and are historical only.
7. Retention does not cover training records, accounts, payments; OTP login, lead auto-assignment, bounce handling not built.

## 10. Security checklist (details: `docs/security/BACKEND-SECURITY-AUDIT.md`, skill `backend-security-review`)
- Every action starts with a `SchoolAccess` call; `BelongsToSchool` is not a substitute (off for anonymous, queue, console). The 7 unscoped models need `school_id`/`user_id` in every query.
- Build model arrays key by key; never `$request->all()`; privileged columns (`role, school_id, status, verified*, listing_status, rating, approved`) only via `forceFill` in code.
- Public queries use `School::public()` (reviews, packages, stats too), are paginated and `max:`-bounded; stored URLs are `url`+https.
- Unauthenticated routes: named limiter; messaging a third party also limits per recipient. Webhooks: `hash_equals`/HMAC, 404 when unset, idempotent.
- No SQL interpolation (`?` bindings, whitelist sort fields); no raw tokens/OTP/PII in DB, logs or audit payloads; emails lowercased.
- Offboarding revokes access (tokens, `status=active` checks); money transitions lock the row and are terminal; sensitive writes call `AuditLog::log(..., $schoolId)`.
- Tests: cross-school 403/404, wrong role 403, anonymous 401, mass-assignment, throttle 429. Run `composer audit --locked` before merging dependency or framework changes.
