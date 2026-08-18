# RBAC Matrix

Version 1.0

Roles:

1. Platform Admin
2. School Owner
3. School Manager
4. Instructor
5. Learner

---

SCHOOL MANAGEMENT

Action                         PA  SO  SM  IN  LE

Create School                  ✔   ✖   ✖   ✖   ✖
Approve School                 ✔   ✖   ✖   ✖   ✖
Update School Profile          ✖   ✔   ✔   ✖   ✖
View School Analytics          ✖   ✔   ✔   ✖   ✖

---

LEAD MANAGEMENT

Action                         PA  SO  SM  IN  LE

View Leads                     ✔   ✔   ✔   ✖   ✖
Create Lead                    ✔   ✔   ✔   ✖   ✖
Convert Lead                   ✔   ✔   ✔   ✖   ✖
Delete Lead                    ✔   ✔   ✖   ✖   ✖

---

LEARNER MANAGEMENT

Action                         PA  SO  SM  IN  LE

View Learners                  ✔   ✔   ✔   Assigned Self
Create Learner                 ✔   ✔   ✔   ✖   ✖
Update Learner                 ✔   ✔   ✔   Assigned Self
Upload Documents               ✖   ✔   ✔   ✖   ✔
Verify Documents               ✖   ✔   ✔   ✖   ✖

---

INSTRUCTOR MANAGEMENT

Action                         PA  SO  SM  IN  LE

Create Instructor              ✔   ✔   ✔   ✖   ✖
Update Instructor              ✔   ✔   ✔   Self ✖
Assign Instructor              ✖   ✔   ✔   ✖   ✖
View Instructor Analytics      ✖   ✔   ✔   Self ✖

---

SCHEDULING

Action                         PA  SO  SM  IN  LE

Create Schedule                ✖   ✔   ✔   ✖   ✖
Update Schedule                ✖   ✔   ✔   Assigned ✖
View Schedule                  ✖   ✔   ✔   Assigned Self
Cancel Schedule                ✖   ✔   ✔   Assigned ✖

---

TRAINING PROGRESS

Action                         PA  SO  SM  IN  LE

View Progress                  ✔   ✔   ✔   Assigned Self
Update Progress                ✖   ✔   ✔   Assigned ✖
Approve Completion             ✖   ✔   ✔   ✖   ✖

---

VEHICLE MANAGEMENT

Action                         PA  SO  SM  IN  LE

Create Vehicle                 ✖   ✔   ✔   ✖   ✖
Assign Vehicle                 ✖   ✔   ✔   ✖   ✖
View Vehicles                  ✖   ✔   ✔   ✔   ✖

---

GOOGLE BUSINESS

Action                         PA  SO  SM  IN  LE

Connect Profile                ✖   ✔   ✔   ✖   ✖
View Analytics                 ✖   ✔   ✔   ✖   ✖
Manage Reviews                 ✖   ✔   ✔   ✖   ✖
Create Posts                   ✖   ✔   ✔   ✖   ✖

---

MESSAGING

Action                         PA  SO  SM  IN  LE

Send Messages                  ✔   ✔   ✔   ✔   ✔
Receive Messages               ✔   ✔   ✔   ✔   ✔

---

PLATFORM ADMINISTRATION

Action                         PA  SO  SM  IN  LE

Manage Subscriptions           ✔   ✖   ✖   ✖   ✖
Manage Plans                   ✔   ✖   ✖   ✖   ✖
View Platform Analytics        ✔   ✖   ✖   ✖   ✖
Moderate Reviews               ✔   ✖   ✖   ✖   ✖
