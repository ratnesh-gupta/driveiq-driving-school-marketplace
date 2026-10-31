---
name: backend-new-endpoint
description: Add or change a Laravel API endpoint in DriveQ backend/ with correct school isolation, role middleware, plan gating, validation, audit logging and a cross-school test. Use for any new route in backend/routes/api.php.
---

# Add a backend endpoint (DriveQ)

Reference implementations: `app/Http/Controllers/Api/VehicleController.php` (school-scoped CRUD, inline validation), `InquiryController.php` (FormRequest + Resource), `LeadNoteController.php` (nested resource).

1. **Pick the route group** in `backend/routes/api.php`:
   - Public read/write: top of file; unauthenticated mutations MUST have a named limiter (`throttle:inquiries|public-forms|public-lookups|auth|consents`, defined in `AppServiceProvider::configureRateLimiting`).
   - Authenticated: inside `Route::middleware('auth:sanctum')`, then the sub-group by role: `role:admin`, `role:school,admin` + `plan.features` (school ops), `role:instructor,school,admin` + `plan.features`, `role:learner,...`.
   - Use `->whereNumber('id')` on id params. Paths are camelCase-JSON, snake_case DB.
2. **Plan gating**: if the feature is paid, add `'api/<route uri>' => '<feature>'` to `config/plans.php` `routes` (key must equal `route()->uri()`, e.g. `api/schools/{id}/vehicles`). Put the route in a group that includes `plan.features`. New feature names also go into `features` per plan.
3. **Controller** in `app/Http/Controllers/Api/` (extends `Controller`, which gives `$this->access()`):
   - School-id routes: first line `if ($deny = $this->access()->school($request, $schoolId)) { return $deny; }` (add `ownerOnly: true` for owner-only actions; `allowInstructor: true` where instructors may read).
   - Id-of-child routes (`/vehicles/{id}`): `Model::withoutGlobalScope('school')->find($id)` -> 404 `{message}` -> `access()->school($request, (int) $model->school_id)`. Learner/instructor/schedule records: `access()->learner()/instructor()/schedule()`.
   - Never take `school_id` from the body; set it from the route/user. Foreign ids in the body: `SchoolScopedIds::learner($schoolId)` etc. in the `exists` rule.
4. **Validation**: `$request->validate([...])` with camelCase keys, or a `BaseFormRequest` subclass in `app/Http/Requests/Api` with `toSnakeCase()`. Public forms: `use GuardsAgainstBots` + `...$this->botRules()`.
5. **Persist**: create with explicit snake_case array. After sensitive mutations call `AuditLog::log('create|update|delete', 'Model', $id, $old, $new[, $schoolId])` (pass `$schoolId` when an admin acts on a school).
6. **Respond** with `response()->json($payload, 201|200)`; match the neighbouring endpoint's shape (plain object/array, no `{data}` wrapper). Use a `Resource` only if one exists for that model. Timestamps via `->toISOString()`.
7. **Public listings**: any query that returns schools to the public must use `School::public()`.
8. **Side effects** (emails, WhatsApp, in-app) go through events/listeners/queued notifications, not inline; see skill `backend-lead-and-notifications`.
9. **Test** (skill `backend-testing`): happy path, 401 anonymous, 403 wrong role, **403/404 for a user of another school**, 402 when plan lacks feature, 422 validation.
10. Run `vendor/bin/pint`, `php artisan test --filter=<YourTest>`, and `php artisan route:list --path=api/<x>`. Update the frontend client types (`frontend/src/...`) only via the frontend owner; they are hand-written (no OpenAPI yet).

## Security checklist (from `docs/security/BACKEND-SECURITY-AUDIT.md`; each item is a past or open finding)
- **SchoolAccess in every action**: the first statement of any action that touches school data is `$this->access()->school|learner|instructor|schedule(...)`. Relying on `BelongsToSchool` alone is not enough (it is off for anonymous, queue and console). `allowInstructor: true` only on routes that really sit in an instructor group (dead flags become silent privilege widening).
- **Role group matches intent**: owner-only actions use `ownerOnly: true` (billing, team, Google, deletes, audit trail); managers are not owners. A removed or terminated person must lose access: revoke tokens, deactivate or detach, and make the access helper check `status = active`.
- **`$fillable` / mass assignment**: build the array key by key (camelCase map). Never `create/fill/update($request->all())` or `validated()` into a model that has `role`, `school_id`, `user_id`, `status`, `*_verified`, `listing_status`, `rating`, `approved`. Privileged columns are set with `forceFill` in code, never from input. New model: `$fillable` excludes anything a client must not set.
- **Public data**: any public query joins `School::public()` (reviews, packages, trainers, stats included); lists are paginated (max 100) and exclude inactive rows.
- **Throttle choice**: unauthenticated mutation -> `public-forms` (5/min) or `inquiries`; token lookup -> `public-lookups`; login/reset -> `auth`; public GET lists -> a read limiter; webhooks -> own limiter plus signature. Uploads and imports get their own `throttle:N,1`. Key limiters by recipient (phone/email hash) as well as IP when the endpoint messages a third party.
- **No raw SQL interpolation**: `whereRaw/selectRaw/orderByRaw` take constants only; every value is a `?` binding. Sorting by a request field uses a whitelist map, never the raw string. LIKE input goes through `addcslashes`.
- **Webhooks**: shared secret compared with `hash_equals` (or HMAC of the raw body, `hash_hmac('sha256', ...)`, with a timestamp window), unset secret = 404, idempotent via a unique key and `insertOrIgnore`, own limiter, no PII in logs.
- **Outbound messaging**: never send WhatsApp/SMS/email to an address only a visitor typed unless it is rate limited per recipient; WhatsApp needs recorded consent of the number's owner (`whatsapp_opt_in_at`); template variables from users are stripped of URLs.
- **Free text and URLs**: every string has `max:`; stored URLs are `url` + https (+ host allowlist); files use `DocumentStorage::FILE_RULE` and server-chosen paths.
- **Mutations that matter** write `AuditLog` with the school id (`AuditLog::log(..., $schoolId)`), without raw PII in old/new values (no phone, no document numbers).
- **Errors**: 404 for unknown id, 403 for another tenant; one message for "not found" vs "not yours" where enumeration matters; never return exception text.
- **Email**: normalise to lowercase before storing or comparing.
