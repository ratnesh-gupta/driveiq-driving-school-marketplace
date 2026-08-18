# School Personas & Frontend UI Layout

**Status:** Draft for review  
**Scope:** School-side onboarding, dashboard, and operational workflows  
**Source of Truth:** School Management module + school-facing frontend routes

---

## 1. Purpose

This document defines the school user personas and the frontend experience for the school-side product. It starts at sign-up and covers the full lifecycle:

- Account creation
- Email / password authentication
- School onboarding
- Team setup
- Operational dashboard usage
- Ongoing maintenance, review, and edge cases

This is the review document for the school-facing UI layout before implementation is finalized.

---

## 2. School User Personas

### 2.1 Persona A — School Owner / Founder

**Profile**
- Owns the driving school business
- Wants a professional online presence and direct lead flow
- Usually creates the account first
- Needs control over branding, pricing, team access, and billing

**Primary Goals**
- Register the school quickly
- Publish a credible profile
- Add team members
- Monitor leads, reviews, and revenue activity
- Keep the profile updated without depending on a developer

**Pain Points**
- Unclear onboarding steps
- Too many settings exposed too early
- Team access that is not role-based
- Booking or payment features appearing before the school is ready

**Success Criteria**
- Can complete sign-up in a few minutes
- Can resume onboarding after interruption
- Can manage the school from one dashboard
- Can delegate work safely to staff

---

### 2.2 Persona B — School Manager / Operations Lead

**Profile**
- Handles day-to-day school operations
- Manages inquiries, profile content, and operational settings
- Usually invited by the owner

**Primary Goals**
- Update school details
- Maintain the dashboard
- Support leads and operational changes
- View schedules and readiness state for later phases

**Pain Points**
- No clear access boundaries
- Needs to know what can be edited vs. view-only
- Needs a simple, task-first dashboard

**Success Criteria**
- Receives a clean invite flow
- Lands directly into the correct dashboard view
- Can perform assigned actions without touching billing or ownership controls

---

### 2.3 Persona C — Front Desk / Inquiry Handler

**Profile**
- Supports calls, walk-ins, and lead follow-up
- Needs minimal access
- Usually works from a mobile device or a shared workstation

**Primary Goals**
- View assigned inquiries
- Update statuses and notes
- Avoid unrelated admin complexity

**Pain Points**
- Too many menus
- Risk of accidental changes outside scope
- Needs fast, repeatable UI

**Success Criteria**
- Sees only relevant actions
- Can update lead status quickly
- Cannot access billing, ownership, or restricted team controls

---

### 2.4 Persona D — Multi-Branch School Admin

**Profile**
- Manages multiple branches under one brand
- Needs branch separation and consistent branding

**Primary Goals**
- Switch between branches
- Ensure profile consistency
- Coordinate team access per branch

**Pain Points**
- Branch context confusion
- Duplicate data entry
- Hard to understand what is global vs. branch-specific

**Success Criteria**
- Branch selector is obvious
- Context changes are visible in the UI
- Shared and branch-specific settings are clearly separated

---

## 3. End-to-End School Journey

### Step 1 — Sign Up

**Entry Point**
- Public register page
- Account type selected as `Driving School`

**User Input**
- School name
- Owner name if needed
- Email
- Phone number
- Password

**UI Requirements**
- School vs learner split must be visible
- Copy must explain that this account is for school operations
- Form must show field validation inline
- CTA must be explicit: `Create Account`

**Success State**
- Account is created
- User lands in the onboarding state or dashboard depending on completion status

**Failure States**
- Email already exists
- Weak password
- Invalid phone/email
- Session or server error

---

### Step 2 — Verify Access

**Required Checks**
- Email verification
- Optional phone verification
- Optional 2FA for owners and managers

**UI Requirements**
- Verification banner on login and dashboard
- Resend verification action
- Clear countdown or retry state if throttled

**Success State**
- Verified account can continue onboarding

**Failure States**
- Verification expired
- Token invalid
- User tries to access protected screens before verification

---

### Step 3 — Create School Profile

**Data Captured**
- School name
- Logo
- Banner image
- Description
- Address
- Locality
- City
- Service area
- Contact details

**UI Requirements**
- Guided profile setup wizard
- Progress indicator for completeness
- Save as draft
- Resume later

**Success State**
- School profile is created and visible in the dashboard

**Failure States**
- Missing required profile fields
- Image upload failure
- Invalid address or service area

---

### Step 4 — Add Team Members

**User Actions**
- Invite managers
- Invite inquiry handlers
- Assign future instructor coordinator roles

**UI Requirements**
- Role dropdown
- Invite email input
- Pending invite state
- Resend / revoke invite actions

**Success State**
- Team members appear in active/pending lists

**Failure States**
- Duplicate email
- Invalid role assignment
- Invite expired

---

### Step 5 — Configure Operations

**User Actions**
- Set working hours
- Configure contact channels
- Set notification preferences
- Configure billing and subscription later if applicable

**UI Requirements**
- Structured settings sections
- Section-level save actions
- Read-only labels for system-managed values

**Success State**
- School is ready for operational use

**Failure States**
- Conflicting settings
- Missing timezone
- Invalid contact configuration

---

### Step 6 — Use Dashboard Day-to-Day

**User Actions**
- Review key metrics
- Update profile
- Manage team
- Track leads and review signals
- Review audit history

**UI Requirements**
- Dashboard should surface immediate actions first
- Secondary actions should not compete with primary school operations
- No booking/payment actions should appear until the relevant phase is active

**Success State**
- School can operate without support intervention

---

### Step 7 — Resume After Interruptions

**Scenario Types**
- User closes onboarding midway
- User logs out before verification
- User is invited but does not accept immediately
- User returns after days or weeks

**UI Requirements**
- Persist onboarding state
- Show clear `Continue setup` CTA
- Show exactly which step is incomplete

---

## 4. Scenario Coverage

### Scenario 1 — New School Owner Creates an Account

1. Visits registration page
2. Selects `Driving School`
3. Enters school name, email, phone, and password
4. Accepts terms
5. Creates account
6. Verifies email
7. Completes onboarding
8. Lands on school dashboard

**Acceptance Criteria**
- School account is created
- School role is assigned correctly
- User is routed to the school path, not the learner path

---

### Scenario 2 — Owner Starts Onboarding and Stops Midway

1. Creates account
2. Leaves before completing profile
3. Returns later
4. Sees onboarding progress
5. Continues from the last incomplete step

**Acceptance Criteria**
- Progress is preserved
- User does not need to restart onboarding

---

### Scenario 3 — Owner Invites a Manager

1. Opens team page
2. Enters email
3. Selects manager role
4. Sends invite
5. Manager accepts invite
6. Manager creates credentials
7. Manager lands in dashboard with scoped permissions

**Acceptance Criteria**
- Invite is tracked as pending until accepted
- Role scope is enforced after login

---

### Scenario 4 — Inquiry Handler Uses a Restricted View

1. Receives invite or account assignment
2. Logs in
3. Sees only inquiry-related actions
4. Updates inquiry status

**Acceptance Criteria**
- No access to billing or ownership actions
- UI hides irrelevant modules

---

### Scenario 5 — Multi-Branch Owner Switches Context

1. Logs in
2. Selects branch
3. Updates branch-specific settings
4. Returns to global brand settings when needed

**Acceptance Criteria**
- Active branch is always visible
- Changes apply to the correct branch

---

### Scenario 6 — Unverified User Tries to Use Dashboard

1. Creates account
2. Skips verification
3. Attempts to access dashboard actions
4. Sees verification gate

**Acceptance Criteria**
- Protected actions are blocked
- Verification prompt is clear and actionable

---

## 5. Frontend UI Layout Design

### 5.1 Public Registration Page

**Route**
- `/auth/register`

**Layout**
- Left panel: brand message, trust signals, short product explanation
- Right panel: account creation form

**Form Design**
- Account type selector with `Driving School` and `Learner`
- School-first copy when `Driving School` is selected
- Inputs:
  - School name
  - Phone number
  - Email
  - Password
- Primary CTA: `Create Account`

**UI Behavior**
- Inline field errors
- Disabled submit while loading
- Redirect based on role after success

**Review Note**
- The school option must be visually more explicit than the learner option because school onboarding is the operational entry path.

---

### 5.2 Login Page

**Route**
- `/auth/login`

**Layout**
- Left panel: partner/school value proposition
- Right panel: login form

**Form Design**
- Email
- Password
- Sign in CTA

**UI Behavior**
- Login should route school users to `/dashboard`
- Invalid credentials should be shown inline
- Verified/unverified status should be communicated clearly after login

---

### 5.3 School Dashboard Shell

**Route Group**
- `/dashboard/*`

**Layout**
- Desktop: left sidebar + top header + main content
- Mobile: sheet-based sidebar + compact header

**Sidebar Modules**
- Overview
- Leads
- Profile
- Packages
- Reviews
- Analytics

**Header**
- Page title
- Optional notifications area
- User menu placeholder

**Review Note**
- Keep booking and payment modules hidden until the phase that enables them.

---

### 5.4 Onboarding Layout

**Recommended Screen Pattern**
- Stepper at the top
- Main form card in the center
- Side rail with setup checklist on desktop
- Persistent `Save and continue later` action

**Step Order**
1. Account creation
2. Verification
3. School profile
4. Team invite
5. Settings
6. Dashboard review

**UI Behavior**
- Show completion percentage
- Allow backward navigation only when safe
- Keep unfinished steps visible in a checklist

---

### 5.5 Team Management Layout

**Screen Sections**
- Team summary card
- Invite form
- Pending invites table
- Active users table
- Permission matrix preview

**UI Behavior**
- Role badges should be readable at a glance
- Actions should be constrained by role
- Deactivate/remove actions require confirmation

---

### 5.6 Settings Layout

**Screen Sections**
- School basics
- Contact details
- Business hours
- Notifications
- Integrations
- Security

**UI Behavior**
- Each section saves independently
- Use explicit success states after save
- Keep system-managed fields read-only

---

### 5.7 Empty, Loading, and Error States

**Empty State**
- No team members yet
- No profile photo uploaded
- No onboarding progress started

**Loading State**
- Skeletons for dashboard cards and tables
- Submit button loading state

**Error State**
- Server errors with retry
- Validation errors inline
- Permission denied state with clear next step

---

## 6. Acceptance Criteria for the Persona Doc

- Personas start from sign-up and continue through operational use
- All key school roles are represented
- Signup, verification, onboarding, team, settings, and dashboard are described
- UI layout guidance is specific enough for frontend review
- Booking and payment UI is not introduced before the phases that support it

---

## 7. Notes for Frontend Implementation

- Keep the school flow separate from learner flow in the registration UI
- Use a clear onboarding progress model rather than a single long form
- Ensure protected dashboard routes redirect unauthenticated users to login
- Preserve the school context across page reloads
- Design the dashboard around task completion, not around raw data density

