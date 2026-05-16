# DriveIQ Pune MVP: Current Codebase Reality

## Gap Analysis + Implementation Phases

Version: 1.0  
Date: 2026-05-16

---

## 1. Purpose

This document maps the original PRD vision to the **current repository implementation** and defines a practical phased execution plan.

Source PRD: `docs/requirements/driveiq-prd-srs.md`  
Current codebase baseline: monorepo with React+Vite frontend, Express API, PostgreSQL + Drizzle, OpenAPI contract-first generation.

---

## 2. Current Reality Snapshot

### 2.1 Implemented Today
- Public marketplace routes exist: home, search, school detail, locality detail, about, contact, auth pages.
- School dashboard routes exist: overview, leads, profile, packages, reviews, analytics.
- Admin routes exist: overview, schools, reviews, localities, users (users currently placeholder).
- API domains implemented: health, schools, localities, reviews, inquiries, packages, stats.
- Database entities implemented: schools, localities, inquiries, reviews, packages.
- Validation pattern implemented with generated Zod schemas from OpenAPI.

### 2.2 Demo/Non-production Behaviors Present
- Auth is frontend-only demo role switching via Zustand; no real session/auth backend.
- Role enforcement is route-guard-only in frontend; API endpoints are not protected by server-side auth/RBAC.
- Dashboard school context is demo-oriented (fixed school assumptions in parts of flow).

### 2.3 Stack Reality vs PRD Stack
- PRD proposes Next.js + Laravel + Sanctum + Redis queue.
- Current implementation is React+Vite + Express + Drizzle/Postgres; no Laravel/Sanctum backend.
- Recommendation: continue current stack for MVP speed unless a hard org constraint requires platform migration.

---

## 3. PRD-to-Codebase Gap Analysis

## 3.1 Product and Discovery Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| Geo detection | Auto geo-detect + near me flow | No production geolocation engine/radius query pipeline | High |
| Geo ranking | Distance + rating + reviews + response speed + verified + premium | Listing filters exist, but no weighted geo ranking engine | High |
| Radius filtering | 2/5/10 km | Not implemented in DB/API contract | High |
| Maps | Google Maps location on profile | Not consistently implemented as integrated maps flow | Medium |
| Women-only filter | Women-only training | Women instructor filter exists; women-only program semantics not explicit | Medium |

## 3.2 Lead and Inquiry Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| Inquiry channels | WhatsApp, callback, direct call, form | Form + contact fields available; channel attribution limited | Medium |
| Lead fields | Name, Phone, Area, Vehicle Type, Preferred Timing, Message | Inquiry schema lacks explicit `area` and `preferredTiming` | High |
| Lead lifecycle | Status tracking + school notification pipeline | Status updates exist; notification workflow not implemented | High |
| Spam controls | inquiry throttling + protection | Not implemented | High |

## 3.3 Trust, Reviews, Verification Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| Review eligibility | Only post inquiry/enrollment | Not enforced server-side | High |
| Abuse reporting | Report abuse flow | Not implemented | High |
| Verification types | phone/business/location/premium verification | Single boolean `verified` only | High |
| Verified reviews | Distinct verified review signal | Not implemented as separate verification model | Medium |

## 3.4 School Dashboard Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| Upload photos | Manage media assets | Basic profile fields exist; media pipeline not fully defined | Medium |
| WhatsApp integration | school-side integration controls | Limited to contact fields/deeplink usage | Medium |
| Analytics depth | views, call clicks, WhatsApp clicks, locality traffic | Stats endpoints provide inquiry/review aggregates only | High |
| Service-area intelligence | geo service coverage | service areas stored as text arrays; no geo coverage logic | Medium |

## 3.5 Admin and Operations Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| User management | full lifecycle | Admin users page placeholder | High |
| Featured listing controls | monetization-aware placements | Not implemented as productized ranking/config module | High |
| SEO page management | admin-managed SEO pages | Not implemented | High |
| Lead ops tooling | assignment, workflow automation | basic list/update only | Medium |

## 3.6 SEO and Content Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| Locality SEO landing templates | targeted slug pages | Locality routes exist, but SEO content system not mature | Medium |
| Comparison/blog/guides | content strategy engine | Not implemented in product | Medium |
| Structured SEO system | metadata/programmatic pages | Partial route presence, no full SEO ops workflow | Medium |

## 3.7 Security, Privacy, and Compliance Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| OTP login | secure auth entry | Not implemented | High |
| API security | secure tokens + auth middleware | Not implemented | High |
| Rate limiting | anti-abuse baseline | Not implemented | High |
| Geo privacy controls | consent + strict usage boundaries | Consent handling not formalized | Medium |

## 3.8 Technical Architecture Gaps

| Area | PRD Expectation | Current State | Gap |
|---|---|---|---|
| PostGIS geo storage/query | ST_DWithin-based search | No PostGIS location columns/query pipeline | High |
| Queue/notifications | Redis-backed workflows | Not implemented | Medium |
| Observability maturity | monitoring/alerts baseline | basic logging exists; no full ops stack | Medium |

---

## 4. Phase-wise Implementation Plan

## Phase A: Stabilize Existing MVP Foundation (2-4 weeks)
Goal: Make current stack production-safe for basic launch in Pune pilot.

Scope:
- Add backend authentication and session/token model (can remain Express-based).
- Enforce server-side RBAC for school/admin endpoints.
- Add API rate limiting and basic anti-spam for inquiry endpoints.
- Expand inquiry schema with `area` and `preferredTiming` fields.
- Add audit-friendly event logging for leads and moderation actions.

Exit criteria:
- Protected APIs cannot be accessed without valid auth context.
- Inquiry abuse controls active and tested.
- Core CRUD + role-based flows pass smoke/regression tests.

## Phase B: Geo-Intelligent Search MVP (3-5 weeks)
Goal: Deliver the core differentiator: nearby and radius-based discovery.

Scope:
- Add latitude/longitude + geo-compatible columns for schools.
- Introduce PostGIS and implement radius search API (2km/5km/10km).
- Implement ranking v1: distance + rating + review count + verified + premium weight.
- Add geolocation consent flow and near-me user entry path on homepage/search.
- Add Google Maps display on school profile.

Exit criteria:
- Near-me and radius filters produce deterministic geo results.
- Ranking behavior documented and validated on seeded Pune data.

## Phase C: Trust and Review Integrity (2-3 weeks)
Goal: Build trust engine needed for conversion.

Scope:
- Add verification model expansion: phone/business/location/premium flags.
- Enforce review eligibility rule (inquiry or enrollment proof).
- Add report-abuse workflow and moderation queue.
- Surface verification badges in listing/detail ranking and UI.

Exit criteria:
- Ineligible review submission blocked server-side.
- Abuse reports are actionable in admin workflow.

## Phase D: Lead Engine and Notifications (2-4 weeks)
Goal: Improve conversion and lead response quality.

Scope:
- Add lead channel attribution (WhatsApp/callback/call/form).
- Implement notification pipeline for new leads (start with click-to-chat + email; queue-ready architecture).
- Add response-time tracking for schools.
- Add richer lead status workflow and basic SLA views.

Exit criteria:
- Every lead has source/channel metadata and lifecycle trail.
- Schools receive near-real-time lead notifications.

## Phase E: SEO and Growth Engine (3-4 weeks)
Goal: Enable organic acquisition at locality granularity.

Scope:
- Implement programmatic SEO landing page templates for Pune localities/use-cases.
- Add metadata schema framework and canonical strategy.
- Add admin tooling for SEO page content management (minimum viable controls).
- Add content modules for guides/comparison pages.

Exit criteria:
- Priority locality pages indexed with stable metadata and crawlability.
- SEO page generation workflow is repeatable by content/admin users.

## Phase F: Monetization and Premium Marketplace Controls (3-5 weeks)
Goal: Move from pilot demand validation to revenue model execution.

Scope:
- Add listing tier model: basic/featured/premium.
- Add premium ranking controls and sponsored placement inventory.
- Add admin control plane for featured slots and campaign windows.
- Add school-side subscription status visibility.

Exit criteria:
- Premium visibility logic is configurable and auditable.
- Marketplace can run both free and paid listing states.

---

## 5. Recommended Execution Strategy

### 5.1 Platform Decision
- Keep current codebase architecture (React+Vite + Express + Postgres) through Pune MVP.
- Do not replatform to Next.js/Laravel during MVP unless mandated; prioritize feature completion and market validation speed.

### 5.2 Delivery Priorities (Must-have before broad launch)
1. Server-side auth + RBAC
2. Inquiry anti-spam + throttling
3. Geo search with PostGIS radius logic
4. Review integrity enforcement
5. Lead notification baseline

### 5.3 Post-launch Enhancements
1. SEO CMS controls
2. Monetization tiers and featured inventory
3. Advanced analytics (CTR, locality conversion, response speed trends)

---

## 6. Traceable Backlog (High-level)

### Epic 1: Security and Identity
- Backend auth implementation
- Role-based middleware
- OTP or equivalent login flow
- Token/session hardening

### Epic 2: Geo Discovery
- Schema migration for geospatial fields
- PostGIS enablement
- Distance/radius query APIs
- Geo ranking service module

### Epic 3: Lead System
- Inquiry schema extension
- Channel attribution
- Lead notification services
- Lead analytics events

### Epic 4: Trust and Moderation
- Verification taxonomy
- Review eligibility rules
- Abuse reporting
- Admin moderation queue UX/API

### Epic 5: SEO and Growth
- Programmatic landing framework
- SEO metadata pipeline
- Content templates
- Admin SEO management tooling

### Epic 6: Monetization
- Subscription tiers data model
- Premium ranking logic
- Featured slot management
- Billing integration (future)

---

## 7. Risks and Mitigations

- Risk: Replatforming slows MVP by 2-3x.
  - Mitigation: Build on existing stack; postpone framework migration.
- Risk: Weak lead quality due to spam.
  - Mitigation: throttling + validation + verification + moderation.
- Risk: Geo ranking quality issues reduce trust.
  - Mitigation: deterministic weighted scoring + offline tuning on Pune seed data.
- Risk: Cold-start in supply side.
  - Mitigation: manual onboarding playbook + featured placement incentives.

---

## 8. Definition of Done for “PRD-aligned MVP Reality”

The platform is considered PRD-aligned for Pune MVP when:
- Geo search (near-me + radius) is production-usable with ranking v1.
- Leads include required fields and channel attribution.
- School/admin operations are secured server-side.
- Review and verification trust controls are enforced.
- Priority locality SEO pages are live and indexable.

