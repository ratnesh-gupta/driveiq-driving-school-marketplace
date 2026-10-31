---
name: backend-security-review
description: Repeatable security review checklist for any PR or change touching backend/ in DriveQ (AuthZ/tenant isolation, mass assignment, rate limits, webhooks, uploads, messaging consent, DPDP, audit log, dependencies, config). Use when reviewing or self-reviewing backend changes before merge.
---

# Backend security review (DriveQ)

Baseline and open findings: `docs/security/BACKEND-SECURITY-AUDIT.md` (BSEC-xx). The sibling for the SPA is `frontend-security-review`. Authorization is convention-enforced (no Policies), so a missing `access()` call is a hole: review for absence, not just presence.

## Quick scans (repo root)
```bash
git diff main -- backend/routes backend/app | grep -nE "Route::(post|put|patch|delete)"                     # each needs role + throttle (if public)
git diff main -- backend/app | grep -nE "whereRaw|selectRaw|orderByRaw|DB::(raw|select|statement)|havingRaw"  # constants + ? bindings only
git diff main -- backend/app | grep -nE "\\\$request->(all|input)\(\)|validated\(\)\)|->fill\(|forceFill|create\("   # key-by-key, no privileged columns
git diff main -- backend/app | grep -nE "withoutGlobalScope|Model::all|::find\(|::findOrFail\("                # followed by access()->... on school_id?
git diff main -- backend/app | grep -nE "Http::|file_get_contents|redirect\(|->away\(|Storage::|unserialize|exec\("
git diff main -- backend/app | grep -nE "Log::|logger\(|dd\(|dump\(|var_dump|print_r|env\("                      # no PII/secret logging, no env() outside config
cd backend && composer audit --locked && vendor/bin/pint --test && php artisan test
php artisan route:list --path=api --json | php -r '$r=json_decode(stream_get_contents(STDIN),true); foreach($r as $x) if(!str_contains(implode(",",$x["middleware"]),"Authenticate")) echo $x["method"]." ".$x["uri"].PHP_EOL;'   # public surface diff
```

## Checklist (answer OK / N/A / finding with file:line, severity, fix)
1. **Routes**: correct `role:` group; `plan.features` + `config/plans.php` entry for paid features (key equals `route()->uri()`); `whereNumber` on ids; unauthenticated route has a named limiter and `GuardsAgainstBots` for forms.
2. **Tenant isolation / BOLA**: every action calls `SchoolAccess` (school/learner/instructor/schedule) before reading or writing; child ids fetched `withoutGlobalScope('school')` then checked against `school_id`; body ids validated with `SchoolScopedIds`; `school_id` never from the client (except admin); lists filter `school_id` explicitly when the model is one of the 7 unscoped ones (`AppNotification, AuditLog, FeaturedPlacement, OutboundMessage, GoogleBusinessConnection, ListingClaim, PlatformInvoice`) or when the code runs unauthenticated, in a queue or in console.
3. **Owner vs manager** and role pairs: destructive/billing/team actions `ownerOnly`; removed, terminated or deactivated people are locked out (tokens revoked, status checked in access helpers).
4. **Mass assignment**: explicit key map; no privileged columns (`role, school_id, user_id, status, verified*, listing_status, approved, rating, review_count`) from input; new `$fillable` reviewed.
5. **Public exposure**: `School::public()` on every public school/review/package/trainer/stat query; pagination and `max` limits; resources do not add internals (`email`, scores, hidden flags, ids of other tenants); 404 vs 403 does not enumerate.
6. **Injection**: no variable interpolation into SQL, `DB::statement`, file paths, shell or regex; sort/filter fields whitelisted; LIKE escaped; PostGIS inputs are validated numerics bound with `?`.
7. **Input**: every string `max:`; URLs `url`+https (+ host allowlist); numbers bounded; arrays shape-checked (`array:keys`); emails lowercased; no request-controlled class names, view names or config keys.
8. **Auth flows**: tokens/codes high-entropy, stored hashed, expiring, single use, attempt-limited; reset/invite/claim revoke tokens; no user enumeration in new responses; no secrets in URLs of authenticated calls.
9. **Rate limits**: right named limiter, keyed by recipient (phone/email hash) as well as IP where a third party is messaged; trusted proxies assumed configured (BSEC-02).
10. **Webhooks / outbound HTTP**: `hash_equals` or HMAC over the raw body with timestamp window; fail closed when secret unset; idempotency key; constant hosts only (no SSRF); timeouts set; no redirects followed to user URLs; redirects built from `config('app.frontend_url')`.
11. **Uploads / downloads**: `DocumentStorage::FILE_RULE`, server-chosen path, private disk, signed short TTL, `nosniff`, quota; delete path exists; access to ID documents audited.
12. **Messaging / DPDP**: WhatsApp only with recorded consent of the number's owner (`whatsapp_opt_in_at` + `Consent` row), never inline in the request (queued listener/notification), masked storage, user text sanitised in templates; new PII table has retention + data-subject export/erase handling; consent withdrawal respected.
13. **Money**: server-side prices, state machine with row lock (`lockForUpdate`) and terminal states, idempotent record-payment/webhook, GST fields computed in code, audit row per transition.
14. **Audit log**: sensitive mutation writes `AuditLog::log(..., $schoolId)` with no raw PII; nothing updates/deletes/truncates `audit_logs`; admin-on-school actions pass the school id.
15. **Errors and logging**: no exception text, SQL or stack in responses; unknown id -> 404 JSON; no PII, tokens, OTP or message bodies in logs (`LogSender` masks); no `dd/dump`; `env()` only in `config/*`.
16. **Config / deploy**: new env vars in `.env.example` with safe defaults (`APP_DEBUG=false` for production examples, drivers `log`/`null` never for prod); boot guard where a default would be unsafe; CORS/Sanctum config unchanged unless justified.
17. **Dependencies**: `composer audit --locked` clean of high/critical; new packages justified and maintained; no abandoned or install-script surprises.
18. **Tests**: cross-school denial, wrong-role, anonymous, mass-assignment, throttle and (if public) unpublished-school tests exist (templates in `backend-testing`); migrations follow `backend-migration` security checklist.

Report format: table of findings (ID, severity Critical/High/Medium/Low, file:line, scenario in words, fix, effort S/M/L) plus a "checked OK" list. No working exploit payloads. Hand API shape changes to `frontend-engineer`; cross-domain, billing or consent design questions to `architect`.
