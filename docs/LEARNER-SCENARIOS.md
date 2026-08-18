# Learner Management Scenarios

**Version:** 1.0  
**Purpose:** Document real-world learner flows and edge cases  
**Updated:** 2026-06-04

---

## Scenario 1: Learner Moves to Different Location → Registers with Another School

### Context
Learner "Raj" completes 50% of his driving course at **School A (Baner)**. He relocates to **Wakad** and wants to continue at **School B (Wakad)** with a different school.

### Current State (School A)
```
Learner: Raj
├─ school_id: 1 (School A)
├─ status: active_training
├─ completed_lessons: 5 of 10
├─ assigned_instructor_id: 101
├─ assigned_vehicle_id: 201
├─ license_status: pending
└─ package_id: pkg_001 (10 lessons)
```

### Question
**What happens to Raj's progress? Can he transfer? Or does he start fresh?**

---

## Solution: Two Approaches

### Approach A: FRESH START (Recommended for MVP)

**Reason:** Simplifies compliance - different schools have different training standards, RTOs, vehicles, instructors.

#### Flow

```
School A (Baner)
├─ Learner Raj (ID: learner_001)
│  ├─ Status: completed (or abandoned)
│  ├─ Lessons completed: 5/10
│  ├─ License status: not_obtained
│  └─ Progress: Saved as historical record
│
└─ [Raj stops here or gets certificate of what he completed]


School B (Wakad)
├─ NEW Learner Raj (ID: learner_002)  ← Different ID
│  ├─ Status: active_training
│  ├─ Lessons completed: 0/10 (starts fresh)
│  ├─ Assigned to: New Instructor
│  ├─ Vehicle: School B's vehicle
│  └─ Note: "Transfer from School A" (metadata)
```

#### Database Schema

**learners table:**
```
id: learner_002 (PRIMARY)
school_id: 2 (School B - NEW)
user_id: user_123 (Raj - SAME)
name: Raj
mobile: 9876543210
email: raj@email.com
status: active_training
license_status: pending
transferred_from_school_id: 1 (School A - metadata)
transferred_from_learner_id: learner_001 (track history)
created_at: 2026-06-04
```

#### Controller Logic

```php
// SchoolBLearnerController.php
public function createTransferredLearner(StoreTransferredLearnerRequest $request)
{
    // Validate request
    $validated = $request->validated();
    
    // Check if learner exists with this email/phone in other schools
    $previousLearner = Learner::where('email', $validated['email'])
        ->orWhere('mobile', $validated['mobile'])
        ->first();
    
    // Create NEW learner at School B
    $newLearner = Learner::create([
        'school_id' => auth()->user()->school_id,  // School B
        'user_id' => auth()->id(),  // Same user
        'name' => $validated['name'],
        'mobile' => $validated['mobile'],
        'email' => $validated['email'],
        'status' => 'active_training',
        'transferred_from_school_id' => $previousLearner->school_id ?? null,
        'transferred_from_learner_id' => $previousLearner->id ?? null,
        'transfer_notes' => $validated['transfer_notes'] ?? null,
    ]);
    
    // Create audit log
    AuditLog::log('transfer_create', 'Learner', $newLearner->id, [], [
        'from_school_id' => $previousLearner->school_id ?? null,
        'from_learner_id' => $previousLearner->id ?? null,
    ]);
    
    return response()->json(new LearnerResource($newLearner), 201);
}
```

#### Why This Approach

✅ **Pro:**
- Simple: Each school manages its learners independently
- Safe: Data stays isolated within school
- Clear: Learner gets new enrollment, fresh assignments
- Compliance: Different schools follow different standards
- RTO aligned: Different RTOs for different locations

❌ **Con:**
- Learner loses progress tracking across schools
- Can't see full training history easily
- Multiple learner records for same person

---

### Approach B: TRANSFER WITH PROGRESS CONTINUATION (Advanced)

For future: If you want to track across schools (e.g., premium feature).

```php
// Transfer with credit for completed lessons
public function transferWithProgressCredit(TransferLearnerRequest $request)
{
    $previousLearner = Learner::findOrFail($request->input('previous_learner_id'));
    
    // Validate same user
    if ($previousLearner->user_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    // Create new learner at new school
    $newLearner = Learner::create([
        'school_id' => auth()->user()->school_id,
        'user_id' => auth()->id(),
        'name' => $previousLearner->name,
        'mobile' => $previousLearner->mobile,
        'email' => $previousLearner->email,
        'transferred_from_learner_id' => $previousLearner->id,
        'lessons_completed_elsewhere' => $previousLearner->lessons_completed,
    ]);
    
    // Mark old learner as transferred
    $previousLearner->update(['status' => 'transferred']);
    
    return response()->json(new LearnerResource($newLearner), 201);
}
```

---

## Recommended: Approach A (Fresh Start)

**For MVP:** Keep each school's learners independent.

**Implementation:**

1. **Add field to learners table:**
   ```sql
   ALTER TABLE learners ADD COLUMN transferred_from_school_id BIGINT NULLABLE;
   ALTER TABLE learners ADD COLUMN transferred_from_learner_id BIGINT NULLABLE;
   ALTER TABLE learners ADD COLUMN transfer_notes TEXT NULLABLE;
   ```

2. **Create LearnerTransfer Model:**
   ```php
   class LearnerTransfer extends Model {
       protected $fillable = [
           'from_school_id', 'from_learner_id',
           'to_school_id', 'to_learner_id',
           'transfer_date', 'transfer_reason'
       ];
   }
   ```

3. **Frontend shows:**
   - "Continuing from School A? Click here" option
   - Displays previous progress as reference only
   - Confirms fresh start at new school

---

---

## Scenario 2: Learner Reassigned to Different Instructor (Same School)

### Context
Learner "Priya" was assigned to **Instructor A** but wants to switch to **Instructor B** (both at same School A) because:
- Timing conflict
- Personality fit
- Instructor availability changed

### Current State
```
Learner: Priya (ID: learner_003)
├─ school_id: 1 (School A)
├─ assigned_instructor_id: 102 (Instructor A)
├─ status: active_training
├─ completed_lessons: 3
└─ next_scheduled: with Instructor A
```

### Flow: Reassignment

#### 1. Manager Initiates Reassignment

```php
// School Dashboard
POST /api/learners/{learner_id}/reassign-instructor

Request:
{
    "from_instructor_id": 102,
    "to_instructor_id": 103,
    "reason": "timing_conflict",  // or "student_request", "instructor_unavailable"
    "effective_date": "2026-06-07"
}
```

#### 2. Database Changes

**learners table:**
```
Before:
├─ assigned_instructor_id: 102
└─ status: active_training

After:
├─ assigned_instructor_id: 103 (UPDATED)
└─ status: active_training
```

**NEW: learner_instructor_history table:**
```
id | learner_id | from_instructor_id | to_instructor_id | reason | changed_by | effective_date | created_at
───┼────────────┼───────────────────┼──────────────────┼────────┼────────────┼────────────────┼──────────
 1 │ 003        │ 102               │ 103              │ timing │ manager_05 │ 2026-06-07     │ 2026-06-04
```

#### 3. Controller Implementation

```php
class LearnerController {
    public function reassignInstructor(
        ReassignInstructorRequest $request,
        int $learnerId
    ): JsonResponse {
        // Get learner (auto-scoped to user's school via BelongsToSchool)
        $learner = Learner::findOrFail($learnerId);
        
        // Validate both instructors belong to same school
        $fromInstructor = Instructor::findOrFail(
            $request->input('from_instructor_id')
        );
        $toInstructor = Instructor::findOrFail(
            $request->input('to_instructor_id')
        );
        
        if ($fromInstructor->school_id !== $learner->school_id ||
            $toInstructor->school_id !== $learner->school_id) {
            return response()->json([
                'message' => 'Instructors must be from same school'
            ], 422);
        }
        
        // Verify learner is currently assigned to from_instructor
        if ($learner->assigned_instructor_id !== $fromInstructor->id) {
            return response()->json([
                'message' => 'Learner not assigned to from_instructor'
            ], 422);
        }
        
        // Create history record
        LearnerInstructorHistory::create([
            'learner_id' => $learner->id,
            'from_instructor_id' => $fromInstructor->id,
            'to_instructor_id' => $toInstructor->id,
            'reason' => $request->input('reason'),
            'changed_by_id' => auth()->id(),
            'effective_date' => $request->input('effective_date'),
        ]);
        
        // Update learner
        $learner->update([
            'assigned_instructor_id' => $toInstructor->id,
        ]);
        
        // Audit log
        AuditLog::log('reassign_instructor', 'Learner', $learner->id, [
            'assigned_instructor_id' => $fromInstructor->id,
        ], [
            'assigned_instructor_id' => $toInstructor->id,
        ]);
        
        return response()->json(new LearnerResource($learner));
    }
}
```

#### 4. Frontend Flow

```
School Manager Dashboard
│
├─ Click on Learner "Priya"
│
├─ Current Instructor: Instructor A (ID: 102)
│
├─ Button: "Change Instructor"
│
├─ Modal Opens:
│  ├─ From: Instructor A (read-only)
│  ├─ To: [Dropdown - all available instructors at School A]
│  ├─ Reason: [Select - timing_conflict, student_request, instructor_unavailable]
│  ├─ Effective Date: [Datepicker]
│  └─ Button: "Confirm Reassignment"
│
└─ After: Shows "Instructor changed to Instructor B"
   └─ History view shows previous instructors
```

#### 5. What Happens to Schedules?

```
Schedule Decision Options:

Option A: RESCHEDULE EXISTING SESSIONS
  - Automatically reschedule remaining sessions with new instructor
  - Old instructor removed
  - Student notified of new schedule

Option B: CANCEL PENDING & CREATE NEW
  - Cancel sessions from old instructor (refund if applicable)
  - Create new sessions with new instructor
  - Student books new time slots

Option C: FLEXIBLE (Recommended)
  - Pending sessions: Automatically transfer to new instructor
  - In-progress sessions: Complete with old instructor
  - Future sessions: Start with new instructor
```

**Implementation:**
```php
// After reassignment, update schedules
$effectiveDate = $request->input('effective_date');

// Keep existing completed/in-progress
// Cancel future sessions with old instructor
Schedule::where('learner_id', $learner->id)
    ->where('instructor_id', $fromInstructor->id)
    ->where('session_date', '>=', $effectiveDate)
    ->where('status', 'scheduled')
    ->update(['status' => 'cancelled_by_reassignment']);

// Create new sessions with new instructor
// (User will reschedule)
```

---

## Scenario 3: Learner with Multiple Instructors During Road Training

### Context
Driving training has phases:
1. **Classroom/Theory** → Instructor A (theory expert)
2. **Vehicle Basics** → Instructor A or B
3. **Road Training** → Can be 2-3 different instructors (highway, city, night driving)

Priya's schedule:
```
Week 1-2: Theory + Basics → Instructor A
Week 3: Highway Training → Instructor C (highway specialist)
Week 4: City Training → Instructor B
Week 5: Night Training → Instructor D (night specialist)
```

### Challenge
Current schema has **single** `assigned_instructor_id`. We need **multiple instructors per phase**.

---

## Solution: Schedule-Based Instructor Assignment

### Approach: Decouple Instructor from Learner (Better Model)

Instead of assigning instructor TO learner, assign instructor TO schedule.

#### New Data Model

**learners table:**
```
id
school_id
name, mobile, email
status
package_id
completed_lessons
assigned_vehicle_id  ← Vehicle stays assigned
training_phase  ← Tracks current phase
```

**schedules table:** (Updated)
```
id
school_id
learner_id        ← Which learner
instructor_id     ← Which instructor for THIS session
vehicle_id        ← Which vehicle
session_date
start_time, end_time
training_type  ← 'theory', 'vehicle_basics', 'highway', 'city', 'night'
status
attendance_status
notes
```

#### Example Schedule

```
Priya's Schedule (learner_id: 003, school_id: 1):

Session 1:
├─ Date: 2026-06-10
├─ Instructor: A (theory)
├─ Type: theory
├─ Duration: 1 hour
└─ Status: scheduled

Session 2:
├─ Date: 2026-06-12
├─ Instructor: A (basics)
├─ Type: vehicle_basics
├─ Duration: 2 hours
└─ Status: scheduled

Session 3:
├─ Date: 2026-06-19
├─ Instructor: C (highway specialist)
├─ Type: highway_training
├─ Duration: 3 hours
└─ Status: scheduled

Session 4:
├─ Date: 2026-06-26
├─ Instructor: B (city training)
├─ Type: city_training
├─ Duration: 3 hours
└─ Status: scheduled

Session 5:
├─ Date: 2026-07-03
├─ Instructor: D (night training)
├─ Type: night_training
├─ Duration: 2 hours
└─ Status: scheduled
```

#### Implementation

**Migration:**
```sql
ALTER TABLE schedules 
ADD COLUMN training_type ENUM(
    'theory', 
    'vehicle_basics', 
    'highway_training', 
    'city_training', 
    'night_training',
    'defensive_driving',
    'reverse_parking'
) DEFAULT 'vehicle_basics';

-- Drop assigned_instructor_id from learners (if not needed)
-- Keep instructor_id in schedules (already exists)
```

**Controller - Create Schedule with Multiple Instructors:**
```php
class ScheduleController {
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Get learner
        $learner = Learner::findOrFail($validated['learner_id']);
        
        // Get instructor - can be different each time!
        $instructor = Instructor::findOrFail($validated['instructor_id']);
        
        // Verify instructor is at same school
        if ($instructor->school_id !== $learner->school_id) {
            return response()->json(['message' => 'Invalid instructor'], 422);
        }
        
        // Create schedule
        $schedule = Schedule::create([
            'school_id' => $learner->school_id,
            'learner_id' => $learner->id,
            'instructor_id' => $instructor->id,
            'vehicle_id' => $validated['vehicle_id'],
            'session_date' => $validated['session_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'training_type' => $validated['training_type'],  // NEW
            'status' => 'scheduled',
        ]);
        
        // Audit log
        AuditLog::log('create_schedule', 'Schedule', $schedule->id, [], [
            'learner_id' => $learner->id,
            'instructor_id' => $instructor->id,
            'training_type' => $validated['training_type'],
        ]);
        
        return response()->json(new ScheduleResource($schedule), 201);
    }
}
```

**Frontend - Show All Instructors in Schedule:**
```jsx
// LearnerSchedule.jsx
<div className="schedule-list">
  {schedules.map(session => (
    <ScheduleCard key={session.id}>
      <div className="session-info">
        <div className="date">{session.sessionDate}</div>
        <div className="time">{session.startTime} - {session.endTime}</div>
        
        {/* Instructor varies by session */}
        <div className="instructor">
          <strong>{session.instructor.name}</strong>
          <span className="badge">{session.trainingType}</span>
        </div>
        
        <div className="type">Training: {session.trainingType}</div>
        <div className="vehicle">Vehicle: {session.vehicle.registrationNumber}</div>
        
        <div className="notes">{session.notes}</div>
      </div>
    </ScheduleCard>
  ))}
</div>
```

#### Dashboard View for Manager

```
Learner: Priya
├─ Status: active_training (33% complete)
├─ Vehicle: Swift (MH-14-AB-1234)
├─ Current Phase: City Training
│
├─ Upcoming Sessions:
│  1. 2026-06-10, 10:00 AM → Instructor A (Theory)
│  2. 2026-06-12, 2:00 PM → Instructor A (Vehicle Basics)
│  3. 2026-06-19, 10:00 AM → Instructor C (Highway Training)  ← Different!
│  4. 2026-06-26, 2:00 PM → Instructor B (City Training)     ← Different!
│  5. 2026-07-03, 8:00 PM → Instructor D (Night Training)    ← Different!
│
├─ Completed Sessions:
│  ✅ Theory Session (2 hrs, Instructor A)
│  ✅ Vehicle Basics (3 hrs, Instructor A)
│
└─ Performance Metrics:
   ├─ Theory Score: 85%
   ├─ Driving Score: 78%
   └─ Attendance: 100%
```

---

## Data Model Summary

### Learners Table
```
id
school_id
user_id
name, mobile, email
status (active_training, completed, dropped_out, transferred)
license_status
training_phase (theory, basics, road_training, rto_prep, completed)
assigned_vehicle_id
transferred_from_school_id (nullable)
transferred_from_learner_id (nullable)
created_at, updated_at
```

### Schedules Table
```
id
school_id
learner_id
instructor_id        ← Can be different for each session!
vehicle_id
session_date
start_time, end_time
training_type (theory, basics, highway, city, night, etc.)
status (scheduled, completed, cancelled, rescheduled)
attendance_status (attended, absent, late)
notes
created_at, updated_at
```

### LearnerInstructorHistory Table (Tracking)
```
id
learner_id
from_instructor_id
to_instructor_id
reason
changed_by_id (who made the change)
effective_date
created_at
```

---

## Key Rules & Constraints

### Rule 1: Data Isolation by school_id
```php
// All models must use BelongsToSchool trait
class Learner extends Model {
    use BelongsToSchool;  // Auto-filters to user's school
}

class Schedule extends Model {
    use BelongsToSchool;  // Auto-filters to user's school
}

class Instructor extends Model {
    use BelongsToSchool;  // Auto-filters to user's school
}
```

### Rule 2: Validate School Ownership
```php
// Before assigning instructor to learner
if ($instructor->school_id !== $learner->school_id) {
    throw ValidationException::withMessages([
        'instructor_id' => 'Instructor must be from same school'
    ]);
}
```

### Rule 3: Track All Changes
```php
// Create history records when reassigning
LearnerInstructorHistory::create([
    'learner_id' => $learner->id,
    'from_instructor_id' => $old_instructor_id,
    'to_instructor_id' => $new_instructor_id,
    'reason' => $reason,
    'changed_by_id' => auth()->id(),
    'effective_date' => $effective_date,
]);
```

---

## Testing Scenarios

### Test Case 1: Transfer Between Schools
```php
public function test_learner_transfer_creates_new_record()
{
    $school1 = School::factory()->create();
    $school2 = School::factory()->create();
    
    $user = User::factory()->create(['school_id' => $school1->id]);
    $learner1 = Learner::factory()->create(['school_id' => $school1->id]);
    
    // Transfer to school2
    $response = $this->actingAs($user)->postJson('/api/learners/transfer', [
        'email' => $learner1->email,
        'transferred_from_learner_id' => $learner1->id,
        'school_id' => $school2->id,
    ]);
    
    $response->assertStatus(201);
    
    // Verify new record created
    $learner2 = Learner::where('email', $learner1->email)
        ->where('school_id', $school2->id)
        ->first();
    
    $this->assertNotNull($learner2);
    $this->assertEquals($learner1->id, $learner2->transferred_from_learner_id);
}
```

### Test Case 2: Reassign Instructor
```php
public function test_reassign_instructor_updates_and_logs()
{
    $learner = Learner::factory()->create();
    $instructor1 = Instructor::factory()->create(['school_id' => $learner->school_id]);
    $instructor2 = Instructor::factory()->create(['school_id' => $learner->school_id]);
    
    $learner->update(['assigned_instructor_id' => $instructor1->id]);
    
    // Reassign
    $response = $this->patchJson(
        "/api/learners/{$learner->id}/reassign-instructor",
        [
            'from_instructor_id' => $instructor1->id,
            'to_instructor_id' => $instructor2->id,
            'reason' => 'timing_conflict',
        ]
    );
    
    $response->assertOk();
    
    // Verify update
    $this->assertEquals(
        $instructor2->id,
        $learner->refresh()->assigned_instructor_id
    );
    
    // Verify history
    $this->assertDatabaseHas('learner_instructor_history', [
        'learner_id' => $learner->id,
        'from_instructor_id' => $instructor1->id,
        'to_instructor_id' => $instructor2->id,
    ]);
}
```

### Test Case 3: Multiple Instructors in Schedule
```php
public function test_learner_can_have_multiple_instructors_in_schedule()
{
    $learner = Learner::factory()->create();
    $instructor1 = Instructor::factory()->create(['school_id' => $learner->school_id]);
    $instructor2 = Instructor::factory()->create(['school_id' => $learner->school_id]);
    
    // Session 1: With instructor1
    Schedule::factory()->create([
        'learner_id' => $learner->id,
        'instructor_id' => $instructor1->id,
        'training_type' => 'theory',
    ]);
    
    // Session 2: With instructor2
    Schedule::factory()->create([
        'learner_id' => $learner->id,
        'instructor_id' => $instructor2->id,
        'training_type' => 'highway_training',
    ]);
    
    // Verify both exist
    $schedules = Schedule::where('learner_id', $learner->id)->get();
    $this->assertEquals(2, $schedules->count());
    
    $instructorIds = $schedules->pluck('instructor_id')->unique();
    $this->assertCount(2, $instructorIds);
}
```

---

## Summary Table

| Scenario | Flow | Key Change | Data Model |
|----------|------|-----------|-----------|
| **Learner moves to another school** | Fresh enrollment | Create NEW learner record | `transferred_from_school_id` |
| **Reassign within school** | Update & history | Change `assigned_instructor_id` | `learner_instructor_history` |
| **Multiple instructors per phase** | Schedule-based | Instructor on each schedule | `schedules.training_type` |

---

## Migration Checklist

To implement these scenarios:

### Immediate (Phase 0)
- [ ] Add `transferred_from_school_id` to learners
- [ ] Add `transferred_from_learner_id` to learners
- [ ] Create `learner_instructor_history` table

### Phase 6 (Learner Management)
- [ ] Create `learners` table (full schema)
- [ ] Add `training_type` to schedules
- [ ] Create `instructors` table
- [ ] Create `schedules` table

### Phase 7+ (Advanced)
- [ ] Create `learner_documents` table
- [ ] Create `learner_progress` table
- [ ] Create `learner_enrollment_history` table
- [ ] Create `training_progress` model with module tracking
