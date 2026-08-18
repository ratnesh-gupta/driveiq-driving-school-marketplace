# Phase 0: MVP Foundation Stabilization

**Goal:** Make backend production-safe with proper authorization enforcement  
**Timeline:** 3-4 weeks  
**Priority:** 🔴 CRITICAL before any external testing

---

## Phase 0.1: Authorization Enforcement (Week 1)

### Task 1.1: Add school_id to Users Table

**Create Migration:**
```bash
php artisan make:migration add_school_id_to_users_table
```

**Migration File:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->after('role');
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
            
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
```

**Update User Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'school_id',  // ADD THIS
    ];

    // Update relationship
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    // REMOVE this (old method)
    // public function schools(): HasMany { ... }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSchool(): bool
    {
        return $this->role === 'school';
    }
}
```

**Update AuthController:**
```php
public function register(RegisterRequest $request): JsonResponse
{
    $user = User::create([
        'name' => $request->validated('name'),
        'email' => $request->validated('email'),
        'password' => $request->validated('password'),
        'role' => $request->validated('role', 'user'),
    ]);

    $schoolId = null;

    if ($user->role === 'school') {
        $school = School::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'slug' => Str::slug($user->name) . '-' . Str::lower(Str::random(5)),
            'email' => $user->email,
        ]);
        
        // UPDATE: Set school_id on user
        $user->update(['school_id' => $school->id]);
        $schoolId = $school->id;
    }

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
        'schoolId' => $schoolId,
    ], 201);
}

public function me(Request $request): JsonResponse
{
    $user = $request->user();

    return response()->json([
        'user' => $user,
        'schoolId' => $user->school_id,  // SIMPLIFIED
    ]);
}
```

---

### Task 1.2: Create BelongsToSchool Trait

**Create File:** `app/Models/Traits/BelongsToSchool.php`

```php
<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    /**
     * Boot the trait - automatically add school_id scope
     */
    protected static function bootBelongsToSchool()
    {
        static::addGlobalScope('school', function (Builder $builder) {
            // For authenticated users, filter by their school
            if (auth()->check() && auth()->user()->school_id) {
                $builder->where('school_id', auth()->user()->school_id);
            }
            
            // Platform admins can see all data (no filter)
            // They must explicitly use withoutGlobalScope('school')
        });
    }

    /**
     * Relationship
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
```

---

### Task 1.3: Apply Trait to Models

**Update Inquiry Model:**
```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    use HasFactory, BelongsToSchool;  // ADD TRAIT

    protected $fillable = [
        'school_id', 'name', 'phone', 'email', 'vehicle_type', 'area',
        'preferred_timing', 'channel', 'message', 'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
```

**Update Review Model:**
```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory, BelongsToSchool;  // ADD TRAIT

    protected $fillable = [
        'school_id', 'author_name', 'rating', 'content', 'approved',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'approved' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
```

**Update DrivePackage Model:**
```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrivePackage extends Model
{
    use HasFactory, BelongsToSchool;  // ADD TRAIT

    protected $table = 'packages';

    protected $fillable = [
        'school_id', 'name', 'description', 'price', 'sessions',
        'vehicle_type', 'transmission', 'has_pickup', 'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'sessions' => 'integer',
            'has_pickup' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
```

---

### Task 1.4: Create Authorization Tests

**Create File:** `tests/Feature/AuthorizationTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Review;
use App\Models\School;
use App\Models\User;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /**
     * Test that users cannot see other schools' inquiries
     */
    public function test_user_cannot_see_other_schools_inquiries()
    {
        // Setup
        $school1 = School::factory()->create(['name' => 'School 1']);
        $school2 = School::factory()->create(['name' => 'School 2']);

        $user1 = User::factory()->create(['school_id' => $school1->id, 'role' => 'school']);
        $user2 = User::factory()->create(['school_id' => $school2->id, 'role' => 'school']);

        $inquiry1 = Inquiry::factory()->create(['school_id' => $school1->id]);
        $inquiry2 = Inquiry::factory()->create(['school_id' => $school2->id]);

        // Test: User1 sees only their inquiry
        $response = $this->actingAs($user1)->getJson('/api/inquiries');
        
        $response->assertOk()
            ->assertJsonFragment(['id' => $inquiry1->id])
            ->assertJsonMissing(['id' => $inquiry2->id]);

        // Test: User2 sees only their inquiry
        $response = $this->actingAs($user2)->getJson('/api/inquiries');
        
        $response->assertOk()
            ->assertJsonFragment(['id' => $inquiry2->id])
            ->assertJsonMissing(['id' => $inquiry1->id]);
    }

    /**
     * Test that users cannot update other schools' inquiries
     */
    public function test_user_cannot_update_other_schools_inquiry()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['school_id' => $school1->id, 'role' => 'school']);
        $inquiry2 = Inquiry::factory()->create(['school_id' => $school2->id]);

        // User1 tries to update inquiry from school2
        $response = $this->actingAs($user1)
            ->patchJson("/api/inquiries/{$inquiry2->id}", [
                'status' => 'converted',
            ]);

        // Should return 404 (inquiry not found due to school scope)
        $response->assertNotFound();
    }

    /**
     * Test that users cannot delete other schools' reviews
     */
    public function test_user_cannot_delete_other_schools_review()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['school_id' => $school1->id, 'role' => 'school']);
        $review2 = Review::factory()->create(['school_id' => $school2->id]);

        // User1 tries to delete review from school2
        $response = $this->actingAs($user1)
            ->deleteJson("/api/reviews/{$review2->id}");

        // Should return 404
        $response->assertNotFound();
    }

    /**
     * Test that reviews data is protected
     */
    public function test_user_cannot_see_other_schools_reviews()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['school_id' => $school1->id, 'role' => 'school']);

        $review1 = Review::factory()->create(['school_id' => $school1->id]);
        $review2 = Review::factory()->create(['school_id' => $school2->id]);

        $response = $this->actingAs($user1)->getJson('/api/reviews');

        $response->assertOk()
            ->assertJsonFragment(['id' => $review1->id])
            ->assertJsonMissing(['id' => $review2->id]);
    }
}
```

**Run Tests:**
```bash
php artisan test --filter AuthorizationTest
```

---

### Task 1.5: Simplify Controllers (No code changes needed!)

Since the trait automatically scopes queries, controllers stay the same:

```php
// InquiryController - Now automatically safe!
public function index(Request $request): JsonResponse
{
    $query = Inquiry::with('school')  // ✅ Auto-scoped to user's school
        ->orderByDesc('created_at');

    if ($request->filled('schoolId')) {
        $query->where('school_id', $request->input('schoolId'));
    }

    return response()->json(InquiryResource::collection($query->get()));
}
```

---

## Phase 0.2: Review Integrity (Week 2)

### Task 2.1: Create Review Reports Table

**Create Migration:**
```bash
php artisan make:migration create_review_reports_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('reported_by_id')->nullable();
            $table->string('reason'); // 'inappropriate', 'fake', 'spam', 'other'
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, investigating, resolved
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
            $table->foreign('reported_by_id')->references('id')->on('users')->setNullOnDelete();
            
            $table->index(['review_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
```

---

### Task 2.2: Add Missing Review Fields

**Create Migration:**
```bash
php artisan make:migration add_eligibility_fields_to_reviews_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Add columns
            $table->unsignedBigInteger('inquiry_id')->nullable()->after('school_id');
            $table->unsignedBigInteger('learner_id')->nullable()->after('inquiry_id');
            $table->string('author_email')->nullable()->change();
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])
                ->default('pending')->after('approved');
            $table->integer('abuse_report_count')->default(0)->after('moderation_status');

            // Add indexes
            $table->index(['school_id', 'moderation_status']);
            $table->index(['school_id', 'created_at']);

            // Add foreign keys
            $table->foreign('inquiry_id')->references('id')->on('inquiries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['inquiry_id']);
            $table->dropColumn(['inquiry_id', 'learner_id', 'moderation_status', 'abuse_report_count']);
        });
    }
};
```

---

### Task 2.3: Create Lead Status History Table

**Create Migration:**
```bash
php artisan make:migration create_lead_status_history_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inquiry_id');
            $table->string('from_status');
            $table->string('to_status');
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('inquiry_id')->references('id')->on('inquiries')->cascadeOnDelete();
            $table->foreign('changed_by_id')->references('id')->on('users')->setNullOnDelete();
            
            $table->index(['inquiry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_history');
    }
};
```

---

### Task 2.4: Implement Review Eligibility

**Update ReviewController:**
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Models\Inquiry;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $schoolId = $request->input('school_id');
        $authorEmail = $request->input('author_email');

        // Check eligibility: user must have submitted inquiry
        $inquiry = Inquiry::where('school_id', $schoolId)
            ->where('email', $authorEmail)
            ->first();

        if (!$inquiry) {
            return response()->json([
                'message' => 'Only users with inquiry can review this school',
            ], 403);
        }

        // Create review
        $data = $request->toSnakeCase();
        $data['inquiry_id'] = $inquiry->id;
        $data['moderation_status'] = 'pending';
        $data['approved'] = false;

        $review = Review::create($data);
        $review->load('school');

        return response()->json(new ReviewResource($review), 201);
    }

    public function index(Request $request): JsonResponse
    {
        // ... existing code but filter by moderation_status = 'approved'
        
        $query = Review::where('moderation_status', 'approved')
            ->with('school')
            ->orderByDesc('created_at');

        if ($request->filled('schoolId')) {
            $query->where('school_id', $request->input('schoolId'));
        }

        return response()->json(ReviewResource::collection($query->get()));
    }
}
```

---

### Task 2.5: Add Rate Limiting

**Update routes/api.php:**
```php
Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:10,60');  // 10 per minute per IP

Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:5,60');  // 5 per minute per IP

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,60');  // 5 login attempts per minute
```

---

## Phase 0.3: Additional Hardening (Week 3)

### Task 3.1: Add Audit Logging

**Create Model:** `app/Models/AuditLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'model_type',
        'model_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function log($action, $modelType, $modelId, $oldValues = [], $newValues = [])
    {
        static::create([
            'user_id' => auth()->id(),
            'school_id' => auth()->user()?->school_id,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }
}
```

**Create Migration:**
```bash
php artisan make:migration create_audit_logs_table
```

**Use in Controllers:**
```php
public function update(UpdateInquiryRequest $request, int $id): JsonResponse
{
    $inquiry = Inquiry::find($id);
    
    if (! $inquiry) {
        return response()->json(['message' => 'Inquiry not found'], 404);
    }

    $oldValues = $inquiry->toArray();
    $inquiry->update($request->validated());
    $newValues = $inquiry->toArray();

    AuditLog::log('update', 'Inquiry', $inquiry->id, $oldValues, $newValues);

    return response()->json(new InquiryResource($inquiry));
}
```

---

## Deployment Checklist

### Before Deploying Phase 0.1
- [ ] Migrations written
- [ ] BelongsToSchool trait created
- [ ] All models updated with trait
- [ ] Authorization tests pass
- [ ] Manual testing with two different users
- [ ] Verified data isolation works

### Database Backup
```bash
# Backup production database before migration
mysqldump -u root driveiq > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Migration Commands
```bash
# Run migrations
php artisan migrate

# If rollback needed
php artisan migrate:rollback
```

### Testing
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/AuthorizationTest.php

# Run with coverage
php artisan test --coverage
```

---

## Success Criteria

✅ Phase 0.1 Complete When:
- All models have BelongsToSchool trait
- Authorization tests pass (100% coverage)
- Users cannot access other schools' data
- Data isolation verified with manual testing

✅ Phase 0.2 Complete When:
- Review eligibility is enforced
- Rate limiting is active
- Lead status history is tracked

✅ Phase 0.3 Complete When:
- Audit logging captures all sensitive actions
- School verification flags are in place
- Comprehensive test suite passes

---

## Rollback Plan

If critical issues found:

```bash
# Revert to last working state
php artisan migrate:rollback

# OR: Remove specific migration
php artisan migrate:rollback --step=1
```

**Keep backups of:**
- Database (daily)
- Migrations (git version control)
- Controllers (git version control)

---

## Next Phase

After Phase 0 completes, move to Phase 1: Geo-Intelligent Search
- PostGIS setup and queries
- Ranking algorithm implementation
- Geo consent flow on frontend
