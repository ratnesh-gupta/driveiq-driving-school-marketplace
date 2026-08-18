# DriveQ — Driving School Marketplace Platform

**Status:** MVP Development in Progress  
**Target Market:** Pune, India (Initial Phase)  
**Current Phase:** Phase 0 — Auth & Security Foundation  
**Last Updated:** 2026-06-09  
**Master Plan:** `docs/PROJECT-PLAN.md` — single source of truth for scope, phasing, and requirements

---

## 1. Project Vision & Purpose

### What is DriveQ?
A hyperlocal, geo-intelligent marketplace platform that connects driving learners with verified driving schools in their locality. The platform helps:

**For Learners:**
- Discover nearby driving schools with geolocation awareness
- Compare schools by location, pricing, ratings, instructors
- Check instructor availability (women instructors, timing preferences)
- Submit inquiries via WhatsApp, callback, or direct contact
- Read and write verified reviews

**For Driving Schools:**
- Increase local visibility and receive high-intent leads
- Manage inquiries and track lead quality
- Build trust through verification badges and reviews
- View analytics on inquiry sources and conversion metrics
- Manage service areas, pricing, and course packages

**For Admin:**
- Manage school approvals and verification
- Moderate reviews and handle abuse reports
- Control featured listings and promotions
- Generate locality-based SEO content
- Track platform analytics and KPIs

### Core Differentiators
- **Geo-Intelligent Search:** Post-GIS-powered radius discovery (2km/5km/10km)
- **Trust-Driven Comparison:** Verified schools, ratings, reviews with eligibility enforcement
- **Locality-Focused:** SEO landing pages for Pune localities (Baner, Wakad, Hinjewadi, etc.)
- **Lead Generation Engine:** Multi-channel inquiry system (WhatsApp, callback, form)
- **Women-Friendly:** Explicit women instructor and women-only training filters

---

## 2. Project Structure

```
driveiq-driving-school-marketplace/
├── frontend/              # React + Vite (v0.1.0)
│   ├── src/
│   │   ├── components/    # ShadCN + Radix UI components
│   │   ├── features/      # Feature-specific modules (regulations, listings, etc.)
│   │   ├── pages/         # Route pages
│   │   ├── hooks/         # Custom React hooks
│   │   ├── store/         # Zustand stores
│   │   ├── types/         # TypeScript definitions
│   │   ├── utils/         # Utilities
│   │   └── services/      # API client services
│   ├── public/            # Static assets
│   ├── vite.config.ts     # Vite configuration
│   ├── tsconfig.json      # TypeScript config
│   └── package.json       # Dependencies: React 19, Tailwind 4, Zustand, Framer Motion
│
├── backend/               # Laravel 13.8 (Monorepo structure transitioning from Express)
│   ├── app/              # Laravel application code
│   ├── routes/           # API routes (REST)
│   ├── database/         # Migrations + seeders (PostgreSQL)
│   ├── tests/            # PHPUnit tests
│   ├── composer.json     # PHP dependencies
│   └── .env              # Environment configuration
│
├── docs/                 # Project documentation
│   ├── requirements/
│   │   ├── driveiq-prd-srs.md              # Full PRD (vision, features, tech stack)
│   │   ├── driveiq-codebase-reality-gap-analysis.md  # Gap analysis + phased roadmap
│   │   └── module-migration-tracker.md     # Backend module implementation status
│   └── Features/
│       └── Geo-Aware.md  # Geo-aware rules & government resources feature spec
│
└── CLAUDE.md             # This file — project context for AI assistants
```

---

## 3. Technical Stack

### Frontend
| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Framework** | React + Vite | 19 / 7.3.2 | Fast HMR, modern bundling |
| **Styling** | TailwindCSS | 4.1.14 | Utility-first CSS |
| **UI Components** | ShadCN + Radix UI | Latest | Accessible, customizable components |
| **State Management** | Zustand | 5.0.13 | Lightweight store (location, regulations) |
| **Animations** | Framer Motion | 12.23.24 | Smooth, declarative animations |
| **Forms** | React Hook Form + Zod | 7.55.0 / 3.25.76 | Form validation + submission |
| **Data Fetching** | TanStack Query | 5.90.21 | Server state management |
| **Routing** | Wouter | 3.3.5 | Client-side routing |
| **Maps** | Google Maps API | TBD | School location display, near-me discovery |
| **Charts** | Recharts | 2.15.2 | Analytics visualization |
| **Toast/Notifications** | Sonner | 2.0.7 | User notifications |
| **Icons** | Lucide React + React Icons | 0.545.0 / 5.4.0 | Icon library |

### Backend
| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Framework** | Laravel | 13.8 | Modern PHP web framework |
| **Authentication** | Laravel Sanctum | 4.3 | Token-based API auth |
| **Queue** | Redis + Laravel Queue | TBD | Async notifications, event processing |
| **Database** | PostgreSQL | 13+ | Relational DB with PostGIS |
| **Geo Extension** | PostGIS | TBD | ST_DWithin queries for radius search |
| **Cache** | Redis | TBD | Session cache, query cache |
| **Testing** | PHPUnit | 12.5.12 | Unit + feature tests |

### Infrastructure (Planned)
| Component | Technology | Purpose |
|-----------|-----------|---------|
| **Hosting** | DigitalOcean Droplets | VPS for app + DB |
| **CDN** | Cloudflare | Static asset delivery + DDoS protection |
| **Object Storage** | DigitalOcean Spaces | School photos, media files |
| **Monitoring** | Uptime Kuma | Uptime monitoring (future: datadog/newrelic) |

---

## 4. Product Domain Architecture (10 Business Domains)

The platform is organized into 10 interconnected business domains:

### Domain 1: Marketplace Domain
**Purpose:** Acquire and convert prospective learners

**Modules:**
- School Discovery (geo-aware search, filters)
- Search Engine (location, vehicle type, instructor filters)
- Comparison Engine (ratings, reviews, pricing)
- Reviews & Ratings System
- Local SEO Pages (locality landing pages)
- Knowledge Center (guides, FAQs)
- Government Resources (rules, licenses, RTO info)

**Primary Users:** Visitors, Prospective Learners  
**Key Outputs:** Leads, School Visibility, SEO Traffic

### Domain 2: Lead Management Domain
**Purpose:** Manage inquiries before enrollment

**Modules:**
- Lead Capture (form, WhatsApp, callback)
- Lead Assignment (to school managers)
- Lead Pipeline (status tracking)
- Follow-Up Tracking (activity log)
- Inquiry Notes (internal notes)
- Conversion Tracking (lead → learner)

**Lead Lifecycle:**
```
New Lead → Contacted → Follow-Up → Interested → Enrolled → Converted to Learner
```

**Primary Users:** School Owner, School Manager  
**Key Outputs:** Conversion metrics, lead quality

### Domain 3: Learner Management Domain
**Purpose:** Manage enrolled learners

**Modules:**
- Learner Profiles (contact, vehicle type, timing)
- Learner Documents (ID, address proof, medical)
- License Tracking (learner license progress)
- Training Packages (enrollment, course selection)
- Enrollment Management (status tracking)
- Progress Tracking (module completion, test readiness)
- Driving Test Tracking (RTO test scheduling)

**Learner Lifecycle:**
```
Lead → Learner → Active Training → Driving Test → License Obtained → Completed
```

**Primary Users:** School Owner, School Manager, Trainer, Learner  
**Key Outputs:** Training completion, license acquisition

### Domain 4: Instructor Management Domain
**Purpose:** Manage driving instructors

**Modules:**
- Instructor Profiles (certifications, experience)
- Instructor Documents (license, qualification)
- Availability Management (scheduling)
- Assignment Management (learner assignment)
- Performance Tracking (ratings, completion rates)

**Instructor Lifecycle:**
```
Created → Verified → Active → Assigned Learners → Performance Tracking
```

**Primary Users:** School Owner, School Manager  
**Key Outputs:** Instructor utilization, quality metrics

### Domain 5: Scheduling Domain
**Purpose:** Manage all training sessions

**Modules:**
- Session Scheduling (calendar, time slots)
- Rescheduling (change requests, conflict handling)
- Availability Management (instructor/vehicle/learner availability)
- Conflict Detection (prevent double bookings)
- Attendance Tracking (who attended, markings)

**Primary Users:** School Manager, Trainer, Learner  
**Key Outputs:** Session completion, attendance records

### Domain 6: Vehicle Management Domain
**Purpose:** Manage training vehicles

**Modules:**
- Vehicle Profiles (registration, type, transmission)
- Vehicle Assignment (assign to schedules)
- Maintenance Tracking (service, repairs)
- Availability Tracking (operational status)
- Vehicle Documents (insurance, pollution, registration)

**Primary Users:** School Owner, School Manager  
**Key Outputs:** Vehicle utilization, maintenance schedule

### Domain 7: Communication Domain
**Purpose:** Enable communication across platform

**Modules:**
- Notifications (in-app, email, SMS)
- Internal Messaging (school → instructor, etc.)
- WhatsApp Integration (inquiry deeplinks, status updates)
- Email Templates (notifications, confirmations)
- SMS Notifications (future: OTP, reminders)

**Primary Users:** All Roles  
**Key Outputs:** Engagement, response times

### Domain 8: Growth Suite Domain
**Purpose:** Help schools acquire more learners and grow visibility

**Modules:**
- Google Business Integration (connect, sync reviews)
- Review Management (respond to reviews, encourage ratings)
- Visibility Analytics (how often school is viewed)
- Competitor Tracking (market positioning)
- SEO Recommendations (local SEO optimization)

**Primary Users:** School Owner  
**Key Outputs:** Lead generation, visibility improvement

### Domain 9: Knowledge Center Domain
**Purpose:** Educate learners about driving rules and licensing

**Modules:**
- Driving Rules (geo-aware, state/city specific)
- Licensing Guides (step-by-step process)
- RTO Directory (office locations, timings)
- Government Resources (official portals, requirements)
- FAQs (common questions and answers)

**Primary Users:** Visitors, Learners  
**Key Outputs:** User education, trust building

### Domain 10: Platform Administration Domain
**Purpose:** Manage entire platform operations

**Modules:**
- School Approval (onboarding, verification)
- Subscription Management (plan selection, billing)
- User Management (roles, permissions, account management)
- Content Moderation (review moderation, abuse reports)
- Billing & Payments (invoice, payment tracking)
- Platform Analytics (system-wide metrics)

**Primary Users:** Platform Admin  
**Key Outputs:** Platform health, compliance, revenue

---

## 5. Core Features & Implementation Status

### 4.1 Public Marketplace Features

| Feature | Status | Notes |
|---------|--------|-------|
| **Homepage** | ✅ Route exists | Auto geo-detect + featured schools + quick search |
| **School Search** | ✅ Partial | Location filters exist; geo-ranking not fully implemented |
| **School Listing** | ✅ API built | School profiles, ratings, reviews, course packages visible |
| **Geo-Aware Discovery** | 🟡 In Progress | PostGIS schema + radius queries being implemented |
| **Locality Pages** | ✅ Route exists | SEO landing pages for Pune areas (Baner, Wakad, etc.) |
| **School Detail View** | ✅ API built | Photos, timing, vehicle types, instructor info, contact buttons |
| **Inquiry System** | ✅ API built | Form submission, WhatsApp deeplinks; channel attribution needs work |
| **Reviews & Ratings** | ✅ API built | Create/list reviews; eligibility enforcement needs server-side validation |
| **Geo Rules & Government Info** | 🟡 Planned | Geo-aware driving rules, license process, RTO guidance (Geo-Aware.md spec) |

### 4.2 School Dashboard Features

| Feature | Status | Notes |
|---------|--------|-------|
| **Profile Management** | ✅ API built | Update school info, service areas, timings |
| **Inquiry Management** | ✅ API built | View/update inquiry status; full lead workflow TBD |
| **Package Management** | ✅ API built | Create/update courses and pricing |
| **Reviews Management** | ✅ API built | View reviews, respond (moderation needed) |
| **Analytics Dashboard** | 🟡 Partial | View count, inquiry count; CTR/locality breakdowns missing |
| **Photo Upload** | 🟡 Planned | Media pipeline not fully defined |
| **WhatsApp Integration** | 🟡 Partial | Click-to-chat deeplinks work; automation/API integration future |

### 4.3 Admin Panel Features

| Feature | Status | Notes |
|---------|--------|-------|
| **School Approval** | ✅ Route exists | Verification workflow partially defined |
| **User Management** | 🟡 Placeholder | Admin users page exists but needs full CRUD |
| **Review Moderation** | 🟡 Planned | Report-abuse workflow not yet implemented |
| **Featured Listing Controls** | 🟡 Not started | Monetization-aware ranking TBD |
| **Analytics Dashboard** | 🟡 Partial | Platform-level insights (inquiry volume, top schools) |
| **Locality SEO Management** | 🟡 Planned | Admin controls for SEO page content |

### 4.4 Backend Module Status (Migration Tracker)

All core modules **verified** and transitioned from Express to Laravel APIs:

| Module | API Status | Frontend Integration | Notes |
|--------|-----------|----------------------|-------|
| `health` | ✅ Implemented (`/api/healthz`) | OK | System status endpoint |
| `localities` | ✅ Implemented | OK | list/create/get-by-id/get-by-slug |
| `schools` | ✅ Implemented | OK | list/featured/get/create/update/delete |
| `packages` | ✅ Implemented | OK | course + pricing management |
| `reviews` | ✅ Implemented | OK | CRUD + listing with sort/filter |
| `inquiries` | ✅ Implemented | OK | lead form submission + status tracking |
| `stats` | ✅ Implemented | OK | overview + school-level analytics |

---

## 5. High-Priority Gaps & Roadmap

### Phase A: Stabilize MVP Foundation (2-4 weeks)
**Goal:** Make the platform production-safe for Pune pilot launch.

**Must-Have:**
- [ ] Backend authentication + session management (OTP or JWT)
- [ ] Server-side role-based access control (RBAC)
- [ ] API rate limiting + spam protection for inquiries
- [ ] Expand inquiry schema: add `area` and `preferredTiming` fields
- [ ] Event logging for audit trail (leads, reviews, admin actions)

**Exit Criteria:** APIs are secured, inquiry throttling active, core CRUD flows work with auth.

### Phase B: Geo-Intelligent Search (3-5 weeks)
**Goal:** Deliver the core marketplace differentiator.

**Must-Have:**
- [ ] Add latitude/longitude columns to schools table
- [ ] PostGIS extension setup + ST_DWithin radius queries
- [ ] Implement ranking v1: distance + rating + review_count + verified + premium weight
- [ ] Geolocation consent flow + "near me" entry point on homepage
- [ ] Google Maps integration on school profiles
- [ ] Test with seeded Pune locality data

**Exit Criteria:** Near-me and radius searches work deterministically with proper ranking.

### Phase C: Trust & Review Integrity (2-3 weeks)
**Goal:** Build conversion-driving trust signals.

**Must-Have:**
- [ ] Expand verification model: phone/business/location/premium flags
- [ ] Enforce review eligibility (only post-inquiry/enrollment)
- [ ] Implement abuse report workflow + moderation queue
- [ ] Surface verification badges in search results and rankings
- [ ] Admin moderation dashboard

**Exit Criteria:** Ineligible reviews are blocked server-side; abuse reports are actionable.

### Phase D: Lead Engine & Notifications (2-4 weeks)
**Goal:** Improve conversion quality and school response.

**Must-Have:**
- [ ] Attach channel metadata to inquiries (WhatsApp/callback/call/form)
- [ ] Notification pipeline (start with email + click-to-chat; queue architecture ready for SMS)
- [ ] Response-time tracking for schools
- [ ] Lead status workflow (new → contacted → converted → lost)

**Exit Criteria:** Schools get near-real-time lead notifications; every lead has channel/status metadata.

### Phase E: SEO & Growth Engine (3-4 weeks)
**Goal:** Enable organic acquisition at locality scale.

**Must-Have:**
- [ ] Programmatic SEO templates for Pune localities + use-cases
- [ ] Metadata/canonical strategy + structured data (FAQ, breadcrumb, local business schema)
- [ ] Admin tooling for SEO page content management
- [ ] Guide/comparison page templates

**Exit Criteria:** Priority locality pages indexed with stable metadata and crawlability.

### Phase F: Monetization & Premium Controls (3-5 weeks)
**Goal:** Move from validation to revenue model execution.

**Must-Have:**
- [ ] Listing tiers: basic (free) / featured (Rs 999-2999/mo) / premium (Rs 4999/mo)
- [ ] Premium ranking logic and sponsored placement inventory
- [ ] Admin control plane for featured slots and campaign windows
- [ ] School-side subscription status visibility

**Exit Criteria:** Marketplace can run free and paid listings; premium visibility is configurable and auditable.

---

## 6. Key Business Model & Monetization

### Phase 1 Revenue Streams
1. **Featured Listing (Rs 999-2999/month):** Increased visibility in search and homepage
2. **Premium Listing (Rs 4999/month):** Top placement, sponsored badges, premium ranking boost
3. **Basic Free Listing:** School can be discovered but with lower ranking priority

### Future Revenue (Post-MVP)
- Pay-per-lead model
- Verified badge fees
- Homepage promotions
- Sponsored SEO landing page placements

---

## 7. Database Architecture & Data Model

### Architecture Pattern: Shared Database + Logical School Isolation

**NOT a multi-tenant architecture.** This is similar to gym/clinic/salon management software:
- Single PostgreSQL database (shared)
- All schools' data stored together
- Logical isolation via `school_id` filtering
- Authorization enforced at application layer (RBAC)

**Core Design Principle:**
Almost every operational table must contain `school_id` to enforce data isolation.

```
Platform
    ↓
Shared PostgreSQL Database
    ↓
All Schools + Users + Learners
    ↓
Filtered by school_id + RBAC
```

### Database Schema Organization (60-70 tables)

#### Global Platform Tables (~15 tables)
Shared across entire platform.
```
users
roles
permissions
subscriptions
plans
cities
states
countries
rto_offices
government_resources
driving_rules
license_guides
notifications
messages
audit_logs
```

#### School Configuration Tables (~10 tables)
School-specific settings and profile.
```
schools
school_branches (future)
school_settings
school_documents
school_subscriptions
school_verification
school_features
pricing_tiers
service_areas
location_coverage
```

#### Lead Management Tables (~8 tables)
Lead acquisition and conversion pipeline.
```
leads
lead_activities
lead_notes
lead_sources
lead_status_history
lead_assignments
lead_conversions
lead_analytics
```

#### Learner Management Tables (~10 tables)
Enrolled learner tracking and progress.
```
learners
learner_documents
learner_packages
learner_progress
learner_license_tracking
learner_notes
learner_enrollment_history
learner_ratings
learner_feedback
learner_status_tracking
```

#### Instructor Management Tables (~8 tables)
Instructor profiles and performance.
```
instructors
instructor_documents
instructor_certifications
instructor_skills
instructor_languages
instructor_ratings
instructor_availability
instructor_performance_metrics
```

#### Scheduling Tables (~6 tables)
Session scheduling and attendance.
```
schedules
schedule_attendees
schedule_history
attendance
reschedule_requests
schedule_conflicts
```

#### Vehicle Management Tables (~6 tables)
Training vehicle lifecycle.
```
vehicles
vehicle_documents
vehicle_maintenance
vehicle_assignments
vehicle_availability
vehicle_service_history
```

#### Marketplace Tables (~8 tables)
Public marketplace and reviews.
```
school_listings
reviews
review_replies
review_reports
ratings_summary
school_photos
featured_placements
marketplace_analytics
```

#### Communication Tables (~6 tables)
Internal and external messaging.
```
messages
message_threads
notifications
notification_templates
notification_preferences
communication_logs
```

#### Google Business Integration Tables (~5 tables)
Google Business Profile management.
```
google_business_accounts
google_business_reviews
google_business_posts
google_business_photos
google_business_analytics
```

### Critical Security Rule

**Every query must be scoped to school_id**

❌ Bad:
```sql
SELECT * FROM learners;
```

✅ Good:
```sql
SELECT * FROM learners
WHERE school_id = :school_id;
```

### Core Entity Relationships

**Schools** (Center of entire platform)
```
Schools
    ├─ Instructors (1:many)
    ├─ Learners (1:many)
    ├─ Vehicles (1:many)
    ├─ Leads (1:many)
    ├─ Reviews (1:many)
    ├─ Packages (1:many)
    └─ Schedules (1:many)
```

**Leads** → **Learners** (Conversion)
```
Lead (prospect inquiry)
    ├─ Lead Activities (contact history)
    ├─ Lead Notes (internal notes)
    └─ → converts to → Learner
```

**Learners** → **Instructors** + **Vehicles** + **Schedules**
```
Learner
    ├─ assigned_instructor_id
    ├─ assigned_vehicle_id
    ├─ package_id
    ├─ Schedules (1:many)
    ├─ Documents (1:many)
    ├─ Progress (1:many)
    └─ License Tracking (1:1)
```

**Schedules** (Central orchestration)
```
Schedule
    ├─ learner_id
    ├─ instructor_id
    ├─ vehicle_id
    ├─ school_id
    ├─ Attendance
    └─ Reschedule History
```

### Example Tables with school_id

**learners**
```
id
school_id (REQUIRED)
name, mobile, email
status (active/inactive/completed)
license_status (pending/pass/fail/obtained)
assigned_instructor_id
assigned_vehicle_id
package_id
created_at, updated_at
```

**instructors**
```
id
school_id (REQUIRED)
user_id
name, license_number, experience
availability_status
rating_average
total_learners_trained
created_at, updated_at
```

**schedules**
```
id
school_id (REQUIRED)
learner_id, instructor_id, vehicle_id
session_date, start_time, end_time
status (scheduled/completed/cancelled)
created_at, updated_at
```

**reviews**
```
id
school_id (REQUIRED)
learner_id (nullable, from learner or lead)
rating (1-5), title, text
verified_badge (bool)
eligible_reason (inquiry_id / learner_id)
abuse_report_count
moderation_status (pending/approved/rejected)
created_at, updated_at
```

### Future: Multi-Branch Support

If you expand to branches later, structure becomes:
```
School
    ├─ Branch 1
    │   ├─ Instructors
    │   └─ Learners
    └─ Branch 2
        ├─ Instructors
        └─ Learners
```

Tables would add:
```
school_id, branch_id
```

Current single-branch design naturally extends to this model.

---

## 8. Current Implementation Status

### What's Working
✅ Frontend routes: home, search, school detail, locality detail, about, contact, auth  
✅ School dashboard routes: overview, leads, profile, packages, reviews, analytics  
✅ Admin routes: overview, schools, reviews, localities, users (placeholder)  
✅ REST API endpoints for all core modules (health, schools, localities, reviews, inquiries, packages, stats)  
✅ Database entities and schema in PostgreSQL  
✅ Zod validation pattern with generated schemas from OpenAPI  
✅ TailwindCSS + ShadCN component library + Framer Motion animations  

### Demo/Non-Production Behaviors
⚠️ Frontend-only auth via Zustand role switching (no real backend sessions)  
⚠️ API endpoints are not protected by server-side auth/RBAC  
⚠️ Demo school context in dashboard (fixed assumptions)  
⚠️ Review eligibility not enforced server-side  
⚠️ Geo-ranking logic not implemented  
⚠️ Notification pipeline not built  
⚠️ Rate limiting and spam controls absent  

### Tech Stack Reality vs PRD
**PRD proposed:** Next.js + Laravel + Sanctum + Redis queue  
**Current implementation:** React + Vite + Laravel + Drizzle/Postgres  
**Recommendation:** Continue current stack for MVP speed (replatforming to Next.js would add 2-3x timeline).

---

## 9. Development Guidelines & Practices

### Branch Strategy
- Main development branch: `main`
- Feature branches: `feature/module-<name>-<scope>` (per module)
- Bugfix branches: `bugfix/<issue>`
- Keep branch synced with `main` daily while active
- Merge only after tests + frontend flow verification

### Authorization & Role-Based Access Control (RBAC)

**5 Core Roles:**
1. **Platform Admin (PA)** - System-wide control
2. **School Owner (SO)** - School owner/founder
3. **School Manager (SM)** - Day-to-day operations
4. **Instructor (IN)** - Training execution
5. **Learner (LE)** - Student enrolled in training

**Permission Matrix Summary:**

| Domain | PA | SO | SM | IN | LE |
|--------|----|----|----|----|-----|
| **School Management** | Create/Approve | Manage Profile | Manage Profile | - | - |
| **Lead Management** | All | View/Convert | View/Convert | - | - |
| **Learner Management** | All | Manage All | Manage All | View Assigned | Self Only |
| **Instructor Management** | All | Manage All | Manage All | Self | - |
| **Scheduling** | All | Create/Manage | Create/Manage | View Assigned | View Self |
| **Vehicle Management** | All | Manage All | Manage All | View Available | - |
| **Google Business** | - | Connect/Manage | Connect/Manage | - | - |
| **Platform Admin** | Subscriptions, Analytics, Moderation | - | - | - | - |

**Implementation in Laravel:**

```php
// In middleware or policy class
if (auth()->user()->role === 'platform_admin') {
    // Full access
}

if (auth()->user()->role === 'school_owner' && $resource->school_id === auth()->user()->school_id) {
    // School-level access
}

if (auth()->user()->role === 'instructor' && $resource->instructor_id === auth()->user()->id) {
    // Personal resource only
}
```

### Code Organization

#### Frontend
- **Feature modules:** Self-contained features with `/components`, `/hooks`, `/services`, `/types`, `/utils`, `/pages` structure
- **Reusable components:** ShadCN + Radix UI, stored in `/components`
- **State management:** Zustand stores in `/store` (locationStore, regulationStore, governmentResourceStore)
- **API services:** Organized by domain (`schoolsService`, `inquiriesService`, `reviewsService`)
- **Type safety:** All APIs have Zod schemas + TypeScript definitions

#### Laravel Backend: BelongsToSchool Pattern

Create a reusable trait to enforce school isolation on all models:

**File:** `app/Models/Traits/BelongsToSchool.php`
```php
<?php

namespace App\Models\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToSchool()
    {
        // Automatically add school_id scope to all queries
        static::addGlobalScope('school', function (Builder $builder) {
            $school_id = auth()->user()?->school_id;
            if ($school_id) {
                $builder->where('school_id', $school_id);
            }
        });
    }

    /**
     * Relationship: belongs to school
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
```

**Usage in Model:**
```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Learner extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'mobile', 'email', 'status'];
}
```

**Models using BelongsToSchool:**
```
Learner
Instructor
Vehicle
Schedule
Lead
Review
Package
Document
Message
```

**Query Example:**
```php
// Automatically filtered by current user's school_id
$learners = Learner::all();

// Equivalent to:
$learners = Learner::where('school_id', auth()->user()->school_id)->get();
```

**Admin Override (if needed):**
```php
// Platform admin can bypass school scope
$allLearners = Learner::withoutGlobalScope('school')->get();
```

### API Design
- REST style with consistent naming: `/api/{resource}` for collections, `/api/{resource}/{id}` for items
- Consistent response shape: `{ data, meta }` with error handling
- Timestamps: `created_at`, `updated_at` in all entities
- Soft deletes where applicable (use `deleted_at`)
- **All mutations must validate school_id ownership**

**Example Controller Pattern:**
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        'mobile' => 'required|string',
    ]);

    // Force school_id from authenticated user
    $validated['school_id'] = auth()->user()->school_id;

    $learner = Learner::create($validated);
    return response()->json(['data' => $learner]);
}
```

### Testing
- **Frontend:** Component tests with React Testing Library (future: integration tests)
- **Backend:** PHPUnit for unit + feature tests
  - Always test with multiple schools to ensure isolation
  - Test that users cannot access another school's data
- **API:** Test all CRUD operations and validation rules

**Test Example:**
```php
public function test_user_can_only_see_own_schools_learners()
{
    $school1 = School::factory()->create();
    $school2 = School::factory()->create();
    
    $user1 = User::factory()->create(['school_id' => $school1->id]);
    $learner1 = Learner::factory()->create(['school_id' => $school1->id]);
    $learner2 = Learner::factory()->create(['school_id' => $school2->id]);

    // User from school1 should not see school2's learner
    $this->actingAs($user1)
        ->getJson('/api/learners')
        ->assertJsonMissing(['id' => $learner2->id])
        ->assertJsonFragment(['id' => $learner1->id]);
}
```

### Performance Targets
- Lighthouse LCP: < 2.5s
- Lighthouse CLS: Minimal
- TTI: < 3s
- Lazy-load accordions and regulation data
- Cache regulation data client-side (Zustand)
- Use ISR/SSG for SEO pages
- **Database:** Index on `(school_id, created_at)` for all operational tables

---

## 10. Key Files & References

### Documentation
- **Master Plan & Roadmap:** `/docs/PROJECT-PLAN.md` — Single source of truth for all phases, scope, and requirements
- **Architecture:** `/docs/ARCHITECTURE.md` — Database architecture, BelongsToSchool trait, isolation pattern
- **Backend Assessment:** `/docs/BACKEND-ASSESSMENT.md` — Detailed gap analysis of current backend
- **Phase 0 Implementation:** `/docs/PHASE-A-IMPLEMENTATION.md` — Step-by-step Phase 0 code
- **Geo-Aware Feature Spec:** `/docs/Features/Geo-Aware.md` — Driving rules & government resources feature
- **Comparison Engine:** `/docs/Features/School-Comparison-Engine.md` — Side-by-side comparison spec
- **User Personas:** `/docs/END-USER-SEARCH-ENQUIRY-PERSONA.md` + `/docs/SCHOOL-PERSONAS-UI.md`

### Frontend Entry Points
- **Homepage:** `src/pages/home.tsx`
- **School Search:** `src/pages/search.tsx`
- **School Detail:** `src/pages/school-detail.tsx`
- **School Dashboard:** `src/pages/dashboard/*`
- **Admin Panel:** `src/pages/admin/*`

### Backend Entry Points (Laravel)
- **Routes:** `backend/routes/api.php`
- **Controllers:** `backend/app/Http/Controllers/`
- **Models:** `backend/app/Models/`
- **Migrations:** `backend/database/migrations/`

### Environment Setup
- **Frontend ENV:** Copy `.env.example` to `.env` (Google Maps API key, backend URL)
- **Backend ENV:** Copy `.env.example` to `.env` (DB credentials, sanctum settings, queue driver)

---

## 11. Known Limitations & Future Considerations

### Current Limitations
- No production authentication (frontend-only role switching)
- Geo-search ranking not tuned to Pune data
- Notification system not implemented (no email/WhatsApp automation yet)
- No advanced analytics (CTR, conversion funnel tracking)
- Mobile app not built (responsive web app is current MVP approach)
- Instructor-specific features minimal (instructor rating, schedule management)

### Future Expansion Areas
- **International expansion:** Add rules/RTOs for other countries
- **Mobile apps:** Native Android/iOS apps for learners and instructors
- **Booking system:** Integrated scheduling, calendar, payment integration
- **AI features:** Smart school recommendations, learner guidance assistant, RTO prep quizzes
- **Ecosystem:** Logistics vehicle training, corporate driver training, EV training

---

## 12. Contact & Key Information

**Project Owner:** Ratnesh (ratnesh.k.gupta@icloud.com)  
**Repository:** `/Volumes/RatneshED/Projects/Agies/driveiq-driving-school-marketplace/`  
**Current Phase:** Phase 0 — Auth & Security Foundation  
**Target Launch:** Marketplace MVP Week 12, Operations MVP Week 18 (see PROJECT-PLAN.md)

---

## 13. Quick Start Commands

### Frontend Development
```bash
cd frontend
npm install
npm run dev  # Starts Vite dev server on http://localhost:5173
npm run build  # Production build
npm run typecheck  # TypeScript validation
```

### Backend Development
```bash
cd backend
composer install
php artisan serve  # Starts Laravel server
php artisan queue:listen  # Background job processor
php artisan migrate  # Run database migrations
php artisan test  # Run PHPUnit tests
```

### Docker Setup (Future)
Both frontend and backend have Dockerfiles for containerized deployment.

---

**This CLAUDE.md is a living document.** Update it as:
- New features are implemented
- Architecture decisions change
- Phases are completed
- Technical stack evolves
