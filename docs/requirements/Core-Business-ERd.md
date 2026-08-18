# Core Business ERD

Version 1.0

---

PLATFORM
│
├── SCHOOLS
│
├── USERS
│
└── SUBSCRIPTIONS

---

SCHOOL
│
├── BRANCHES (Future)
├── INSTRUCTORS
├── LEARNERS
├── VEHICLES
├── LEADS
├── REVIEWS
├── GOOGLE_BUSINESS_PROFILE
└── SCHEDULES

---

LEADS

lead_id
school_id

name
mobile
email

source
status

converted_to_learner_id

Created At

Relationship:

Lead
→ may become →
Learner

---

LEARNERS

learner_id
school_id

name
mobile
email

status

license_status

assigned_instructor_id

assigned_vehicle_id

package_id

Relationship:

Learner
→ many schedules

Learner
→ many documents

Learner
→ one instructor

---

LEARNER_DOCUMENTS

document_id
learner_id

document_type

status

expiry_date

file_url

---

INSTRUCTORS

instructor_id
school_id

user_id

name

license_number

experience

availability_status

Relationship:

Instructor
→ many learners

Instructor
→ many schedules

Instructor
→ many ratings

---

VEHICLES

vehicle_id
school_id

registration_number

vehicle_type

transmission_type

status

Relationship:

Vehicle
→ many schedules

---

SCHEDULES

schedule_id

learner_id

instructor_id

vehicle_id

date

start_time

end_time

status

Relationship:

Schedule
→ belongs to learner

Schedule
→ belongs to instructor

Schedule
→ belongs to vehicle

---

ATTENDANCE

attendance_id

schedule_id

learner_status

trainer_status

---

TRAINING_PROGRESS

progress_id

learner_id

module

completion_percentage

notes

updated_by_instructor

---

DRIVING_TESTS

test_id

learner_id

rto_id

test_date

status

---

RTO_OFFICES

rto_id

city_id

office_name

address

---

GOOGLE_BUSINESS_PROFILE

gbp_id

school_id

profile_id

rating

reviews_count

visibility_score

---

MESSAGES

message_id

sender_user_id

receiver_user_id

message

attachment

---

NOTIFICATIONS

notification_id

user_id

type

message

status

---

USERS

user_id

role_id

school_id

email

mobile

status
