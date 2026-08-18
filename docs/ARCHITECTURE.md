# DriveQ Architecture Guide

**Version:** 1.0  
**Last Updated:** 2026-06-04

---

## Core Architecture Decision: Shared Database + Logical Isolation

### What We're Building

**NOT a multi-tenant SaaS** (separate database per school)

**IS a shared-database platform** (similar to Gym/Clinic/Salon management software)
- Single PostgreSQL instance
- All schools' data in same database
- Logical isolation via `school_id` filtering
- Authorization at application layer (RBAC)

### Why This Approach

| Aspect | Multi-Tenant DB | Shared DB |
|--------|-----------------|-----------|
| **Complexity** | High | Low ✅ |
| **Analytics** | Hard | Easy ✅ |
| **Scaling** | Expensive | Cost-effective ✅ |
| **Maintenance** | Complex | Simple ✅ |
| **Data Isolation** | Physical | Logical ✅ |

For your use case (dozens to hundreds of schools, not millions), **shared database is correct**.

---

## Database Architecture

### Core Principle

**Every operational table must contain `school_id`**

```
Good:
- learners (id, school_id, name, mobile, ...)
- instructors (id, school_id, name, license_number, ...)
- vehicles (id, school_id, registration_number, ...)

Bad:
- learners (id, name, mobile, ...) ← No way to isolate!
```

### Table Organization (60-70 tables)

**Global Platform Tables (~15)**
User management, roles, subscriptions, rules, resources.

**School Configuration (~10)**
School profiles, settings, documents, verification.

**Lead Management (~8)**
Leads, activities, notes, sources, conversions.

**Learner Management (~10)**
Learners, documents, packages, progress, tracking.

**Instructor Management (~8)**
Instructors, documents, skills, ratings, availability.

**Scheduling (~6)**
Sessions, attendance, rescheduling, conflicts.

**Vehicle Management (~6)**
Vehicles, maintenance, assignments, availability.

**Marketplace (~8)**
Listings, reviews, photos, featured placements.

**Communication (~6)**
Messages, notifications, templates, logs.

**Google Business (~5)**
Accounts, reviews, posts, photos, analytics.

### Recommended Schema Pattern

```sql
-- Good pattern
CREATE TABLE learners (
    id BIGINT PRIMARY KEY,
    school_id BIGINT NOT NULL,  -- CRITICAL
    name VARCHAR(255),
    mobile VARCHAR(20),
    email VARCHAR(255),
    status ENUM('active', 'inactive', 'completed'),
    license_status ENUM('pending', 'pass', 'fail', 'obtained'),
    assigned_instructor_id BIGINT,
    assigned_vehicle_id BIGINT,
    package_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (school_id) REFERENCES schools(id),
    INDEX idx_school_id (school_id),  -- Critical for filtering
    INDEX idx_school_created (school_id, created_at)  -- For pagination
);
```

---

## Authorization Pattern: RBAC + school_id Enforcement

### 5 Core Roles

1. **Platform Admin** — System administration
2. **School Owner** — Owner/founder of school
3. **School Manager** — Day-to-day operations
4. **Instructor** — Teaches learners
5. **Learner** — Enrolled student

### Permission Logic

```
Platform Admin
    ↓
Can access: ENTIRE SYSTEM (with override)

School Owner / Manager
    ↓
Can access: Resources WHERE school_id = auth()->user()->school_id

Instructor
    ↓
Can access: 
    - Assigned learners
    - Own schedule
    - Assigned vehicles

Learner
    ↓
Can access:
    - Own profile
    - Own schedule
    - Own progress
```

---

## Laravel Implementation: BelongsToSchool Trait

### Create the Trait

**File:** `app/Models/Traits/BelongsToSchool.php`

```php
<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    /**
     * Boot the trait - add global scope
     */
    protected static function bootBelongsToSchool()
    {
        // Automatically filter by user's school_id
        static::addGlobalScope('school', function (Builder $builder) {
            $school_id = auth()->user()?->school_id;
            
            if ($school_id) {
                $builder->where('school_id', $school_id);
            }
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

### Use in Models

```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Learner extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'name',
        'mobile',
        'email',
        'status',
        'license_status',
        'assigned_instructor_id',
    ];
}
```

### Query Examples

```php
// Automatically scoped to user's school
$learners = Learner::all();
// Equivalent to: Learner::where('school_id', auth()->user()->school_id)->all()

// Pagination
$learners = Learner::paginate(15);
// Still scoped!

// Relationships work
$learner = Learner::first();
$instructor = $learner->instructor;
// Only accessible if instructor is in same school
```

### Admin Override

```php
// Only platform admins should be able to do this
if (auth()->user()->is_platform_admin) {
    $all_learners = Learner::withoutGlobalScope('school')->get();
}
```

---

## API Security Pattern

### Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use Illuminate\Http\Request;

class LearnerController extends Controller
{
    /**
     * List all learners for authenticated school
     */
    public function index()
    {
        // Automatically filtered by school_id via trait
        $learners = Learner::paginate(15);
        return response()->json(['data' => $learners]);
    }

    /**
     * Create learner
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20',
            'email' => 'required|email',
            'status' => 'required|in:active,inactive',
        ]);

        // Force school_id from authenticated user
        $validated['school_id'] = auth()->user()->school_id;

        $learner = Learner::create($validated);
        return response()->json(['data' => $learner], 201);
    }

    /**
     * Update learner
     */
    public function update(Request $request, Learner $learner)
    {
        // Global scope already prevents access to other schools' learners
        // If learner doesn't exist in user's school, 404 is returned automatically
        
        $validated = $request->validate([...]);
        $learner->update($validated);
        return response()->json(['data' => $learner]);
    }

    /**
     * Delete learner
     */
    public function destroy(Learner $learner)
    {
        // Same automatic protection
        $learner->delete();
        return response()->json(['message' => 'Learner deleted'], 200);
    }
}
```

### Testing School Isolation

```php
<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Models\Learner;
use Tests\TestCase;

class LearnerIsolationTest extends TestCase
{
    public function test_user_can_only_see_own_schools_learners()
    {
        // Setup
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['school_id' => $school1->id]);
        $user2 = User::factory()->create(['school_id' => $school2->id]);

        $learner1 = Learner::factory()->create(['school_id' => $school1->id]);
        $learner2 = Learner::factory()->create(['school_id' => $school2->id]);

        // Test user1 can only see learner1
        $this->actingAs($user1)
            ->getJson('/api/learners')
            ->assertJsonFragment(['id' => $learner1->id])
            ->assertJsonMissing(['id' => $learner2->id]);

        // Test user2 can only see learner2
        $this->actingAs($user2)
            ->getJson('/api/learners')
            ->assertJsonFragment(['id' => $learner2->id])
            ->assertJsonMissing(['id' => $learner1->id]);
    }

    public function test_user_cannot_access_other_schools_learner()
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['school_id' => $school1->id]);
        $learner2 = Learner::factory()->create(['school_id' => $school2->id]);

        // User1 tries to update learner from school2 - should 404
        $this->actingAs($user1)
            ->patchJson("/api/learners/{$learner2->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }
}
```

---

## Future: Multi-Branch Support

If you expand to branches later:

```
School
    ├─ Branch 1 (Mumbai)
    │   ├─ Instructors
    │   └─ Learners
    └─ Branch 2 (Pune)
        ├─ Instructors
        └─ Learners
```

**Current single-branch schema extends naturally:**

```sql
CREATE TABLE branches (
    id BIGINT PRIMARY KEY,
    school_id BIGINT NOT NULL,
    name VARCHAR(255),
    location VARCHAR(255),
    created_at TIMESTAMP,
    
    FOREIGN KEY (school_id) REFERENCES schools(id)
);

-- Update learners table
ALTER TABLE learners ADD COLUMN branch_id BIGINT;
ALTER TABLE learners ADD FOREIGN KEY (branch_id) REFERENCES branches(id);

-- Now can query: learners WHERE school_id = X AND branch_id = Y
```

No architectural change needed. Design supports expansion.

---

## Critical Rules

### Rule 1: Every Query Must Be Scoped
```
❌ BAD:
SELECT * FROM learners;

✅ GOOD:
SELECT * FROM learners WHERE school_id = :school_id;

✅ BETTER (Automatic via trait):
Learner::all(); // Automatically scoped
```

### Rule 2: Force school_id on Create
```
❌ BAD:
Learner::create($request->all());

✅ GOOD:
$data = $request->validated();
$data['school_id'] = auth()->user()->school_id;
Learner::create($data);
```

### Rule 3: Test School Isolation
Every CRUD operation must have tests verifying:
- User A cannot see User B's data
- User A cannot modify User B's data
- User A cannot delete User B's data

### Rule 4: Index for Performance
```sql
-- Essential indexes
INDEX idx_school_id (school_id)
INDEX idx_school_created (school_id, created_at)
INDEX idx_school_status (school_id, status)
```

---

## Models Using BelongsToSchool

All operational (non-global) models:

```
✅ Learner
✅ Instructor
✅ Vehicle
✅ Schedule
✅ Lead
✅ Review
✅ Package
✅ Document
✅ Message
✅ Notification
✅ GoogleBusinessAccount
✅ InstructorRating
✅ LearnerProgress
✅ TrainingProgress
✅ Attendance
✅ VehicleMaintenance
✅ RescheduleRequest
```

---

## Summary

| Aspect | Decision |
|--------|----------|
| **Architecture** | Shared Database + Logical Isolation |
| **Multi-tenancy** | NOT true multi-tenant |
| **Database** | Single PostgreSQL |
| **Tables** | 60-70 organized by domain |
| **Isolation** | `school_id` in every operational table |
| **Authorization** | RBAC + school_id enforcement |
| **Laravel Pattern** | BelongsToSchool trait + Global Scopes |
| **Testing** | Mandatory isolation tests |
| **Future Scale** | Naturally supports branches + multi-location |

This architecture is **simple, scalable, and proven**. It's used by thousands of SaaS products managing business operations (gyms, clinics, salons, repair shops, etc.).

**Build with confidence.**
