---
name: backend-testing
description: Write and run PHPUnit feature tests for the DriveQ Laravel backend on PostgreSQL/PostGIS, including multi-school isolation, plan gating, mail/queue and messaging fakes. Use when adding or fixing backend tests or when a test run fails to connect.
---

# Backend testing (DriveQ)

## Running
- Needs PostgreSQL+PostGIS reachable at 127.0.0.1:5432, db `driveiq_test`, user/pass `driveiq` (`phpunit.xml` forces `DB_CONNECTION=pgsql`, `DB_DATABASE=driveiq_test`; `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `MESSAGING_DRIVER=null`, `CACHE_STORE=array`).
- Docker: `make up-infra` then `make test` / `make test-filter FILTER=LeadLifecycleTest` (creates `driveiq_test` via `make test-db`). Local: `cd backend && php artisan test --filter=Name`.
- Without a database every Feature test errors with `SQLSTATE[08006] connection refused`; that is environmental, not a code failure. CI (`.github/workflows/ci.yml`) uses `postgis/postgis:16-3.4`, runs `vendor/bin/pint --test`, `migrate --force`, `php artisan test`.

## Writing a test
1. File in `backend/tests/Feature/<Topic>Test.php`, `namespace Tests\Feature`, extend `Tests\TestCase`, `use RefreshDatabase;`, docblock citing the `DIQ-nnn`.
2. Fixtures: only `User::factory()` exists. Build the rest explicitly (see `LeadLifecycleTest::setUp`):
   ```php
   $owner = User::factory()->create(['role' => 'school']);
   $school = School::create(['name' => 'X', 'slug' => 'x', 'user_id' => $owner->id]); // starts a trial -> premium features
   $owner->update(['school_id' => $school->id]);
   Sanctum::actingAs($owner->refresh());
   ```
   Add `SchoolAdmin::create([... 'role' => 'owner', 'status' => 'active'])` when owner-vs-manager matters. Create scoped rows with `Model::withoutGlobalScope('school')->create([...])` or the scope will hide them from you.
3. Second tenant: `$this->otherSchoolUser()` in `tests/TestCase.php` (a made-up `school_id` breaks the FK).
4. Always assert isolation: other school's user gets 403/404 and the other school's ids are absent from lists (`tests/Feature/AuthorizationTest.php`, `CrossSchoolIdsTest.php`, `SecurityRegressionTest.php`).
5. Plan gating: modify the school's `Subscription` (`Subscription::withoutGlobalScope('school')->where('school_id', ...)`) to a lower plan, expect `402` with `code=plan_required` on writes and 200 on reads (`PlanGatingTest`, `ListingTiersTest`).
6. Mail/notifications: `Mail::fake()` / `Notification::fake()`; queued listeners run synchronously (`sync` queue). Time: `$this->travel(...)`/`Carbon::setTestNow`.
7. WhatsApp: bind the fake: `$this->app->instance(MessageSender::class, $fake = new FakeMessageSender);` then assert on `$fake->sent` / `$fake->texts()` (`WhatsAppLeadAlertsTest`).
8. Commands: `$this->artisan('driveiq:lead-reminders')->assertExitCode(0)` (`LeadRemindersTest`, `OpsRemindersTest`, `RetentionCommandTest`).
9. Public spam endpoints: send `formStartedAt` old enough and empty `website` or hit the bot rule intentionally (`AntiSpamTest`); rate limiter tests clear with `RateLimiter::clear`.
10. Geo: tests insert lat/lng and rely on the DB trigger to fill `location` (`GeoSearchTest`, `SearchPerformanceTest`).
11. Finish with `vendor/bin/pint` and the targeted test; run the full suite before a PR.

## Security test templates (required for every new endpoint)
Put them next to the feature test or in `SecurityRegressionTest`. Use two real schools (`otherSchoolUser()` or two `school()` helpers like `CrossSchoolIdsTest`).

```php
// 1. Cross-school denial (BOLA): B's id, A's token -> 403 or 404, never 200, and no data in the body
Sanctum::actingAs($a['owner']->refresh());
$this->getJson("/api/vehicles/{$b['vehicle']->id}/documents")->assertStatus(403);   // or assertNotFound
$this->getJson("/api/schools/{$b['school']->id}/vehicles")->assertForbidden();
$this->patchJson("/api/vehicles/{$b['vehicle']->id}", ['status' => 'retired'])->assertForbidden();
$this->assertDatabaseHas('vehicles', ['id' => $b['vehicle']->id, 'status' => 'active']); // unchanged

// 2. Foreign id in the body is refused (SchoolScopedIds)
$this->postJson("/api/schools/{$a['school']->id}/schedules", [... 'instructorId' => $b['instructor']->id])->assertStatus(422);

// 3. Role denial: wrong role -> 403, anonymous -> 401
Sanctum::actingAs(User::factory()->create(['role' => 'learner']));
$this->getJson($url)->assertForbidden();
auth()->forgetGuards(); $this->getJson($url)->assertUnauthorized();

// 4. Owner vs manager: manager (SchoolAdmin role=manager) gets 403 on ownerOnly actions
// 5. Mass assignment: extra keys are ignored
$this->postJson($url, $valid + ['role' => 'admin', 'schoolId' => $b['school']->id, 'verified' => true]);
$this->assertDatabaseMissing('users', ['role' => 'admin']);
// 6. Terminated / deactivated user: token refused (401) or 403 after the status change
// 7. Public endpoints: unpublished school (draft/suspended/unclaimed) never appears in the response
// 8. Throttle: hit the route limit+1 times from one IP -> 429; RateLimiter::clear() in setUp
```
- Webhook tests: bad key -> 403, missing key config -> 404, duplicate delivery -> one side effect.
- For messaging endpoints use `FakeMessageSender` and assert nothing is sent without consent and that a second request to the same recipient is rate limited.
- Never assert on a 500; if a malformed input (array instead of string, non-UUID id) reaches a query, add the validation and a 422/404 test.
