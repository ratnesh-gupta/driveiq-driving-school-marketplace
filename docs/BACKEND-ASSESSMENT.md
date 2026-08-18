# Backend Laravel Implementation Assessment

**Date:** 2026-06-04  
**Phase:** MVP Foundation Analysis  
**Status:** ⚠️ Critical Authorization Gaps Found

---

## Executive Summary

The Laravel backend has a solid **foundation** with:
- ✅ Correct data models (School, Inquiry, Review, Package, Locality)
- ✅ Database schema includes `school_id` in operational tables
- ✅ Geo-distance calculations implemented (Haversine formula)
- ✅ Basic role-based middleware

**But critical authorization gaps exist:**
- ❌ NO `BelongsToSchool` trait with Global Scopes (queries not auto-scoped)
- ❌ NO enforcement preventing users from accessing other schools' data
- ❌ NO server-side authorization checks in controllers
- ❌ Missing critical models/tables (Learner, Instructor, Vehicle, Schedule, etc.)

---

## Current Implementation Status

### ✅ What's Working

#### Models (6 implemented)
```
✅ School
✅ User
✅ Inquiry
✅ Review
✅ DrivePackage
✅ Locality
```

#### Database Tables
```
✅ schools (with latitude/longitude for geo)
✅ inquiries (with school_id, channel, status)
✅ packages (with school_id)
✅ reviews (with school_id, approved flag)
✅ localities
✅ users
```

#### Controllers (7 implemented)
```
✅ SchoolController (index, featured, show, showBySlug, store, update, delete)
✅ InquiryController (index, store, update)
✅ ReviewController (index, store, update, delete)
✅ PackageController (index, store, update, delete)
✅ LocalityController (basic CRUD)
✅ AuthController (register, login, logout, me)
✅ StatsController (overview, school-level)
```

#### Features
```
✅ API Routes defined with auth/role middleware
✅ Sanctum authentication tokens
✅ Haversine distance formula for geo-search
✅ Role-based middleware (RoleMiddleware.php)
✅ Basic SchoolService with filtering
✅ Inquiry fields: area, preferred_timing, channel, status
```

---

## ❌ Critical Gaps

### 1. **NO BelongsToSchool Trait (CRITICAL)**

**Current Problem:**
```php
// InquiryController - UNSAFE
$query = Inquiry::with('school')
    ->orderByDesc('created_at');

if ($request->filled('schoolId')) {
    $query->where('school_id', $request->input('schoolId'));
}
// ❌ If schoolId NOT provided, returns ALL inquiries from ALL schools!
```

**Correct Implementation Needed:**
```php
// With BelongsToSchool trait
trait BelongsToSchool {
    protected static function bootBelongsToSchool()
    {
        static::addGlobalScope('school', function (Builder $builder) {
            $school_id = auth()->user()?->school_id;
            if ($school_id) {
                $builder->where('school_id', $school_id);
            }
        });
    }
}

// Now EVERY query is automatically scoped
$query = Inquiry::all(); // ✅ Safe - automatically filtered
```

**Risk Level:** 🔴 CRITICAL  
**Severity:** Data leakage possible - users can see other schools' data

---

### 2. **No User→School Association**

**Current State:**
```php
// User model has NO school_id column
// Only relationship is: User→Schools (one-to-many)

class User extends Authenticatable {
    public function schools(): HasMany {
        return $this->hasMany(School::class, 'user_id');
    }
}
```

**Problem:**
- Can't easily identify which school a user manages
- Can't automatically scope queries to user's school
- Need to do: `auth()->user()->schools()->first()` every time

**Required Migration:**
```sql
ALTER TABLE users ADD COLUMN school_id BIGINT NULLABLE AFTER role;
ALTER TABLE users ADD FOREIGN KEY (school_id) REFERENCES schools(id);
```

**Updated User Model:**
```php
class User extends Authenticatable {
    protected $fillable = ['name', 'email', 'password', 'role', 'school_id'];
    
    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }
}
```

---

### 3. **Controllers Not Enforcing school_id Ownership**

#### InquiryController - UPDATE
```php
// ❌ UNSAFE - doesn't verify ownership
public function update(UpdateInquiryRequest $request, int $id): JsonResponse
{
    $inquiry = Inquiry::find($id);  // No school check!
    
    if (! $inquiry) {
        return response()->json(['message' => 'Inquiry not found'], 404);
    }

    $inquiry->fill($request->toSnakeCase());
    $inquiry->save();
    return response()->json(new InquiryResource($inquiry));
}

// ✅ CORRECT - verify ownership before update
public function update(UpdateInquiryRequest $request, int $id): JsonResponse
{
    // With BelongsToSchool trait, this is automatic:
    $inquiry = Inquiry::find($id);
    // If inquiry doesn't belong to user's school, returns 404 automatically
    
    if (! $inquiry) {
        return response()->json(['message' => 'Inquiry not found'], 404);
    }

    $inquiry->update($request->validated());
    return response()->json(new InquiryResource($inquiry));
}
```

#### SchoolController - UPDATE
```php
// ❌ UNSAFE - admin can update any school without verification
public function update(UpdateSchoolRequest $request, int $id): JsonResponse
{
    $school = School::find($id);  // No ownership check!
    
    if (! $school) {
        return response()->json(['message' => 'School not found'], 404);
    }

    $school = $this->schoolService->update($school, $request->toSnakeCase());
    return response()->json(new SchoolResource($school));
}
```

---

### 4. **Missing Core Domain Models (25 tables missing)**

According to the architecture, the MVP needs:

#### Learner Management Tables (MISSING)
```
❌ learners
❌ learner_documents
❌ learner_packages
❌ learner_progress
❌ learner_license_tracking
❌ learner_enrollment_history
```

#### Instructor Management Tables (MISSING)
```
❌ instructors
❌ instructor_documents
❌ instructor_certifications
❌ instructor_skills
❌ instructor_ratings
❌ instructor_availability
```

#### Scheduling Tables (MISSING)
```
❌ schedules
❌ schedule_attendees
❌ attendance
❌ reschedule_requests
```

#### Vehicle Management Tables (MISSING)
```
❌ vehicles
❌ vehicle_documents
❌ vehicle_maintenance
❌ vehicle_assignments
```

#### Communication Tables (MISSING)
```
❌ messages
❌ message_threads
❌ notifications (only exists as personal_access_tokens)
❌ notification_templates
```

#### Verification/Trust Tables (MISSING)
```
❌ school_verifications (phone/business/location/premium)
❌ review_reports (abuse reporting)
❌ lead_status_history
```

---

### 5. **No Review Eligibility Enforcement**

**Current:**
```php
// ReviewController - Anyone can post a review
public function store(StoreReviewRequest $request): JsonResponse
{
    $data = $request->toSnakeCase();
    $data['approved'] ??= false;

    $review = Review::create($data);  // ❌ No eligibility check!
    return response()->json(new ReviewResource($review), 201);
}
```

**Required Enforcement:**
```php
public function store(StoreReviewRequest $request): JsonResponse
{
    $schoolId = $request->input('school_id');
    $email = $request->input('author_email');
    
    // Check if user has inquiry or enrollment with this school
    $hasEligibility = Inquiry::where('school_id', $schoolId)
        ->where('email', $email)
        ->exists();
    
    if (!$hasEligibility) {
        return response()->json([
            'message' => 'Only users with inquiry/enrollment can review'
        ], 403);
    }

    $review = Review::create($request->validated());
    return response()->json(new ReviewResource($review), 201);
}
```

---

### 6. **No API Rate Limiting**

**Current:** None  
**Needed:** 
- Rate limit inquiry submissions (prevent spam)
- Rate limit review submissions
- Rate limit login attempts

```php
// Add to routes/api.php
Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:10,60');  // 10 per minute

Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:5,60');  // 5 per minute
```

---

### 7. **No Audit Logging**

**Current:** No trail for sensitive operations  
**Missing:**
- Lead status changes
- School updates
- Review approvals
- Admin actions

---

## Database Schema Issues

### Missing Critical Indexes

```sql
-- Missing from current migrations
CREATE INDEX idx_inquiry_school_created 
ON inquiries(school_id, created_at);

CREATE INDEX idx_review_school_created 
ON reviews(school_id, created_at);

CREATE INDEX idx_school_id ON packages(school_id);

-- For future geo queries
CREATE INDEX idx_geo_coords ON schools(latitude, longitude);
```

---

## Role-Based Access Control Issues

### Current Implementation (Basic)
```php
// RoleMiddleware - Only checks role exists
if (!in_array($user->role, $roles, true)) {
    return response()->json(['message' => 'Forbidden'], 403);
}
```

### Missing: School-Scoped Authorization

```php
// Missing - should verify school_id ownership
// Example: Only school owner/manager can update own school inquiries

Route::middleware(['auth:sanctum', 'role:school', 'belongs-to-school'])->group(function () {
    Route::patch('/inquiries/{id}', [InquiryController::class, 'update']);
});

// The 'belongs-to-school' middleware would verify:
// - Inquiry belongs to user's school
// - User has permission to manage it
```

---

## Authentication Issues

### Positive
✅ Sanctum tokens are created  
✅ Password is hashed

### Missing
- ❌ No OTP flow (recommended in PRD)
- ❌ No token expiration policy
- ❌ No refresh token rotation
- ❌ No rate limiting on login
- ❌ No account lockout on failed attempts

---

## Testing Gaps

### Current
- No tests visible in `/tests` directory

### Critical Tests Missing
```php
// Must test:
public function test_user_cannot_access_other_schools_inquiries() { }
public function test_school_manager_cannot_update_other_schools_packages() { }
public function test_inquiry_scoped_to_school() { }
public function test_review_eligibility_enforced() { }
```

---

## Phase 0: Stabilization Checklist

### Priority 1: Authorization (Critical - do first)
- [ ] Create `BelongsToSchool` trait with Global Scopes
- [ ] Apply trait to all models: Inquiry, Review, DrivePackage
- [ ] Add `school_id` column to users table
- [ ] Add authorization middleware for school-owned resources
- [ ] Add tests verifying data isolation

### Priority 2: Review Integrity
- [ ] Create `review_reports` table for abuse reporting
- [ ] Implement review eligibility enforcement
- [ ] Add `eligible_reason` column to reviews table
- [ ] Create moderation queue

### Priority 3: Inquiry Enhancements
- [ ] Verify all inquiry fields are captured (area ✅, preferred_timing ✅, channel ✅)
- [ ] Implement rate limiting on inquiry endpoint
- [ ] Add status workflow: new → contacted → converted → lost
- [ ] Add `lead_status_history` table for audit trail

### Priority 4: School Safety
- [ ] Add school-level policies (prevent manager from deleting school)
- [ ] Add verification flags (phone/business/location/premium)
- [ ] Create soft deletes for schools

### Priority 5: Testing
- [ ] Set up PHPUnit test structure
- [ ] Create school isolation tests
- [ ] Create authorization tests

---

## Implementation Guide: BelongsToSchool Trait

### Step 1: Create the Trait

**File:** `app/Models/Traits/BelongsToSchool.php`

```php
<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    protected static function bootBelongsToSchool()
    {
        static::addGlobalScope('school', function (Builder $builder) {
            $school_id = auth()->user()?->school_id;
            
            if ($school_id) {
                $builder->where('school_id', $school_id);
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
```

### Step 2: Apply to Models

```php
// app/Models/Inquiry.php
use App\Models\Traits\BelongsToSchool;

class Inquiry extends Model
{
    use BelongsToSchool;
    // ... rest of model
}

// Apply to: Review, DrivePackage
```

### Step 3: Update Controllers

```php
// Before
$inquiry = Inquiry::find($id);

// After (same code - now protected by trait!)
$inquiry = Inquiry::find($id);  // ✅ Automatically scoped
```

### Step 4: Add Tests

```php
public function test_user_cannot_access_other_schools_inquiries()
{
    $school1 = School::factory()->create();
    $school2 = School::factory()->create();
    
    $user1 = User::factory()->create(['school_id' => $school1->id]);
    $inquiry2 = Inquiry::factory()->create(['school_id' => $school2->id]);

    $this->actingAs($user1)
        ->patchJson("/api/inquiries/{$inquiry2->id}", ['status' => 'converted'])
        ->assertNotFound();  // Should 404, not reveal other school's data
}
```

---

## Security Risk Matrix

| Risk | Severity | Status | Fix Effort |
|------|----------|--------|-----------|
| No global scope on queries | 🔴 CRITICAL | ❌ Missing | 2-3 hours |
| Users can access other schools' data | 🔴 CRITICAL | ❌ Missing | 2-3 hours |
| No review eligibility | 🟠 HIGH | ❌ Missing | 1-2 hours |
| No rate limiting | 🟠 HIGH | ❌ Missing | 1 hour |
| No audit logging | 🟠 HIGH | ❌ Missing | 2-3 hours |
| No OTP auth | 🟡 MEDIUM | ❌ Missing | 4-6 hours |
| Missing domain models | 🟡 MEDIUM | ❌ Missing | Phase 6 |

---

## Recommended Implementation Order

### Week 1: Critical Authorization (Phase 0.1)
1. Add `school_id` to users table
2. Create `BelongsToSchool` trait
3. Apply to Inquiry, Review, DrivePackage
4. Create authorization tests
5. Deploy and verify data isolation

### Week 2: Review Integrity + Inquiry Enhancements (Phase 0.2)
1. Create review_reports table
2. Implement review eligibility enforcement
3. Add rate limiting
4. Create lead_status_history table
5. Implement status workflow

### Week 3: Additional Hardening (Phase 0.3)
1. Add audit logging
2. Create school verification model
3. Add soft deletes
4. Create comprehensive test suite

### Phase 6: Domain Models
- Learner management tables
- Instructor management tables
- Scheduling tables
- Vehicle management tables

---

## Code Quality Observations

### Positive
✅ Uses form request classes for validation  
✅ Service layer pattern (SchoolService)  
✅ Resource classes for API responses  
✅ Proper relationship definitions  
✅ Type casting in models  

### Areas for Improvement
- Add strict types (`declare(strict_types=1);`)
- Add PHPStan/Pint configuration
- Add request validation rules to all form requests
- Add comprehensive error handling
- Add API response standardization

---

## Summary

**Current State:** Foundation is solid but **NOT PRODUCTION SAFE**  
**Main Issues:** Authorization gaps, missing domain models  
**Risk Level:** 🔴 CRITICAL (user data isolation not enforced)  
**Estimated Phase 0 Fix Time:** 3-4 weeks  

**Recommendation:** Implement Phase 0 before any external testing or launch.

---

## Next Steps

1. ✅ Review this assessment
2. ⬜ Create BelongsToSchool trait (today)
3. ⬜ Add school_id to users table (migration)
4. ⬜ Apply trait to models (all operational models)
5. ⬜ Create isolation tests
6. ⬜ Deploy Phase 0.1
7. ⬜ Continue with Phase 0.2, A.3
