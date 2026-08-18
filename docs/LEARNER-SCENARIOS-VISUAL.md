# Learner Scenarios - Visual Guide

---

## Scenario 1: Learner Moves to Different Location

### Problem

```
Learner RAJ
│
├─ Current: School A (Baner)
│  ├─ Status: Active Training
│  ├─ Lessons: 5/10 completed
│  └─ Instructor: Instructor A
│
└─ Wants: School B (Wakad)
   └─ Question: What happens to progress?
```

### Solution A: FRESH START (Recommended for MVP)

```
┌─────────────────────────────────────────────────────────────┐
│ SCHOOL A (BANER)                                            │
│                                                             │
│ Learner: RAJ (ID: learner_001)                             │
│ ├─ Status: COMPLETED (or ABANDONED)                        │
│ ├─ Lessons: 5/10 ✓                                         │
│ ├─ Instructor: Instructor A                                │
│ └─ License Status: NOT_OBTAINED                            │
│                                                             │
│ 📋 Historical Record Saved                                 │
│    (Can reference but no transfer of credit)               │
└─────────────────────────────────────────────────────────────┘
                           ↓
              🔄 LEARNER MOVES TO WAKAD
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ SCHOOL B (WAKAD)                                            │
│                                                             │
│ Learner: RAJ (ID: learner_002) ← NEW ID                   │
│ ├─ Status: ACTIVE_TRAINING                                 │
│ ├─ Lessons: 0/10 ← STARTS FRESH                           │
│ ├─ Instructor: Instructor C (Wakad expert)                │
│ ├─ Vehicle: School B's vehicle                            │
│ └─ Note: "Transferred from School A" (metadata)           │
│                                                             │
│ Database Fields:                                           │
│ {                                                          │
│   "transferred_from_school_id": 1,                         │
│   "transferred_from_learner_id": learner_001              │
│ }                                                          │
└─────────────────────────────────────────────────────────────┘
```

### Database Flow

```
LEARNERS TABLE

┌────────────────────────────────────────────────────────────┐
│ Before: School A                                           │
├────────────────────────────────────────────────────────────┤
│ ID    │ School_ID │ Name │ Status          │ Lessons       │
├───────┼───────────┼──────┼─────────────────┼───────────────┤
│ 001   │ 1         │ Raj  │ active_training │ 5             │
└────────────────────────────────────────────────────────────┘

                    ↓ LEARNER RELOCATES ↓

┌────────────────────────────────────────────────────────────┐
│ After: School B (NEW RECORD)                               │
├────────────────────────────────────────────────────────────┤
│ ID    │ School_ID │ Name │ Status          │ Transferred   │
├───────┼───────────┼──────┼─────────────────┼───────────────┤
│ 002   │ 2         │ Raj  │ active_training │ from_001      │
└────────────────────────────────────────────────────────────┘

Old record (ID: 001) marked as completed
New record (ID: 002) created with reference to old record
```

### Why Fresh Start?

✅ **Simpler:** No complex transfer logic  
✅ **Safe:** Data stays isolated by school  
✅ **Compliant:** Different RTOs, standards, vehicles  
✅ **Clear:** New enrollment = fresh process  

---

## Scenario 2: Reassign to Different Instructor (Same School)

### Problem

```
PRIYA (learner_003)
├─ School: School A (BANER)
├─ Current Instructor: Instructor A
├─ Issue: Timing conflict / Want different instructor
└─ Question: How to reassign without losing progress?
```

### Solution: Update + Track History

```
BEFORE REASSIGNMENT
┌─────────────────────────────────┐
│ Priya                           │
├─────────────────────────────────┤
│ Status: active_training         │
│ Lessons: 3/10                   │
│ Instructor: Instructor A        │
│ Next Session: June 10, 2 PM     │
└─────────────────────────────────┘

        Manager Initiates:
        "Change Instructor"
        From: Instructor A
        To: Instructor B
        Reason: timing_conflict
               ↓

AFTER REASSIGNMENT
┌─────────────────────────────────┐
│ Priya                           │
├─────────────────────────────────┤
│ Status: active_training ✓       │
│ Lessons: 3/10 ✓ (preserved)     │
│ Instructor: Instructor B ← NEW  │
│ Next Session: June 12, 4 PM     │
└─────────────────────────────────┘

        Create History Record:
        From: Instructor A
        To: Instructor B
        Date: June 4, 2026
        Reason: timing_conflict
```

### Data Changes

```
LEARNERS TABLE
┌──────────────────────────────────────────┐
│ ID  │ Name  │ Assigned_Instructor_ID     │
├─────┼───────┼────────────────────────────┤
│ 003 │ Priya │ 102 ─────────→ 103         │
└──────────────────────────────────────────┘

LEARNER_INSTRUCTOR_HISTORY TABLE (NEW)
┌────────────────────────────────────────────────────┐
│ ID │ Learner │ From_Instr │ To_Instr │ Reason      │
├────┼─────────┼────────────┼──────────┼─────────────┤
│ 1  │ 003     │ 102        │ 103      │ timing_conf │
└────────────────────────────────────────────────────┘
```

### Schedule Handling Options

```
Option 1: FLEXIBLE (Recommended)
┌─────────────────────────────────────────────────┐
│ Sessions with Instructor A:                     │
│ ✓ Past: Completed                               │
│ ⏳ Today: Continue with Instructor A             │
│ ❌ Future: Cancelled (pending new schedule)      │
│                                                 │
│ Sessions with Instructor B:                     │
│ 📅 New sessions to be scheduled                 │
└─────────────────────────────────────────────────┘

Option 2: AUTO-TRANSFER
┌─────────────────────────────────────────────────┐
│ All future sessions automatically transferred   │
│ Old instructor: No longer assigned              │
│ New instructor: Takes over remaining sessions   │
│ Student: Receives new schedule                  │
└─────────────────────────────────────────────────┘

Option 3: MANUAL (Full Control)
┌─────────────────────────────────────────────────┐
│ Manager must manually cancel/reschedule         │
│ Good for complex situations                     │
│ More work but more control                      │
└─────────────────────────────────────────────────┘
```

### Frontend Dashboard

```
┌──────────────────────────────────────────────┐
│ LEARNER: PRIYA                               │
├──────────────────────────────────────────────┤
│                                              │
│ Current Instructor: Instructor A             │
│                                              │
│ [CHANGE INSTRUCTOR] Button                   │
│                                              │
│ Lessons Completed: 3/10 ✓                    │
│ Status: Active ✓                             │
│ Vehicle: Swift (assigned)                    │
│                                              │
├────────────────────────────────────────────────┤
│ REASSIGNMENT HISTORY                         │
├────────────────────────────────────────────────┤
│ Date     │ From          │ To          │ Reason │
├──────────┼───────────────┼─────────────┼────────┤
│ Jun 4    │ Instructor A  │ Instructor B│ timing │
│ May 20   │ Instructor B  │ Instructor A│ request│
└────────────────────────────────────────────────┘
```

---

## Scenario 3: Multiple Instructors Per Training Phase

### Problem

```
Driving training requires different specialists:

Week 1: Theory (Classroom) → Instructor A
Week 2: Vehicle Basics → Instructor A or B
Week 3: Highway Training → Specialist C
Week 4: City Training → Specialist B
Week 5: Night Training → Specialist D

Current Model Problem:
❌ learners.assigned_instructor_id = single instructor
✗ Can't handle 5 different instructors in same training
✗ No way to track which instructor for which phase
```

### Solution: Schedule-Based Assignment

```
PRIYA'S COMPLETE TRAINING SCHEDULE

┌─────────────────────────────────────────────────────────┐
│ LEARNER: Priya (ID: learner_003)                        │
│ SCHOOL: School A (Baner)                                │
│ VEHICLE: Swift (assigned throughout)                    │
│                                                         │
│ TRAINING SCHEDULE:                                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Session 1                                               │
│ ├─ Date: June 10                                        │
│ ├─ Instructor: Instructor A 👤                          │
│ ├─ Type: THEORY                                         │
│ ├─ Duration: 1 hour                                     │
│ └─ Status: Scheduled                                    │
│                                                         │
│ Session 2                                               │
│ ├─ Date: June 12                                        │
│ ├─ Instructor: Instructor A 👤                          │
│ ├─ Type: VEHICLE_BASICS                                 │
│ ├─ Duration: 2 hours                                    │
│ └─ Status: Scheduled                                    │
│                                                         │
│ Session 3 ⭐ DIFFERENT INSTRUCTOR!                       │
│ ├─ Date: June 19                                        │
│ ├─ Instructor: Instructor C 👤 (highway specialist)     │
│ ├─ Type: HIGHWAY_TRAINING                               │
│ ├─ Duration: 3 hours                                    │
│ └─ Status: Scheduled                                    │
│                                                         │
│ Session 4 ⭐ DIFFERENT INSTRUCTOR!                       │
│ ├─ Date: June 26                                        │
│ ├─ Instructor: Instructor B 👤 (city specialist)        │
│ ├─ Type: CITY_TRAINING                                  │
│ ├─ Duration: 3 hours                                    │
│ └─ Status: Scheduled                                    │
│                                                         │
│ Session 5 ⭐ DIFFERENT INSTRUCTOR!                       │
│ ├─ Date: July 3                                         │
│ ├─ Instructor: Instructor D 👤 (night specialist)       │
│ ├─ Type: NIGHT_TRAINING                                 │
│ ├─ Duration: 2 hours                                    │
│ └─ Status: Scheduled                                    │
│                                                         │
└─────────────────────────────────────────────────────────┘

Key: Each session has its own instructor!
```

### Data Model

```
SCHEDULES TABLE (Key insight)

┌──────────────────────────────────────────────────────────┐
│ ID │ Learner │ Instructor │ Training_Type  │ Date       │
├────┼─────────┼────────────┼────────────────┼────────────┤
│ 1  │ 003     │ A          │ theory         │ Jun 10     │
│ 2  │ 003     │ A          │ vehicle_basics │ Jun 12     │
│ 3  │ 003     │ C          │ highway        │ Jun 19     │
│ 4  │ 003     │ B          │ city           │ Jun 26     │
│ 5  │ 003     │ D          │ night          │ Jul 3      │
└──────────────────────────────────────────────────────────┘

✓ One learner
✓ Multiple instructors
✓ Different training types
✓ Each schedule independent
```

### Training Phase Visualization

```
PRIYA'S TRAINING JOURNEY

Theory Phase (Week 1)
│
├─ Instructor A 👤
│  └─ Classroom Theory
│     └─ Session: June 10, 1 hour
│        Status: ⏳ Scheduled
│
Vehicle Basics Phase (Week 2)
│
├─ Instructor A 👤 (same)
│  └─ Vehicle Controls & Safety
│     └─ Session: June 12, 2 hours
│        Status: ⏳ Scheduled
│
Road Training Phase (Weeks 3-5)
│
├─ WEEK 3: Highway Training
│  │
│  ├─ Instructor C 👤 (specialist)
│  │  └─ Highway Driving
│  │     └─ Session: June 19, 3 hours
│  │        Status: ⏳ Scheduled
│  │
│  ├─ WEEK 4: City Training
│  │  │
│  │  ├─ Instructor B 👤 (specialist)
│  │  │  └─ City Road Rules & Driving
│  │  │     └─ Session: June 26, 3 hours
│  │  │        Status: ⏳ Scheduled
│  │  │
│  │  └─ WEEK 5: Night Training
│  │     │
│  │     ├─ Instructor D 👤 (specialist)
│  │     │  └─ Night Driving & Safety
│  │     │     └─ Session: July 3, 2 hours
│  │     │        Status: ⏳ Scheduled
│  │     │
│  │     └─ COMPLETED: Ready for RTO Test
│
└─ License Obtained ✅
```

### Frontend Display

```
┌──────────────────────────────────────────────────┐
│ LEARNER: PRIYA                                   │
├──────────────────────────────────────────────────┤
│                                                  │
│ Progress: 33% (3/10 lessons complete)           │
│                                                  │
│ Current Phase: Highway Training                 │
│                                                  │
│ Vehicle: Swift (throughout)                     │
│                                                  │
│ UPCOMING SESSIONS:                               │
├──────────────────────────────────────────────────┤
│                                                  │
│ 1️⃣  Theory (COMPLETED ✓)                        │
│   Instructor: Instructor A                      │
│   Date: June 10                                  │
│                                                  │
│ 2️⃣  Vehicle Basics (COMPLETED ✓)                │
│   Instructor: Instructor A                      │
│   Date: June 12                                  │
│                                                  │
│ 3️⃣  Highway Training (IN PROGRESS ⏳)            │
│   Instructor: Instructor C ← Highway Specialist │
│   Date: June 19                                  │
│   [View Details] [Confirm Attendance]           │
│                                                  │
│ 4️⃣  City Training (SCHEDULED 📅)                │
│   Instructor: Instructor B ← City Specialist    │
│   Date: June 26                                  │
│   [Reschedule] [View Details]                   │
│                                                  │
│ 5️⃣  Night Training (SCHEDULED 📅)               │
│   Instructor: Instructor D ← Night Specialist   │
│   Date: July 3                                   │
│   [Reschedule] [View Details]                   │
│                                                  │
└──────────────────────────────────────────────────┘
```

---

## Comparison Table

```
┌────────────────────────────────────────────────────────────────┐
│ SCENARIO COMPARISON                                            │
├────────────────┬──────────────┬──────────────┬────────────────┤
│ Scenario       │ New Record?  │ Instructor   │ Data Model     │
├────────────────┼──────────────┼──────────────┼────────────────┤
│ 1. Transfer    │ ✅ YES       │ Changes     │ Fresh learner  │
│    to new      │ NEW learner  │ (New school │ record with    │
│    school      │ at School B  │ choice)     │ reference to   │
│                │              │             │ old school     │
├────────────────┼──────────────┼──────────────┼────────────────┤
│ 2. Reassign    │ ❌ NO        │ Updates     │ Update existing│
│    within      │ Same learner │ single      │ record + track │
│    school      │ at School A  │ assignment  │ in history     │
│                │              │             │ table          │
├────────────────┼──────────────┼──────────────┼────────────────┤
│ 3. Multiple    │ ❌ NO        │ Multiple    │ Each schedule  │
│    phases      │ Same learner │ per phase   │ has own        │
│    in one      │ throughout   │ (6 possible)│ instructor     │
│    school      │              │             │                │
└────────────────┴──────────────┴──────────────┴────────────────┘
```

---

## Implementation Priority

```
┌──────────────────────────────────────────────────────┐
│ PHASE A (CRITICAL - Weeks 1-3)                       │
├──────────────────────────────────────────────────────┤
│ ✅ Authorization enforcement (BelongsToSchool trait)  │
│ ⚠️  Add transferred_from fields to learners          │
│ ⚠️  Create learner_instructor_history table          │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│ PHASE B (LEARNER MANAGEMENT - Week 4-6)              │
├──────────────────────────────────────────────────────┤
│ ⏳ Create learners table (full schema)                │
│ ⏳ Create instructors table                           │
│ ⏳ Create schedules table (with training_type)       │
│ ⏳ Implement multiple instructor support              │
│ ⏳ Create instructor history & tracking              │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│ PHASE C (ADVANCED - Week 7+)                         │
├──────────────────────────────────────────────────────┤
│ 📅 Training progress tracking                        │
│ 📅 License tracking                                  │
│ 📅 Performance analytics                             │
│ 📅 RTO integration                                   │
└──────────────────────────────────────────────────────┘
```

---

## Key Database Fields Summary

```
PHASE A (Add to existing)
├─ learners.transferred_from_school_id
├─ learners.transferred_from_learner_id
└─ NEW TABLE: learner_instructor_history

PHASE B (Create new tables)
├─ learners (complete schema)
├─ instructors
├─ schedules (with training_type)
├─ learner_documents
├─ instructor_documents
├─ instructor_certifications
└─ training_progress

PHASE C (Advanced)
├─ learner_license_tracking
├─ learner_progress (by module)
├─ driving_tests
└─ rto_integration
```

---

## Testing Scenarios

```
TEST CASE 1: Transfer Between Schools
├─ Create learner at School A
├─ Create new enrollment at School B
├─ Verify transferred_from fields set
├─ Verify data isolation maintained
└─ Verify NO cross-school access

TEST CASE 2: Reassign Instructor
├─ Create learner with Instructor A
├─ Reassign to Instructor B
├─ Verify history record created
├─ Verify schedules updated appropriately
└─ Verify audit log created

TEST CASE 3: Multiple Instructors
├─ Create schedules with different instructors
├─ Verify all retrieved correctly
├─ Verify training_type field set
├─ Verify learner can't access other school's instructors
└─ Verify frontend shows all instructors
```

---

## Final Implementation Checklist

### For Scenario 1 (Transfer)
- [ ] Add `transferred_from_school_id` to learners migration
- [ ] Add `transferred_from_learner_id` to learners migration
- [ ] Create transfer endpoint in LearnerController
- [ ] Create LearnerTransfer model for tracking
- [ ] Write tests for transfer isolation
- [ ] Update frontend to show transfer option

### For Scenario 2 (Reassign)
- [ ] Create `learner_instructor_history` migration
- [ ] Create LearnerInstructorHistory model
- [ ] Add reassign endpoint in LearnerController
- [ ] Handle schedule updates (flexible approach)
- [ ] Create audit logs for reassignments
- [ ] Update frontend dashboard to show history

### For Scenario 3 (Multiple Instructors)
- [ ] Add `training_type` to schedules migration
- [ ] Update Schedule model with training type support
- [ ] Create instructor assignment per schedule
- [ ] Update frontend to show all instructors
- [ ] Create tests for multiple instructor scenarios
- [ ] Build analytics for phase completion

---

## Documentation References

- Full details: `/docs/LEARNER-SCENARIOS.md`
- Backend assessment: `/docs/BACKEND-ASSESSMENT.md`
- Phase 0 implementation: `/docs/PHASE-A-IMPLEMENTATION.md`
- Architecture patterns: `/docs/ARCHITECTURE.md`
