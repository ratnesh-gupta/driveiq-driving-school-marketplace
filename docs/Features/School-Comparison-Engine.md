# FRONTEND FEATURE REQUIREMENT

# School Comparison Engine

## For Driving School Marketplace Platform

### Version 1.0

---

# 1. Feature Overview

## Feature Name

> Side-by-Side School Comparison Engine

---

# Purpose

Allow users to compare multiple driving schools across structured attributes — pricing, instructors, vehicle types, convenience features, and training options — enabling informed decision-making that Google Business and map-based discovery cannot provide.

---

# Strategic Goal

This feature should:

* be the primary differentiator against Google Business / Justdial
* increase time-on-site and engagement
* improve conversion from browsing to inquiry
* position the platform as a "decision layer" not a "discovery layer"
* answer: "Which school is best for ME?" instead of "Where are schools near me?"

---

# 2. Product Positioning

This feature should feel like:

```text
Intelligent Decision Tool
```

NOT:

* a basic data table
* a cluttered spreadsheet
* a feature-checklist dump

---

# 3. Core Comparison Attributes

---

# School Data Fields Required

These fields must exist on the school model (some are new additions):

## Existing Fields
| Field | Source |
|-------|--------|
| Name | schools table |
| Locality | schools table |
| Rating | reviews aggregate |
| Review Count | reviews aggregate |
| Verified Status | verification table |
| Phone / WhatsApp | schools table |

## New Fields (To Be Added)

| Field | Type | Description |
|-------|------|-------------|
| `has_female_instructor` | boolean | Women instructor available |
| `has_pickup_drop` | boolean | Pickup/drop service offered |
| `pickup_radius_km` | number | Pickup service radius |
| `has_weekend_batches` | boolean | Weekend training available |
| `has_weekday_batches` | boolean | Weekday training available |
| `has_evening_batches` | boolean | Evening batch available |
| `has_automatic_car` | boolean | Automatic transmission training |
| `has_manual_car` | boolean | Manual transmission training |
| `has_two_wheeler` | boolean | Two-wheeler training |
| `languages_spoken` | string[] | Languages instructors speak |
| `license_assistance` | boolean | Helps with license process |
| `has_simulator` | boolean | Simulator training available |
| `min_price_rupees` | number | Starting price (derived from packages) |
| `max_price_rupees` | number | Highest package price |
| `years_in_business` | number | Establishment years |
| `total_instructors` | number | Number of instructors |

---

# 4. Comparison Table UI

---

# Layout

```text
           | School A     | School B     | School C     |
-----------|-------------|-------------|-------------|
Price      | Rs 3,500     | Rs 4,200     | Rs 5,000     |
Rating     | 4.5 (120)    | 4.2 (85)     | 4.8 (200)    |
Female Inst| Yes          | No           | Yes          |
Pickup     | Yes (3km)    | Yes (5km)    | No           |
Weekend    | Yes          | No           | Yes          |
Automatic  | Yes          | No           | Yes          |
Languages  | Hindi, Eng   | Hindi, Mar   | Hindi, Eng   |
License Help| Yes         | No           | Yes          |
Verified   | Yes          | Yes          | No           |
```

---

# Comparison Limits

* Minimum: 2 schools
* Maximum: 4 schools (mobile: 2-3)
* Schools must be in the same city/region

---

# 5. User Flow

---

# How Users Add Schools to Compare

## Method 1 — From Search Results

Each school card in search/listing has a "Compare" checkbox/button.

```text
[ ] Add to Compare
```

When selected, a floating comparison bar appears at the bottom:

```text
Comparing: School A, School B  [Compare Now]  [Clear]
```

---

## Method 2 — From School Detail Page

"Compare with other schools" CTA button on the school detail page.

---

## Method 3 — From Locality Page

Compare schools within a specific locality.

---

# Comparison View Options

## Option A — Full Page

Dedicated route:

```text
/compare?schools=school-a-slug,school-b-slug,school-c-slug
```

Full-width comparison table with all attributes.

## Option B — Slide-up Panel

Bottom sheet / modal overlay on mobile.

---

# 6. Comparison Page Layout

---

# Structure

```text
Header (schools being compared)
      |
Quick Summary Cards (price range, top rated, best value badges)
      |
Detailed Comparison Table
      |
Package Comparison (if schools have matching vehicle types)
      |
Review Highlights (top review from each school)
      |
CTA Row (Inquire / WhatsApp / Call for each school)
```

---

# Quick Summary Badges

Auto-generated insight badges:

* **Best Rated** — highest rating
* **Most Affordable** — lowest starting price
* **Best Value** — rating/price ratio
* **Most Reviewed** — highest review count
* **Women Friendly** — has female instructor + good rating

---

# 7. Shareable Comparisons

---

# URL-Based Sharing

Comparison state encoded in URL:

```text
/compare?schools=abc-driving-school,xyz-motor-training,pqr-academy
```

* Shareable via WhatsApp / copy link
* Open Graph metadata for shared links:
  * Title: "Compare ABC vs XYZ vs PQR — DriveIQ"
  * Description: "Side-by-side comparison of driving schools in Pune"

---

# 8. School Profile Page Updates

---

# New Profile Sections Required

The school dashboard profile page needs new tabs/sections to capture comparison data:

## Tab 1 — Basic Info (Existing)
Name, address, contact, description, timings

## Tab 2 — Training & Vehicles (New)
* Vehicle types offered (car manual, car automatic, two-wheeler, heavy vehicle)
* Simulator availability
* Training methodology description

## Tab 3 — Instructors (New)
* Total instructor count
* Female instructor availability
* Languages spoken by instructors
* Years of experience (aggregate)

## Tab 4 — Convenience & Services (New)
* Pickup/drop availability + radius
* Batch timings (weekday / weekend / evening / early morning)
* License assistance offered
* Free re-test policy
* Trial class available

## Tab 5 — Packages & Pricing (Existing, Enhanced)
* Already exists but should surface min/max price for comparison

---

# Profile Completeness Score

Display a completeness indicator encouraging schools to fill all fields:

```text
Profile Completeness: 72%
Complete your profile to appear in more comparisons
[Fill Missing Details]
```

Missing fields that affect comparison visibility should be highlighted.

---

# 9. Learner Profile Enhancements

---

# New Data to Collect from Learners

During inquiry or registration, capture preferences that power smarter comparisons:

| Field | Type | Purpose |
|-------|------|---------|
| `preferred_vehicle_type` | enum | car/bike/scooter/auto |
| `preferred_transmission` | enum | manual/automatic/both |
| `preferred_timing` | enum | weekday/weekend/evening |
| `needs_female_instructor` | boolean | Women instructor preference |
| `needs_pickup` | boolean | Pickup service needed |
| `preferred_language` | string | Training language preference |
| `budget_range` | enum | economy/mid/premium |

These preferences can:
* pre-filter comparison results
* highlight "best match" schools
* power future AI recommendations

---

# 10. Mobile UX

---

# Mobile Comparison View

* Swipeable horizontal cards (2 visible at a time)
* Sticky school name headers while scrolling attributes
* Expandable attribute groups (tap to show/hide sections)
* Bottom sticky CTA: "Inquire About [School Name]"

---

# Floating Compare Bar (Mobile)

```text
[School A] [School B] [+]    [Compare]
```

* Stays at bottom of screen during browsing
* Shows school thumbnails/initials
* Tap [+] to add more
* Tap school to remove
* Max 3 on mobile

---

# 11. Frontend Components

---

# New Components

| Component | Purpose |
|-----------|---------|
| ComparisonTable | Main side-by-side table |
| CompareCheckbox | Add-to-compare toggle on school cards |
| FloatingCompareBar | Bottom bar showing selected schools |
| ComparisonBadge | Auto-generated insight badges |
| AttributeRow | Single comparison attribute row |
| SchoolCompareCard | School header in comparison view |
| PackageCompareSection | Package-level comparison |
| ReviewHighlightCard | Top review excerpt per school |
| ProfileCompletenessBar | School dashboard completeness indicator |
| CompareShareButton | Copy/share comparison link |

---

# 12. Zustand Store

---

# comparisonStore

```text
/store/comparisonStore
```

Responsibilities:
* selected schools for comparison (max 4)
* add/remove school
* clear comparison
* comparison view open/closed state

---

# 13. API Requirements

---

# Endpoints

```text
GET /api/schools/compare?ids=1,2,3
```

Returns full comparison data for specified school IDs including:
* all comparison fields
* aggregated package pricing
* review summary (count, average, top review)
* verification status

---

# 14. Framer Motion Animations

---

# Required Animations

* Floating bar slide-up when first school added
* School card add/remove transition
* Badge reveal animation on comparison page load
* Attribute row stagger animation
* Mobile swipe transitions between schools
* Highlight animation for "best in category" cells

---

# Animation Style

Same as Geo-Aware module:
* smooth, premium, trustworthy
* no flashy or gaming-style effects

---

# 15. SEO & Sharing

---

# Comparison Pages

* Dynamic title: "Compare [School A] vs [School B] in Pune"
* Meta description with key differentiators
* FAQ schema: "How to choose between [A] and [B]?"
* Breadcrumb schema

---

# Programmatic Comparison Pages (Future)

Auto-generate popular comparisons:
```text
/compare/abc-vs-xyz-pune
/compare/best-driving-schools-baner
```

---

# 16. Performance

---

# Targets

| Metric | Target |
|--------|--------|
| Compare API response | < 500ms |
| Table render | < 1s |
| Floating bar interaction | < 100ms |

* Lazy-load review highlights
* Cache comparison data in Zustand
* Debounce add/remove actions

---

# 17. Accessibility

---

# Requirements

* Comparison table must be a proper HTML table with headers
* Screen reader announces "School A is best rated at 4.8"
* Keyboard navigable (tab through cells)
* ARIA labels on all interactive elements
* Color is not the only differentiator (use icons + text)

---

# 18. Legal

---

# Disclaimer

```text
Pricing and features are as reported by driving schools.
Verify details directly with the school before enrolling.
```

---

# 19. Implementation Phases

---

## Phase 1 — MVP (With Geo-Aware Module)

* Add new boolean/structured fields to school schema
* School dashboard profile page updates (new tabs)
* Compare checkbox on search results
* Floating compare bar
* Basic comparison table (full page)
* Compare API endpoint

## Phase 2 — Enhanced

* Smart badges (best rated, best value, etc.)
* Shareable comparison URLs with OG metadata
* Package-level comparison
* Review highlights in comparison
* Profile completeness score for schools

## Phase 3 — Intelligence

* Learner preference capture
* "Best match for you" highlighting
* Programmatic comparison SEO pages
* AI-powered comparison summaries

---

# 20. Success Metrics

| Metric | Target |
|--------|--------|
| Compare feature usage | > 15% of search sessions |
| Inquiry conversion from compare | > 25% higher than direct |
| Average schools compared | 2.5+ per session |
| Profile completeness (schools) | > 70% within 30 days |
