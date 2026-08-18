# DriveIQ — Master Project Plan & Roadmap

**Version:** 1.0  
**Date:** June 9, 2026  
**Owner:** Ratnesh Gupta  
**Status:** Active — canonical project reference

This is the **single source of truth** for project scope, phasing, requirements, and status.
All previous standalone PRDs, roadmaps, and assessment docs are superseded by this document.

---

## Table of Contents

1. [Product Vision](#1-product-vision)
2. [Current State (What Exists Today)](#2-current-state)
3. [Architecture & Technical Stack](#3-architecture--technical-stack)
4. [User Roles & Permissions](#4-user-roles--permissions)
5. [Phase 0 — Auth & Security Foundation](#5-phase-0--auth--security-foundation)
6. [Phase 1 — Geo-Intelligent Search](#6-phase-1--geo-intelligent-search)
7. [Phase 2 — Trust & Verification](#7-phase-2--trust--verification)
8. [Phase 3 — School Management Foundation](#8-phase-3--school-management-foundation)
9. [Phase 4 — Marketplace Monetization](#9-phase-4--marketplace-monetization)
10. [Phase 5 — Instructor Management](#10-phase-5--instructor-management)
11. [Phase 6 — Instructor Advanced Operations](#11-phase-6--instructor-advanced-operations)
12. [Phase 7 — Learner Management](#12-phase-7--learner-management)
13. [Phase 8 — Learner Progress & Attendance](#13-phase-8--learner-progress--attendance)
14. [Phase 9 — Internal Messaging](#14-phase-9--internal-messaging)
15. [Phase 10 — Analytics & Insights](#15-phase-10--analytics--insights)
16. [Public Pages & Features](#16-public-pages--features)
17. [Timeline & Dependency Map](#17-timeline--dependency-map)
18. [Go-to-Market Strategy](#18-go-to-market-strategy)
19. [Business Model & Pricing](#19-business-model--pricing)
20. [Success Metrics](#20-success-metrics)
21. [Supporting Documents](#21-supporting-documents)

---

## 1. Product Vision

### What is DriveIQ?

A **hyperlocal, geo-intelligent marketplace** that connects driving learners with verified driving schools — evolving into a **Driving School Operating System (DSOS)** for complete school operations.

**Launch city:** Pune, India  
**Initial localities:** Baner, Hinjewadi, Wakad, Hadapsar, Kothrud, Pimpri-Chinchwad

### Platform Evolution

```
Phase 0-2:  Marketplace (Discovery + Lead Generation)
Phase 3-4:  Marketplace + School Operations Platform
Phase 5-6:  + Instructor Management
Phase 7-8:  + Learner Lifecycle Management
Phase 9-10: + Communication + Analytics = Full DSOS
```

### Strategic Sequencing Rationale

```
Marketplace (Discovery)
    ↓
School Management (Operations Foundation)
    ↓
Instructor Management (Team Operations)
    ↓
Learner Management (Training Lifecycle)
```

**Why this order:**
- Schools must control profile, settings, and team FIRST
- Instructors cannot be managed without stable school infrastructure
- Learners require both school + instructor context
- Dependencies flow downward: School → Instructors → Learners
- Revenue alignment: each phase unlocks higher subscription tiers

### Core Differentiators

- **Geo-Intelligent Search:** PostGIS radius discovery (2km/5km/10km)
- **Trust-Driven Comparison:** Verified schools, structured attribute comparison
- **Locality-Focused SEO:** Programmatic landing pages for Pune localities
- **Lead Generation Engine:** WhatsApp, callback, form inquiry channels
- **Women-Friendly Filters:** Women instructor and women-only training filters
- **School Comparison Engine:** Side-by-side structured attribute comparison (vs Google/Justdial)

---

## 2. Current State

### What's Implemented

**Backend (Laravel 13.8 + PostgreSQL):**
- 6 Models: School, User, Inquiry, Review, DrivePackage, Locality
- 7 Controllers: School, Inquiry, Review, Package, Locality, Auth, Stats
- Sanctum token auth (basic registration/login)
- Haversine geo-distance calculation
- Role middleware (role field on users table)
- 15 database migrations

**Frontend (React 19 + Vite + Tailwind 4):**
- 22 page components (home, search, school-detail, compare, dashboard/*, admin/*)
- ShadCN + Radix UI component library
- Zustand stores (location, regulations)
- TanStack Query for data fetching
- Framer Motion animations
- Zod validation with generated schemas

**Working Features:**
- Homepage with school listing
- School search with basic filters
- School detail pages
- Locality detail pages
- Inquiry submission (form + WhatsApp deeplinks)
- Reviews (create/list, with `auth:sanctum` + throttle on POST)
- School dashboard: overview, leads, profile, packages, reviews, analytics
- Admin panel: overview, schools, reviews, localities, users (placeholder)
- Auth routes: login, register
- Comparison page skeleton
- Driving rules page

### Critical Gaps (Must Fix Before Launch)

| Gap | Risk | Phase |
|-----|------|-------|
| NO `BelongsToSchool` trait — queries not auto-scoped | **DATA LEAK** | 0 |
| NO server-side authorization in controllers | **DATA LEAK** | 0 |
| Users table missing `school_id` foreign key | **BROKEN ISOLATION** | 0 |
| No rate limiting on most endpoints | **ABUSE** | 0 |
| Review eligibility not enforced server-side | **SPAM** | 2 |
| No audit logging | **COMPLIANCE** | 0 |
| 25+ domain models missing (Learner, Instructor, Vehicle, Schedule, etc.) | **INCOMPLETE** | 5-8 |

---

## 3. Architecture & Technical Stack

### Architecture Pattern

**Shared Database + Logical School Isolation** (NOT multi-tenant per-database)

```
Platform → Single PostgreSQL Database → All Schools + Users
    Filtered by school_id + RBAC at application layer
```

### Critical Security Rule

Every operational query MUST be scoped to `school_id`. Enforced via `BelongsToSchool` trait with Laravel Global Scopes.

### Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Frontend** | React + Vite | 19 / 7.3 |
| **Styling** | TailwindCSS | 4.1 |
| **UI Components** | ShadCN + Radix UI | Latest |
| **State** | Zustand | 5.0 |
| **Data Fetching** | TanStack Query | 5.90 |
| **Routing** | Wouter | 3.3 |
| **Forms** | React Hook Form + Zod | 7.55 / 3.25 |
| **Animations** | Framer Motion | 12.23 |
| **Backend** | Laravel | 13.8 |
| **Auth** | Laravel Sanctum | 4.3 |
| **Database** | PostgreSQL | 13+ |
| **Geo** | PostGIS (planned) | — |
| **Cache/Queue** | Redis (planned) | — |
| **Testing** | PHPUnit | 12.5 |

### Database Organization (~60-70 tables at full build)

| Category | Table Count | Phases |
|----------|------------|--------|
| Global Platform (users, roles, cities, etc.) | ~15 | 0-3 |
| School Configuration | ~10 | 3 |
| Lead Management | ~8 | 0, 3 |
| Marketplace (reviews, photos, featured) | ~8 | 0-2 |
| Instructor Management | ~8 | 5-6 |
| Learner Management | ~10 | 7-8 |
| Scheduling | ~6 | 6 |
| Vehicle Management | ~6 | 6 |
| Communication | ~6 | 9 |
| Google Business (future) | ~5 | 4+ |

### Core Entity Relationships

```
School (center of platform)
    ├── SchoolAdmin/Team (1:many) — Phase 3
    ├── Instructor (1:many) — Phase 5
    ├── Learner (1:many) — Phase 7
    ├── Vehicle (1:many) — Phase 6
    ├── Lead/Inquiry (1:many) — Phase 0
    ├── Review (1:many) — Phase 0
    ├── DrivePackage (1:many) — Phase 0
    ├── Schedule (1:many) — Phase 6
    └── Subscription (1:1) — Phase 4

Lead → converts to → Learner (Phase 7)
Learner → assigned to → Instructor + Vehicle (Phase 7)
Schedule → links → Learner + Instructor + Vehicle (Phase 6)
```

---

## 4. User Roles & Permissions

### 5 Core Roles

| Role | Code | Scope | Phase |
|------|------|-------|-------|
| Platform Admin | `platform_admin` | System-wide control | 0 |
| School Owner | `school_owner` | Full school access | 3 |
| School Manager | `school_manager` | Day-to-day operations | 3 |
| Instructor | `instructor` | Assigned learners/sessions | 5 |
| Learner | `learner` | Own training data | 7 |

### Permission Matrix

| Domain | Platform Admin | School Owner | School Manager | Instructor | Learner |
|--------|:---:|:---:|:---:|:---:|:---:|
| School Profile | Approve | Manage | Manage | — | — |
| Lead/Inquiry Management | All | View/Convert | View/Convert | — | — |
| Learner Management | All | Manage All | Manage All | View Assigned | Self Only |
| Instructor Management | All | Manage All | Manage All | Self | — |
| Scheduling | All | Create/Manage | Create/Manage | View Assigned | View Self |
| Vehicle Management | All | Manage All | Manage All | View Available | — |
| Reviews & Ratings | Moderate | View Own School | View Own School | — | Create |
| Subscriptions | Manage All | Manage Own | View | — | — |
| Analytics | Platform-wide | School-level | School-level | Own Stats | Own Progress |
| Platform Administration | Full | — | — | — | — |

### Laravel Implementation Pattern

```php
// BelongsToSchool trait auto-scopes all queries
trait BelongsToSchool {
    protected static function bootBelongsToSchool() {
        static::addGlobalScope('school', function (Builder $builder) {
            if ($school_id = auth()->user()?->school_id) {
                $builder->where('school_id', $school_id);
            }
        });
    }
}

// Admin override
$allData = Model::withoutGlobalScope('school')->get();
```

**Models using BelongsToSchool:** Instructor, Learner, Vehicle, Schedule, Lead/Inquiry, Review, DrivePackage, Document, Message, Attendance, and all other school-scoped tables.

---

## 5. Phase 0 — Auth & Security Foundation

**Goal:** Make backend production-safe  
**Timeline:** 2-4 weeks  
**Priority:** CRITICAL — must complete before any external testing  
**Status:** In Progress (partial auth + review throttle done)

### Scope

#### 0.1 Authorization Enforcement (Week 1)
- [ ] Add `school_id` to users table (migration)
- [ ] Create `BelongsToSchool` trait with Global Scope
- [ ] Apply trait to: Inquiry, Review, DrivePackage, School (where applicable)
- [ ] Update all controllers to enforce school ownership on mutations
- [ ] Write isolation tests (user from school A cannot see school B data)

#### 0.2 Authentication Hardening (Week 2)
- [ ] Harden Sanctum token lifecycle (expiry, refresh)
- [ ] Implement role-based middleware enforcement on all protected routes
- [ ] Add `school_id` assignment during registration/admin-invite flow
- [ ] Protect admin-only routes (school approval, user management, review moderation)

#### 0.3 Rate Limiting & Anti-Spam (Week 2-3)
- [x] Rate limit on `POST /reviews` (10 per minute) — DONE
- [x] Rate limit on auth endpoints — DONE
- [ ] Rate limit on `POST /inquiries` (5 per minute per IP)
- [ ] Rate limit on `POST /schools` (admin only)
- [ ] CAPTCHA or honeypot on public inquiry form

#### 0.4 Audit & Event Logging (Week 3-4)
- [ ] Create `audit_logs` table (actor, action, resource, old_value, new_value, timestamp)
- [ ] Log: lead status changes, review moderation, admin actions, school profile updates
- [ ] Immutable audit log (no deletes)

### Acceptance Criteria
- No unauthenticated user can mutate data
- Users from school A cannot read/write school B data
- Rate limiting active on all public-facing mutation endpoints
- Audit trail exists for all admin and school-level actions

### Exit Criteria
Protected APIs require valid auth; abuse controls active; school isolation enforced.

---

## 6. Phase 1 — Geo-Intelligent Search

**Goal:** Core marketplace differentiator — "near me" discovery  
**Timeline:** 3-5 weeks  
**Depends on:** Phase 0 (auth baseline)

### Scope

#### 1.1 PostGIS Setup & Schema
- [ ] Enable PostGIS extension on PostgreSQL
- [ ] Add `geography` column to schools table (or use existing lat/lng with ST_DWithin)
- [ ] Create spatial index on school locations
- [ ] Seed Pune school locations with accurate coordinates

#### 1.2 Radius Search API
- [ ] `GET /api/schools?lat=X&lng=Y&radius=2` (2km, 5km, 10km options)
- [ ] ST_DWithin queries replacing current Haversine (more accurate, index-friendly)
- [ ] Fallback to text-based locality search when geo unavailable

#### 1.3 Geo Ranking v1
- [ ] Ranking formula: `distance_score + rating_score + review_count_score + verified_bonus + premium_bonus`
- [ ] Weights documented and tunable
- [ ] Deterministic results (same input = same output)

#### 1.4 Frontend Geo Integration
- [ ] Browser geolocation consent flow ("Allow location for nearby schools")
- [ ] "Near Me" entry point on homepage
- [ ] Radius selector on search page (2km / 5km / 10km toggle)
- [ ] Google Maps embed on school detail pages
- [ ] Distance badge on school cards ("1.2 km away")

#### 1.5 Locality Landing Pages
- [ ] Programmatic SEO templates for Pune localities
- [ ] `/{locality-slug}` routes with structured data (LocalBusiness schema)
- [ ] Schools listed by proximity within locality

### Acceptance Criteria
- "Near me" search returns schools sorted by distance within selected radius
- Ranking is documented and reproducible
- Locality pages are indexed with proper metadata

### Exit Criteria
Near-me and radius filters produce deterministic geo results; ranking documented.

### Detailed Feature Spec
See `docs/Features/Geo-Aware.md` for the full geo-aware driving rules & government resources module specification (40+ pages covering content structure, components, animations, SEO, accessibility).

---

## 7. Phase 2 — Trust & Verification

**Goal:** Build confidence in marketplace listings  
**Timeline:** 2-3 weeks  
**Depends on:** Phase 0 (auth), Phase 1 (geo — schools have locations)

### Scope

#### 2.1 Verification Model
- [ ] Verification fields on schools: `phone_verified`, `business_verified`, `location_verified`, `premium_verified`
- [ ] Verification workflow: school submits docs → admin reviews → badge granted
- [ ] Verification badges displayed on search results, school detail, comparison

#### 2.2 Review Eligibility Enforcement
- [ ] Server-side check: reviewer must have submitted inquiry OR be enrolled learner
- [ ] Block ineligible reviews at API level (not just frontend)
- [ ] Eligible reason stored: `inquiry_id` or `learner_id`

#### 2.3 Abuse Reporting & Moderation
- [ ] `POST /api/reviews/{id}/report` — abuse report endpoint
- [ ] Moderation queue for admin (pending/approved/rejected reviews)
- [ ] Auto-flag reviews with high report count
- [ ] Admin moderation dashboard with bulk actions

#### 2.4 Trust Signals in UI
- [ ] Verification badges on school cards (search results)
- [ ] Trust score or verification level on school detail
- [ ] "Verified" filter on search page
- [ ] Review count and recency displayed prominently

### Acceptance Criteria
- Ineligible reviews blocked server-side (API returns 403)
- Abuse reports create actionable moderation queue items
- Verification badges visible in search and detail views

### Exit Criteria
Review eligibility blocking server-side; abuse reports actionable; verification badges visible.

---

## 8. Phase 3 — School Management Foundation

**Goal:** Complete school operations infrastructure  
**Timeline:** 4-6 weeks  
**Depends on:** Phase 0 (auth + RBAC)  
**Unlocks:** Phase 4 (Monetization), Phase 5 (Instructors)

**SCOPE NOTE:** Phase 3 is focused on **profile, team, settings, and dashboard**. Training booking and payment flows are deferred to Phases 5-7 where instructor and learner infrastructure exists.

### Scope

#### 3.1 School Profile Enhancement
- [ ] Profile completeness scoring (% with nudges for missing fields)
- [ ] Logo + banner image uploads
- [ ] Rich description editor
- [ ] Service area definition (localities served, pickup radius)
- [ ] Operational hours (weekday, Saturday, Sunday, holiday)
- [ ] Contact methods (phone, WhatsApp, email, website)
- [ ] Language options available
- [ ] Vehicle types offered flags
- [ ] Convenience flags: `has_female_instructor`, `has_pickup_drop`, `has_weekend_batches`, `has_automatic_car`, etc.

**New School Fields (for comparison engine):**

| Field | Type | Description |
|-------|------|-------------|
| `has_female_instructor` | boolean | Women instructor available |
| `has_pickup_drop` | boolean | Pickup/drop service |
| `pickup_radius_km` | integer | Pickup service radius |
| `has_weekend_batches` | boolean | Weekend training |
| `has_evening_batches` | boolean | Evening batch |
| `has_automatic_car` | boolean | Automatic transmission |
| `has_manual_car` | boolean | Manual transmission |
| `has_two_wheeler` | boolean | Two-wheeler training |
| `languages_spoken` | json | Languages offered |
| `license_assistance` | boolean | License process help |
| `has_simulator` | boolean | Simulator training |
| `years_in_business` | integer | Establishment years |
| `total_instructors` | integer | Instructor count |

#### 3.2 Admin & Team Management
- [ ] `school_admins` table (school_id, user_id, role, invited_by, accepted_at)
- [ ] Invite flow: owner sends email/SMS invite → invitee registers → linked to school
- [ ] School-level roles: Owner, Manager (subset permissions)
- [ ] Add/remove/deactivate team members
- [ ] Permission enforcement per school role

#### 3.3 School Settings & Configuration
- [ ] `school_settings` table (JSON key-value per school)
- [ ] Notification preferences (email, SMS, in-app toggles)
- [ ] Lead auto-assignment rules (future)
- [ ] Integration settings placeholder (Google Business API keys, etc.)
- [ ] Timezone and locale settings

#### 3.4 School Operations Dashboard
- [ ] Key metrics cards: total leads, inquiries this month, response rate, conversion rate
- [ ] Recent activity log (new inquiries, reviews, profile views)
- [ ] Pending tasks/actions (unresponded leads, pending reviews)
- [ ] Quick stats: this month vs. last month comparison
- [ ] Profile completeness bar with "complete your profile" CTA

#### 3.5 Audit Logging
- [ ] All admin actions logged (immutable `audit_logs` table)
- [ ] Who changed what, when, old value → new value
- [ ] Viewable by school owner in settings
- [ ] Platform admin can view all audit logs

### Data Model

```
school_admins:
  id, school_id, user_id, role (owner|manager), 
  invited_by, invited_at, accepted_at, status, created_at

school_settings:
  id, school_id, settings (JSON), created_at, updated_at

audit_logs:
  id, school_id, actor_id, action, resource_type, resource_id,
  old_value (JSON), new_value (JSON), ip_address, created_at
```

### API Endpoints

```
POST   /api/schools/{id}/team           — invite team member
GET    /api/schools/{id}/team           — list team members
DELETE /api/schools/{id}/team/{userId}  — remove team member
PATCH  /api/schools/{id}/team/{userId}  — update role

GET    /api/schools/{id}/settings       — get settings
PATCH  /api/schools/{id}/settings       — update settings

GET    /api/schools/{id}/audit-log      — get audit entries
GET    /api/schools/{id}/dashboard      — dashboard metrics
```

### Acceptance Criteria
- School profile completeness score calculates correctly
- Team members can be invited, accepted, and role-assigned
- Audit log captures all admin mutations
- Dashboard shows real-time operational metrics

### Exit Criteria
Schools can manage profile, settings, and admin team end-to-end. Training booking and payment flows remain deferred.

---

## 9. Phase 4 — Marketplace Monetization

**Goal:** Enable revenue-generating listings  
**Timeline:** 3-4 weeks  
**Depends on:** Phase 3 (school profile + settings)  
**Can run parallel with Phase 5**

### Scope

#### 4.1 Subscription Tiers
- [ ] `subscriptions` table: school_id, plan, status, starts_at, expires_at, auto_renew
- [ ] `plans` table: name, price, features (JSON), limits

| Tier | Price/month | Features |
|------|------------|----------|
| Basic (Free) | Rs 0 | Listing, basic leads, limited profile |
| Featured | Rs 999-2999 | Enhanced profile, lead tracking, review monitoring, visibility dashboard |
| Premium | Rs 4999 | Top placement, sponsored badges, premium ranking boost, analytics |
| Enterprise | Rs 14,999+ | Multi-branch, fleet management, advanced reporting, API access |

#### 4.2 Listing Tier Logic
- [ ] Free listings: appear in search with standard ranking
- [ ] Featured listings: ranking boost, highlighted card in search
- [ ] Premium listings: top placement, sponsored badge, homepage featured section

#### 4.3 Premium Ranking Controls
- [ ] Admin control plane for featured inventory (how many sponsored slots per page)
- [ ] Campaign windows (featured placement for X days)
- [ ] School-side subscription status visibility + renewal reminders

#### 4.4 Admin Monetization Dashboard
- [ ] Active subscriptions by tier
- [ ] Revenue tracking (MRR, churn)
- [ ] Expiring subscriptions alerts
- [ ] Featured slot utilization

### Exit Criteria
Premium visibility logic operational; free + paid listing states both work; admin can manage inventory.

---

## 10. Phase 5 — Instructor Management

**Goal:** Enable schools to manage trainer teams  
**Timeline:** 4-6 weeks  
**Depends on:** Phase 3 (school management foundation)  
**Unlocks:** Phase 6 (scheduling), Phase 7 (learner assignment)

### Scope

#### 5.1 Instructor Profiles
- [ ] `instructors` table: school_id, user_id, name, mobile, email, gender, dob, address
- [ ] Employment info: employee_id, joining_date, status, employment_type (full-time/part-time/contract)
- [ ] Driving credentials: license_number, license_category, license_expiry, years_experience
- [ ] Training skills: vehicle types (car, automatic, motorcycle, scooter, commercial), women_instructor flag
- [ ] Languages spoken
- [ ] Public profile visibility controls (opt-in/opt-out of marketplace display)

#### 5.2 Instructor Documents
- [ ] `instructor_documents` table: instructor_id, type, file_path, status, verified_by, verified_at
- [ ] Document types: driving license, Aadhaar, PAN, photo, certificates
- [ ] Status tracking: pending → uploaded → verified → rejected

#### 5.3 Instructor Authentication
- [ ] School admin creates instructor account (admin-initiated, not self-signup)
- [ ] OTP sent to instructor phone/email for activation
- [ ] Instructor login with email/mobile + password (or OTP)
- [ ] Protected routes: `/instructor/dashboard`, `/instructor/learners`, `/instructor/schedule`, etc.
- [ ] Depends on Phase 0 auth infrastructure

#### 5.4 Instructor Dashboard (MVP)
- [ ] Overview cards: today's sessions, upcoming sessions, assigned learners, attendance %
- [ ] Learner roster: list of assigned learners (name, phone, vehicle type, package, progress)
- [ ] Basic session attendance marking (present/absent/rescheduled/cancelled)
- [ ] Simple trainer notes (text field per learner per session)

#### 5.5 School Admin — Instructor Management
- [ ] View all instructors + status (active/inactive)
- [ ] Assign instructors to school
- [ ] View trainer performance (sessions completed, avg rating)
- [ ] Manage trainer availability (mark as active/inactive)
- [ ] Trainer workload overview

#### 5.6 Public Marketplace Integration
- [ ] "Meet Our Trainers" section on school detail page
- [ ] Trainer cards: photo, name, experience, languages, specialization, rating
- [ ] Learner ratings of trainers (1-5 stars, after session completion)
- [ ] Search filter: "Female Instructor available"
- [ ] Trainer cards visible only for opted-in instructors

### Data Model

```
instructors:
  id, school_id, user_id, name, mobile, email, gender, dob, address,
  employee_id, joining_date, status (active|inactive|terminated),
  employment_type (full_time|part_time|contract),
  license_number, license_category, license_expiry, years_experience,
  skills (JSON), languages (JSON), women_instructor (bool),
  public_visible (bool), rating_average, total_learners_trained,
  created_at, updated_at

instructor_documents:
  id, instructor_id, school_id, type, file_path, file_name,
  status (pending|uploaded|verified|rejected),
  verified_by, verified_at, created_at, updated_at
```

### API Endpoints

```
GET    /api/schools/{id}/instructors          — list instructors
POST   /api/schools/{id}/instructors          — create instructor
GET    /api/instructors/{id}                   — instructor profile
PATCH  /api/instructors/{id}                   — update profile
DELETE /api/instructors/{id}                   — deactivate

POST   /api/instructors/{id}/documents        — upload document
GET    /api/instructors/{id}/documents        — list documents
PATCH  /api/instructors/{id}/documents/{docId} — verify/reject

GET    /api/schools/{slug}/trainers           — public trainer list (marketplace)
```

### Acceptance Criteria
- Schools can onboard trainers with complete profiles
- Instructors can log in and see their dashboard
- Learners see trainer profiles on school detail page
- Ratings functional (post-session only)

### Exit Criteria
Schools can onboard trainers; learners see trainer profiles; ratings functional.

---

## 11. Phase 6 — Instructor Advanced Operations

**Goal:** Scheduling, capacity management, and operational controls  
**Timeline:** 4-5 weeks  
**Depends on:** Phase 5 (instructor profiles exist)

### Scope

#### 6.1 Scheduling System
- [ ] `schedules` table: school_id, learner_id, instructor_id, vehicle_id, session_date, start_time, end_time, pickup_location, status
- [ ] Calendar view (month/week/day)
- [ ] Create/edit sessions (date, time, learner, vehicle, pickup)
- [ ] Rescheduling workflow (allowed 1hr to 10min before session start)

#### 6.2 Conflict Detection
- [ ] Prevent overlapping session assignments per instructor
- [ ] Warn when trainer at capacity (max sessions/day configurable)
- [ ] Suggest alternative trainers if conflict exists

#### 6.3 Availability Management
- [ ] Trainers set working days/hours
- [ ] Leave requests (pending/approved/rejected)
- [ ] Admin approves/rejects leave
- [ ] Calendar reflects availability + leave

#### 6.4 Attendance Tracking
- [ ] Trainer marks attendance: present, absent, rescheduled, cancelled
- [ ] Auto-sync with session completion status
- [ ] Attendance report (for payroll reference)

#### 6.5 Session Notes & Feedback
- [ ] Trainer submits post-session summary
- [ ] Notes on learner performance, areas for improvement
- [ ] Visible to school admin (and to learner in Phase 7)

#### 6.6 Vehicle Management
- [ ] `vehicles` table: school_id, registration_number, type, transmission, fuel_type, status
- [ ] Vehicle documents: insurance, pollution cert, registration
- [ ] Vehicle availability tracking
- [ ] Vehicle assignment to sessions

### Data Model

```
schedules:
  id, school_id, learner_id, instructor_id, vehicle_id,
  session_date, start_time, end_time, pickup_location,
  status (scheduled|completed|cancelled|rescheduled),
  notes, created_at, updated_at

attendance:
  id, schedule_id, school_id, learner_id, instructor_id,
  status (present|absent|rescheduled|cancelled),
  marked_by, marked_at, created_at

vehicles:
  id, school_id, registration_number, type, transmission,
  fuel_type, status (active|maintenance|retired),
  created_at, updated_at

vehicle_documents:
  id, vehicle_id, school_id, type, file_path,
  expiry_date, status, created_at, updated_at

leave_requests:
  id, instructor_id, school_id, start_date, end_date,
  reason, status (pending|approved|rejected),
  reviewed_by, reviewed_at, created_at
```

### Exit Criteria
Schedules prevent conflicts; leave management operational; session history tracked; vehicles assigned to sessions.

---

## 12. Phase 7 — Learner Management

**Goal:** Complete learner enrollment and lifecycle management  
**Timeline:** 4-6 weeks  
**Depends on:** Phase 5 (instructors exist for assignment), Phase 6 preferred (scheduling exists)

### Scope

#### 7.1 Lead Conversion
- [ ] "Convert to Learner" one-click on inquiry record
- [ ] Auto-populate learner record from inquiry data
- [ ] Lead status: new → contacted → interested → enrolled → converted

#### 7.2 Learner Profiles
- [ ] `learners` table: school_id, name, mobile, email, gender, dob, address, emergency_contact
- [ ] Training info: vehicle_type, package_id, start_date, expected_completion, assigned_instructor_id
- [ ] License info: learner_license_number, issue_date, expiry_date, permanent_license_status
- [ ] Status: active, inactive, completed, suspended

#### 7.3 Document Management
- [ ] `learner_documents` table: learner_id, type, file_path, status, expiry_date
- [ ] Required docs: Aadhaar, PAN, passport photo, learner license, medical certificate
- [ ] Status tracking: pending → uploaded → verified → rejected
- [ ] Expiry alerts (learner license expiring soon)

#### 7.4 Training Assignment
- [ ] School admin assigns: learner → instructor → vehicle
- [ ] Assignment triggers notification to instructor
- [ ] Assignment history tracked

#### 7.5 Package Purchase & Payments
- [ ] Online payment integration for learner packages
- [ ] Package purchase flow (select package → pay → enrolled)
- [ ] Invoice/receipt history
- [ ] Payment status tracking

#### 7.6 Learner Portal (Basic)
- [ ] Learner login (mobile + OTP)
- [ ] View assigned trainer + vehicle
- [ ] View upcoming sessions (from Phase 6 schedules)
- [ ] View document status
- [ ] Submit missing documents
- [ ] View training progress

#### 7.7 Notifications
- [ ] Session reminders (24h, 2h before)
- [ ] Missing document alerts
- [ ] Document verification status updates
- [ ] Package expiry warnings

### Learner Lifecycle

```
Lead (inquiry) → Convert → Learner Record Created
    → Document Collection
    → Trainer + Vehicle Assignment
    → Training Schedule (from Phase 6)
    → Training Progress (Phase 8)
    → Driving Test → License Obtained → Completed
```

### Learner Transfer Scenarios (Reference)

Three scenarios for learner mobility (see `docs/LEARNER-SCENARIOS.md` for full detail):

1. **Learner moves to different location** → Fresh Start: new learner record at new school (recommended for MVP)
2. **Reassign instructor (same school)** → Update `assigned_instructor_id` + log history
3. **Multiple instructors in phases** → Schedule-based assignment (instructor per session, not per learner)

### Data Model

```
learners:
  id, school_id, user_id, name, mobile, email, gender, dob, address,
  emergency_contact, vehicle_type, package_id, start_date,
  expected_completion_date, assigned_instructor_id, assigned_vehicle_id,
  learner_license_number, license_issue_date, license_expiry_date,
  permanent_license_status, status (active|inactive|completed|suspended),
  converted_from_inquiry_id, created_at, updated_at

learner_documents:
  id, learner_id, school_id, type, file_path, file_name,
  status (pending|uploaded|verified|rejected), expiry_date,
  verified_by, verified_at, created_at, updated_at
```

### Exit Criteria
Schools can onboard learners end-to-end; lead conversion works; learners see training status; documents tracked.

---

## 13. Phase 8 — Learner Progress & Attendance

**Goal:** Track training progress and readiness  
**Timeline:** 3-4 weeks  
**Depends on:** Phase 7 (learners exist), Phase 6 (sessions exist)

### Scope

#### 8.1 Progress Tracking
- [ ] Define training skills: vehicle controls, parking, reverse, traffic navigation, night driving, highway driving
- [ ] Trainer updates skill percentage after each session
- [ ] Learner sees progress bar per skill
- [ ] Overall completion percentage

#### 8.2 Session History
- [ ] Learner views past sessions with dates, trainer, notes
- [ ] Trainer feedback visible to learner
- [ ] Attendance certificate download (optional)

#### 8.3 Driving Test Tracking
- [ ] Test date, RTO location, attempt number
- [ ] Status: scheduled → completed → passed/failed
- [ ] Link to learner's license upgrade journey
- [ ] RTO directory integration (from Geo-Aware module)

### Data Model

```
training_progress:
  id, learner_id, school_id, skill_name, percentage,
  updated_by (instructor_id), session_id, created_at, updated_at

driving_tests:
  id, learner_id, school_id, test_date, rto_id, attempt_number,
  status (scheduled|completed|passed|failed), notes, created_at
```

### Exit Criteria
Complete training history visible; progress tracked per skill; test results recorded.

---

## 14. Phase 9 — Internal Messaging

**Goal:** Reduce WhatsApp dependency for operational communication  
**Timeline:** 2-3 weeks  
**Depends on:** Phase 5 (instructors), Phase 7 (learners)

### Scope

#### 9.1 Message Types
- School ↔ Instructor
- Instructor ↔ Learner
- School ↔ Learner

#### 9.2 MVP Features
- Text-only messages (no file attachments in MVP)
- Message history (searchable, archived)
- Unread message count + badge
- Email notification on new message (async, not real-time)

#### 9.3 Channels
- In-app messaging
- Email digest (daily unread summary)
- SMS notification (optional, future)

### Data Model

```
messages:
  id, school_id, sender_id, receiver_id, thread_id,
  body, read_at, created_at

message_threads:
  id, school_id, participant_ids (JSON), last_message_at, created_at
```

### Exit Criteria
Key operational conversations happen in-platform; messaging reduces WhatsApp dependency.

---

## 15. Phase 10 — Analytics & Insights

**Goal:** Data-driven visibility for schools and platform  
**Timeline:** 3-4 weeks  
**Depends on:** Phases 5-8 (operational data exists)

### Scope

#### 10.1 School Dashboard Analytics
- Leads received (this month, trend)
- Inquiries → learner conversion rate
- Active learners + completion %
- Instructor utilization (hours/week, learners assigned)
- Top performing instructors (by rating, completion rate)

#### 10.2 Instructor Performance
- Sessions completed
- Learners assigned
- Attendance %
- Average rating (from learners)
- Completion rate

#### 10.3 Platform Admin Analytics
- Total schools, active schools, churn
- Total inquiries, conversion funnel
- Revenue by tier (MRR, ARR)
- Top localities by inquiry volume
- Platform health metrics

### Exit Criteria
Schools have data-driven visibility into operations; platform admin has business metrics.

---

## 16. Public Pages & Features

### Existing Pages (Implemented)

| Route | Page | Status |
|-------|------|--------|
| `/` | Homepage | Working — auto geo-detect + featured schools + search |
| `/search` | School Search | Working — basic filters, needs geo ranking |
| `/schools/:slug` | School Detail | Working — profile, reviews, packages, contact |
| `/localities/:slug` | Locality Detail | Working — SEO landing page |
| `/compare` | School Comparison | Skeleton — needs comparison engine |
| `/driving-rules` | Driving Rules | Working — geo-aware rules content |
| `/about` | About | Working |
| `/contact` | Contact | Working |
| `/login` | Login | Working (demo auth) |
| `/register` | Register | Working (demo auth) |

### School Comparison Engine (Phase 1-2)

Side-by-side comparison of up to 4 schools across structured attributes.

**Entry Points:**
- "Compare" checkbox on school cards in search results
- "Compare with other schools" CTA on school detail
- "Compare schools" on locality pages
- URL-based: `/compare?schools=slug-a,slug-b,slug-c`

**Comparison Attributes:**
Price, rating, review count, female instructor, pickup/drop, weekend batches, automatic car, languages, license assistance, verified status

**Auto-generated Badges:**
Best Rated, Most Affordable, Best Value (rating/price), Most Reviewed, Women Friendly

**API:** `GET /api/schools/compare?ids=1,2,3` — returns full comparison data

See `docs/Features/School-Comparison-Engine.md` for detailed component specs.

### Geo-Aware Knowledge Center (Phase 1+)

- Country/state/city-specific driving regulations
- RTO directory (office locations, timings)
- License process guides (step-by-step)
- Official government resource links (Parivahan, Sarathi, eChallan)
- FAQ schema for SEO
- Integration into homepage, locality pages, school detail

See `docs/Features/Geo-Aware.md` for detailed feature spec.

### SEO Strategy (Phase 1-4)

- Programmatic locality pages with LocalBusiness structured data
- Comparison pages: "Compare ABC vs XYZ in Pune"
- Guide pages: "Best Driving Schools in Baner"
- FAQ schema on knowledge center pages
- Canonical URLs and breadcrumb schema

### End-User Journey (Public Flow)

```
1. Discover → Homepage / Google search / locality page
2. Search → Filter by location, vehicle type, features
3. Compare → Side-by-side comparison (2-4 schools)
4. Detail → School profile, reviews, packages, trainers
5. Enquire → Form / WhatsApp / Callback
6. Confirm → Submission confirmation + expected response time
7. School Responds → Lead appears in school dashboard
```

See `docs/END-USER-SEARCH-ENQUIRY-PERSONA.md` for detailed persona and journey spec.

---

## 17. Timeline & Dependency Map

### Phase Dependencies

```
Phase 0: Auth & Security ─────────────────────────┐
    ↓                                              │
Phase 1: Geo-Search                                │
    ↓                                              │
Phase 2: Trust & Verification                      │
    ↓                                              │
Phase 3: School Management ◄───────────────────────┘
    ├── (depends on Phase 0)
    └── (unlocks Phase 4, 5)
    ↓
Phase 4: Monetization (can run parallel with Phase 5)
    ↓
Phase 5: Instructor Management
    ├── (depends on Phase 3)
    └── (unlocks Phase 6, 7)
    ↓
Phase 6: Instructor Advanced Ops (scheduling, vehicles)
    ├── (depends on Phase 5)
    └── (should complete before Phase 7)
    ↓
Phase 7: Learner Management
    ├── (depends on Phase 5, ideally Phase 6)
    └── (unlocks Phase 8)
    ↓
Phase 8: Learner Progress
Phase 9: Messaging (depends on Phase 5 + 7)
Phase 10: Analytics (depends on Phase 5-8)
```

### Timeline Estimate

| Phase | Effort | Weeks | Cumulative |
|-------|--------|-------|-----------|
| 0: Auth & Security | 2-4 wks | 1-4 | 4 weeks |
| 1: Geo-Search | 3-5 wks | 5-9 | 9 weeks |
| 2: Trust & Verification | 2-3 wks | 10-12 | 12 weeks |
| 3: School Management | 4-6 wks | 13-18 | 18 weeks |
| 4: Monetization | 3-4 wks | 19-22 | 22 weeks |
| 5: Instructor Mgmt | 4-6 wks | 23-28 | 28 weeks |
| 6: Instructor Ops | 4-5 wks | 29-33 | 33 weeks |
| 7: Learner Mgmt | 4-6 wks | 34-39 | 39 weeks |
| 8: Learner Progress | 3-4 wks | 40-43 | 43 weeks |
| 9: Messaging | 2-3 wks | 44-46 | 46 weeks |
| 10: Analytics | 3-4 wks | 47-50 | 50 weeks |

**Total: ~50 weeks (~12 months) for full DSOS**

### MVP Milestones

| Milestone | Phases | Week | What Schools Can Do |
|-----------|--------|------|---------------------|
| **Marketplace MVP** | 0-2 | 12 | List, receive inquiries, basic profile |
| **Operations MVP** | 0-3 | 18 | + manage profile, team, settings, dashboard |
| **Instructor MVP** | 0-5 | 28 | + manage trainers, trainer profiles on marketplace |
| **Full Platform** | 0-10 | 50 | Complete driving school operating system |

---

## 18. Go-to-Market Strategy

### Launch 1: Marketplace MVP (Week 12)
**Target:** Schools looking for lead generation  
**Value prop:** "Get more student inquiries from Pune"  
**Features:** Listing, geo-search, reviews, verification, inquiry management

### Launch 2: Operations Platform (Week 18)
**Target:** Schools wanting professional online presence  
**Value prop:** "Manage your school operations from one dashboard"  
**Features:** + profile management, team access, settings, operational metrics

### Launch 3: Instructor Management (Week 28)
**Target:** Growing schools with 3+ instructors  
**Value prop:** "Stop managing trainers on WhatsApp"  
**Features:** + trainer profiles, scheduling, performance tracking

### Launch 4: Full DSOS (Week 50)
**Target:** Schools replacing spreadsheets entirely  
**Value prop:** "One platform from inquiry to license"  
**Features:** + learner lifecycle, progress tracking, messaging, analytics

---

## 19. Business Model & Pricing

### Subscription Tiers

| Tier | Price/month | Target | Key Features |
|------|------------|--------|-------------|
| **Basic** | Free | New schools | Listing, basic profile, limited leads |
| **Growth** | Rs 999-2999 | Lead-focused schools | Enhanced profile, lead tracking, review monitoring, visibility dashboard |
| **Academy** | Rs 4999 | Schools with learners | + Learner management, document tracking |
| **Professional** | Rs 7999 | Schools with teams | + Instructor management, scheduling |
| **Enterprise** | Rs 14,999+ | Multi-branch chains | Multi-branch, fleet management, advanced reporting, API access |

### Additional Revenue (Future)
- Pay-per-lead model (premium leads)
- Featured/sponsored placement fees
- Verified badge fees
- Google Business Growth Suite (Phase 4+)
- Programmatic SEO landing page sponsorship

---

## 20. Success Metrics

### Phase 0-2 (Marketplace)
- API security: 0 unauthenticated data mutations
- Search: geo results accurate within selected radius
- Trust: ineligible reviews blocked server-side

### Phase 3 (School Management)
- Profile completeness: > 85% of onboarded schools
- Active school admins: > 70% of schools
- Team invitations: > 1.5 per school
- Team member activation: > 60%

### Phase 5 (Instructor Management)
- Schools with > 1 instructor added: > 40%
- Instructors who log in: > 50% of added
- Trainer cards viewed: > 20% of school detail views
- Trainer ratings: > 1 per 5 learners

### Phase 7 (Learner Management)
- Lead → learner conversion: > 30%
- Document upload completion: > 80%
- Learner portal logins: > 40%
- Active learners: > 100 per school

### Platform Health
- Uptime: > 99.5%
- API latency (p95): < 500ms
- Lighthouse LCP: < 2.5s
- Monthly active schools: growing month-over-month

---

## 21. Supporting Documents

The following documents provide deeper detail for specific areas. They are **supplements** to this master plan, not standalone requirements.

### Architecture & Technical
| Document | Purpose | Status |
|----------|---------|--------|
| `docs/ARCHITECTURE.md` | Database architecture, BelongsToSchool trait, isolation pattern | Current |
| `docs/BACKEND-ASSESSMENT.md` | Detailed gap analysis of current Laravel backend | Current |
| `docs/PHASE-A-IMPLEMENTATION.md` | Step-by-step Phase 0 implementation with code | Current |

### Feature Specifications
| Document | Purpose | Status |
|----------|---------|--------|
| `docs/Features/Geo-Aware.md` | Geo-aware driving rules & government resources (40+ pages) | Current |
| `docs/Features/School-Comparison-Engine.md` | Side-by-side comparison engine spec | Current |
| `docs/Features/PRD-Google-Business.md` | Google Business Growth Suite (future) | Reference |

### User Personas & Flows
| Document | Purpose | Status |
|----------|---------|--------|
| `docs/END-USER-SEARCH-ENQUIRY-PERSONA.md` | Public user search & enquiry journey | Current |
| `docs/SCHOOL-PERSONAS-UI.md` | School-side user personas & onboarding | Current |
| `docs/LEARNER-SCENARIOS.md` | Learner transfer/reassignment scenarios (Phase 7) | Current |
| `docs/LEARNER-SCENARIOS-QUICK-REF.md` | Quick reference for learner scenarios | Current |
| `docs/LEARNER-SCENARIOS-VISUAL.md` | Visual diagrams for learner scenarios | Current |

### Domain Reference
| Document | Purpose | Status |
|----------|---------|--------|
| `docs/requirements/PRD-DSOS.md` | 10 product domain definitions | Current |
| `docs/requirements/PBAC.md` | Permission/access control matrix | Current |
| `docs/requirements/Core-Business-ERd.md` | High-level entity relationships | Reference |

### Deleted Documents (consolidated into this plan on June 9, 2026)
- `ROADMAP-Resequenced-School-First.md` → Section 17
- `PRD-Phase-0-Auth.md`, `PRD-Phase-1-Geo-Search.md`, `PRD-Phase-2-Trust-Verification.md` → Sections 5-7
- `PRD-School-Management.md` → Section 8
- `PRD-Instructor-Management.md`, `PRD-Learner-Management.md`, `REVIEW-PRD-Instructor-Management.md` → Sections 10, 12
- `driveiq-prd-srs.md` → Sections 1, 19
- `driveiq-codebase-reality-gap-analysis.md`, `module-migration-tracker.md` → Section 2
- `BACKEND-SUMMARY.md`, `DOCUMENTS-INDEX.md` → Sections 2, 21
- Root: `IMPLEMENTATION-MASTER-PLAN.md`, `SUMMARY-School-First-Resequencing.md`, `BACKEND-REVIEW-SUMMARY.txt`, `PROJECT-COMPLETION-SUMMARY.txt`

---

*This is a living document. Update as phases complete, architecture evolves, or scope changes.*
