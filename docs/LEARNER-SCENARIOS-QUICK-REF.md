# Learner Scenarios - Quick Reference

---

## ⚡ TLDR (Too Long; Didn't Read)

| Scenario | Action | Result | Database |
|----------|--------|--------|----------|
| **1. Learner moves to new school** | Create NEW enrollment | Fresh learner record at new school | `learners` (new row) + `transferred_from_school_id` |
| **2. Reassign within school** | Update instructor + log | Same learner, different instructor | `learners.assigned_instructor_id` + `learner_instructor_history` |
| **3. Multiple instructors in phases** | Schedule-based assignment | Each session has own instructor | `schedules.instructor_id` + `training_type` |

---

## 🎯 Scenario 1: Learner Moves to Different Location

### The Problem
```
Raj was training at School A (Baner)
↓
Raj relocates to Wakad
↓
Raj wants to join School B (Wakad)
↓
Question: Does he keep his progress? Transfer lessons?
```

### The Solution
**CREATE NEW LEARNER RECORD** (Fresh Start)

```
Reason: Different schools have different standards, vehicles, RTOs, instructors
Approach: Clean slate at new school
Data kept: Reference to where he came from
```

### Technical Details

**Database:**
```sql
-- Add to learners table
ALTER TABLE learners ADD transferred_from_school_id INT;
ALTER TABLE learners ADD transferred_from_learner_id INT;

-- Record: School A
learner_id: 001, school_id: 1, name: Raj, status: completed, lessons: 5/10

-- Record: School B (NEW)
learner_id: 002, school_id: 2, name: Raj, status: active_training, 
transferred_from_school_id: 1, transferred_from_learner_id: 001
```

**Controller Endpoint:**
```php
POST /api/learners/transfer
{
    "email": "raj@email.com",
    "mobile": "9876543210",
    "transferred_from_learner_id": 1
}
// Returns: New learner record (learner_002)
```

**Frontend UX:**
```
"Have you trained at another school?"
[Yes, I have]
  → Show "Continuing from School A?"
  → Auto-populate learner info
  → Create new enrollment
[No, new learner]
  → Normal registration flow
```

### Key Points
✅ Progress NOT transferred (fresh start)
✅ BUT referenced for admin review
✅ Clean data isolation (each school's learners stay with school)
✅ Simple to implement

### Migration Needed
```sql
ALTER TABLE learners ADD transferred_from_school_id BIGINT NULLABLE;
ALTER TABLE learners ADD transferred_from_learner_id BIGINT NULLABLE;
ALTER TABLE learners ADD transfer_notes TEXT NULLABLE;
```

---

## 🎯 Scenario 2: Reassign to Different Instructor (Same School)

### The Problem
```
Priya is training with Instructor A
↓
Priya has timing conflict
OR Priya wants different instructor
OR Instructor A unavailable
↓
Reassign to Instructor B (same school)
↓
Question: What happens to her progress/schedule?
```

### The Solution
**UPDATE INSTRUCTOR + LOG HISTORY**

```
What changes: assigned_instructor_id
What stays: All progress, lessons, vehicle, school
What tracked: History of all reassignments
```

### Technical Details

**Database:**
```sql
-- Update learner
UPDATE learners 
SET assigned_instructor_id = 103  -- Instructor B
WHERE id = 003;  -- Priya

-- Track in history
INSERT INTO learner_instructor_history 
  (learner_id, from_instructor_id, to_instructor_id, reason, changed_by_id, effective_date)
VALUES 
  (003, 102, 103, 'timing_conflict', user_manager_id, NOW());
```

**Controller Endpoint:**
```php
PATCH /api/learners/{learner_id}/reassign-instructor
{
    "from_instructor_id": 102,
    "to_instructor_id": 103,
    "reason": "timing_conflict",  // or "student_request", "instructor_unavailable"
    "effective_date": "2026-06-07"
}
// Returns: Updated learner record
```

**What Happens to Schedules?**

Option A (Recommended):
```
- Past sessions: ✓ Stay with Instructor A
- Today's session: ⏳ Complete with Instructor A
- Future sessions: ❌ Cancelled (reschedule with new instructor)
```

Option B (Auto-transfer):
```
- All future sessions: Automatically transferred to Instructor B
- Learner gets updated schedule
```

**Frontend UX:**
```
Learner: PRIYA
├─ Current Instructor: Instructor A
├─ Button: [CHANGE INSTRUCTOR]
│
└─ Modal:
   ├─ From: Instructor A (read-only)
   ├─ To: [Dropdown with available instructors]
   ├─ Reason: [timing_conflict / student_request / etc]
   ├─ Effective Date: [Datepicker]
   └─ [CONFIRM REASSIGNMENT]

REASSIGNMENT HISTORY:
  June 4: A → B (timing_conflict)
  May 20: B → A (student_request)
```

### Key Points
✅ Progress PRESERVED (same learner)
✅ History tracked for audit
✅ Schedules handled intelligently
✅ Manager has full control

### Migrations Needed
```sql
CREATE TABLE learner_instructor_history (
    id BIGINT PRIMARY KEY,
    learner_id BIGINT,
    from_instructor_id BIGINT,
    to_instructor_id BIGINT,
    reason VARCHAR(255),
    changed_by_id BIGINT,
    effective_date DATE,
    created_at TIMESTAMP
);
```

---

## 🎯 Scenario 3: Multiple Instructors in Road Training

### The Problem
```
Driving training has phases:
  Week 1: Theory → Instructor A
  Week 2: Vehicle Basics → Instructor A
  Week 3: Highway Training → Instructor C (specialist)
  Week 4: City Training → Instructor B (specialist)
  Week 5: Night Training → Instructor D (specialist)

Current Model Problem:
  ❌ learners.assigned_instructor_id = single instructor only
  ❌ Can't handle 5 different instructors
```

### The Solution
**SCHEDULE-BASED INSTRUCTOR ASSIGNMENT**

```
Key insight: Don't assign instructor to learner
Instead: Assign instructor to each SCHEDULE (session)

So: One learner CAN have many instructors
    Each learner session has its own instructor
```

### Technical Details

**Database:**
```sql
-- Add to schedules table
ALTER TABLE schedules ADD training_type ENUM(
    'theory',
    'vehicle_basics',
    'highway_training',
    'city_training',
    'night_training',
    'defensive_driving',
    'reverse_parking'
);

-- Now schedules can look like:
Schedule 1: learner_003, instructor_102, training_type='theory'
Schedule 2: learner_003, instructor_102, training_type='vehicle_basics'
Schedule 3: learner_003, instructor_103, training_type='highway_training' ← Different!
Schedule 4: learner_003, instructor_101, training_type='city_training'   ← Different!
Schedule 5: learner_003, instructor_104, training_type='night_training'  ← Different!
```

**Data Relationships:**
```
Learner (ONE)
    ↓
    ├─ Vehicle (assigned once) → Stays throughout training
    │
    └─ Schedules (MANY) → Each with potentially different instructor
        ├─ Schedule 1 → Instructor A
        ├─ Schedule 2 → Instructor A
        ├─ Schedule 3 → Instructor C (highway specialist)
        ├─ Schedule 4 → Instructor B (city specialist)
        └─ Schedule 5 → Instructor D (night specialist)
```

**Controller Endpoint:**
```php
POST /api/schedules
{
    "learner_id": 3,
    "instructor_id": 103,  // Can be different each time!
    "vehicle_id": 201,
    "session_date": "2026-06-19",
    "start_time": "10:00",
    "end_time": "13:00",
    "training_type": "highway_training"  // Specifies the phase
}
// Returns: Schedule with highway specialist instructor
```

**Frontend UX:**
```
PRIYA'S TRAINING SCHEDULE

✅ Completed Sessions
  • June 10: Theory (Instructor A) - 1 hour
  • June 12: Vehicle Basics (Instructor A) - 2 hours

⏳ Upcoming Sessions
  • June 19: Highway Training (Instructor C) ← Highway Specialist
    [View Details] [Confirm Attendance]
  
  • June 26: City Training (Instructor B) ← City Specialist
    [Reschedule] [View Details]
  
  • July 3: Night Training (Instructor D) ← Night Specialist
    [Reschedule] [View Details]
```

**Progress Dashboard:**
```
Priya's Training Progress
├─ Phase 1: Theory ✅ Complete
├─ Phase 2: Vehicle Basics ✅ Complete
├─ Phase 3: Road Training (In Progress)
│  ├─ Highway: Week of June 19 (Instructor C)
│  ├─ City: Week of June 26 (Instructor B)
│  └─ Night: Week of July 3 (Instructor D)
└─ Ready for RTO Test: July 10
```

### Key Points
✅ ONE learner
✅ MULTIPLE instructors
✅ Different specialist for each phase
✅ Tracked by training_type
✅ Each schedule independent

### Migrations Needed
```sql
ALTER TABLE schedules ADD training_type ENUM(
    'theory',
    'vehicle_basics',
    'highway_training',
    'city_training',
    'night_training'
) DEFAULT 'vehicle_basics';

ALTER TABLE schedules ADD COLUMN attendance_status VARCHAR(255) NULLABLE;
```

---

## 📊 Decision Matrix

```
WHEN TO USE WHICH APPROACH:

Scenario 1: Transfer Between Schools
├─ IF: Learner relocates AND wants new school
├─ THEN: Create new learner record
├─ DATA: New record with reference to old school
└─ PROGRESS: Lost (fresh start)

Scenario 2: Reassign Instructor (Same School)
├─ IF: Learner wants different instructor
├─ THEN: Update assigned_instructor_id
├─ DATA: Add history record
└─ PROGRESS: Preserved (same learner)

Scenario 3: Multiple Instructors Per Phase
├─ IF: Need different specialists for different phases
├─ THEN: Use schedule-based assignment
├─ DATA: training_type field on schedules
└─ PROGRESS: Tracked by phase completion
```

---

## 🔄 Data Flow Diagrams

### Scenario 1 Flow
```
POST /api/learners/transfer
     ↓
Validate transferred_from_learner_id exists
     ↓
Check school isolation
     ↓
Create NEW learner record
     ↓
Set transferred_from fields
     ↓
Return: New learner_id
```

### Scenario 2 Flow
```
PATCH /api/learners/{id}/reassign-instructor
     ↓
Validate learner exists & current instructor
     ↓
Check both instructors in same school
     ↓
Create history record
     ↓
Update assigned_instructor_id
     ↓
Handle schedules (flexible/auto-transfer)
     ↓
Create audit log
     ↓
Return: Updated learner
```

### Scenario 3 Flow
```
POST /api/schedules
     ↓
Validate learner exists
     ↓
Validate instructor exists & same school
     ↓
Set training_type based on session phase
     ↓
Create schedule record
     ↓
Assign instructor to THIS schedule
     ↓
Return: Schedule with instructor
```

---

## 💾 Database Fields Summary

### Minimum for Phase 0
```
learners:
  + transferred_from_school_id (nullable)
  + transferred_from_learner_id (nullable)

learner_instructor_history:
  + learner_id
  + from_instructor_id
  + to_instructor_id
  + reason
  + changed_by_id
  + effective_date
```

### For Phase 6 (Full)
```
learners:
  + all Phase 0 fields
  + training_phase
  + assigned_vehicle_id

schedules:
  + training_type (new)
  + attendance_status (new)

instructors:
  + Complete table

learner_progress:
  + Complete table
```

---

## ✅ Implementation Checklist

### Scenario 1: Transfer
- [ ] Add migration for transferred_from fields
- [ ] Create transfer endpoint
- [ ] Write isolation tests
- [ ] Update frontend registration flow
- [ ] Document process for school managers

### Scenario 2: Reassign
- [ ] Create learner_instructor_history migration
- [ ] Add reassign endpoint
- [ ] Implement schedule handling logic
- [ ] Create audit logs
- [ ] Update dashboard UI
- [ ] Write reassignment tests

### Scenario 3: Multiple Instructors
- [ ] Add training_type to schedules migration
- [ ] Update Schedule model
- [ ] Modify schedule creation logic
- [ ] Update frontend schedule display
- [ ] Create phase tracking view
- [ ] Write multiple instructor tests

---

## 🧪 Test Cases

```
Test 1: Transfer creates isolation
- Create learner at School A
- Transfer to School B
- Verify only School B user can see new learner
- Verify old record unmarked

Test 2: Reassign updates correctly
- Create learner with Instructor A
- Reassign to Instructor B
- Verify history created
- Verify learner still has same progress

Test 3: Multiple instructors work
- Create schedule with Instructor A
- Create schedule with Instructor B for same learner
- Verify both appear in learner's schedule
- Verify different training_types
```

---

## 📚 Full Documentation

For detailed implementation, see:
- **LEARNER-SCENARIOS.md** - Complete code examples
- **LEARNER-SCENARIOS-VISUAL.md** - Visual diagrams
- **PHASE-A-IMPLEMENTATION.md** - Database migrations
- **ARCHITECTURE.md** - Data isolation patterns

---

## Key Takeaways

1. **Transfer (Different School)** = NEW RECORD
   - Fresh learner at new school
   - Progress not transferred
   - Reference kept for history

2. **Reassign (Same School)** = UPDATE + HISTORY
   - Same learner record
   - Progress preserved
   - Changes tracked

3. **Multiple Phases** = SCHEDULE-BASED
   - One learner, many instructors
   - Each schedule has its instructor
   - Training type identifies phase

**All operations must maintain school_id isolation!**
