# DriveQ Frontend — AI Assistant Guide

React 19 + Vite 7 + Tailwind 4 + wouter SPA (pnpm, Node 22 in CI). Verified against code on 2026-10-31.
Root context: `../CLAUDE.md`. Plan/status: `../docs/PROJECT-PLAN.md`. i18n rules: `../docs/I18N.md`.

## 1. Structure map (`src/`)

| Path | What lives there |
|---|---|
| `main.tsx` | Boot: `setBaseUrl`, `setAuthTokenGetter`, `setUnauthorizedHandler`, `hydrateAuth()`, locale, UTM `captureAttribution()` |
| `App.tsx` | `QueryClient` (retry 1, staleTime 30s), all routes (wouter `Switch`), global Toaster x2, `CookieConsent`, `PlanRequiredListener` |
| `pages/` | 60 route components (default export). `admin/`, `dashboard/`, `instructor/`, `learner/` + public pages at root |
| `components/layout/` | `PublicLayout`, `DashboardLayout` (school), `AdminLayout`, `InstructorLayout`, `LearnerLayout` — each page wraps itself |
| `components/ui/` | shadcn "new-york" primitives (55 files, ~27 unused). Do not hand-restyle; extend via `cn()` |
| `components/` (rest) | Feature widgets: `school-card`, `documents-panel`, `lead-timeline-sheet`, `schedule/`, `learners/`, `instructors/`, `maps/`, `notifications/`, `plan/`, `legal/`, `seo/json-ld.tsx` |
| `features/comparison`, `features/geo-rules` | Self-contained modules (components/hooks/stores/types; geo-rules ships static `data/india.json`) |
| `api-client/` | `generated/` (orval, DO NOT EDIT) + `custom-fetch.ts` + `index.ts` barrel |
| `lib/*-api.ts` | Hand-written clients: `auth-api`, `ops-api` (+ shared `request()`), `acquisition-api`, `notifications-api` |
| `lib/store.ts` | Zustand: `useAuthStore`, `useThemeStore`, `useFilterStore` (unused) |
| `lib/consent.ts`, `plan.ts`, `lead-status.ts`, `attribution.ts`, `realtime.ts`, `response-time.ts` | Cross-cutting helpers |
| `hooks/` | `use-school-id`, `use-entitlements`, `use-notifications`, `use-toast`, `use-mobile` |
| `i18n/` | `locales/{en,hi,mr}.ts`, `use-locale.ts` (`useT`, `useLocaleStore`), `index.ts` (`translate`) |

Alias `@` -> `src`. `../../frontend_old/` is the legacy Replit workspace: ignore. `../../marketing/` drives demo videos/screenshots.

## 2. Routing and role gating (`App.tsx`, `components/auth-guard.tsx`)

- Wouter with `base = import.meta.env.BASE_URL`. Routes are inline `<Route>`; no lazy loading (single bundle).
- Roles (`UserRole` in `lib/auth-api.ts`): `school` (owner OR manager; distinguish with `schoolRole`), `admin`, `instructor` (= trainer portal), `learner`.
- Guard pattern: `<AuthGuard requireRole="admin">` (inline) or `schoolGuard()` helper. `admin` is allowed into `school` routes. Unauthed -> `/auth/login`; wrong role -> `roleHomePath(role)`.
- Guards are UX only; server enforces RBAC/school isolation. Owner-only UI is hidden by `schoolRole !== "manager"` (see `dashboard-layout.tsx`, audit link).
- Portal layouts wrap content in `RoleConsentGate` (DPDP notice before portal use).

| Route(s) | Page(s) | Data layer |
|---|---|---|
| `/`, `/search`, `/school/:slug`, `/locality/:slug`, `/compare?schools=1,2` | `home`, `search`, `school-detail`, `locality-detail`, `compare` | generated hooks (`useListSchools`, `useGetSchoolBySlug`, ...); compare via `features/comparison` |
| `/about /contact /driving-rules /privacy /terms /privacy/data-request` | static + `features/geo-rules` | `ops-api` for contact/data-request |
| `/auth/{login,register,forgot-password,reset-password}` | auth pages | `auth-api` via `useAuthStore` |
| `/for-schools`, `/for-trainers` | `for-listings` (`audience` prop) | public |
| `/claim/:token`, `/unsubscribe/:token`, `/team/accept`, `/review?token=` | token landing pages | `acquisition-api` / `ops-api` |
| `/dashboard/*` (leads, learners, instructors, schedules, vehicles, payments, messages, team, profile, packages, reviews, analytics, settings, audit, billing, billing/invoices/:id) | school portal | mix: leads/reviews/packages/profile/analytics use generated hooks; rest `ops-api` |
| `/admin/*` (schools, reviews, localities, users, analytics, data-requests, messages, billing, prospects, outreach, audit) | admin portal | generated (schools/reviews/localities) + `ops-api`/`acquisition-api` |
| `/instructor/*` (sessions, learners, leave, messages; `/attendance` aliases sessions) | trainer portal | `ops-api` |
| `/learner/*` (progress, sessions, documents, messages) | learner portal | `ops-api` |

## 3. API layer — three parallel clients (know which to use)

1. **Generated (orval 8.9.1)**: `api-client/generated/api.ts` + `api.schemas.ts`, imported from `@/api-client`. Only covers the original 7 modules (health, schools, localities, reviews, inquiries, packages, stats). Types are stale (e.g. `InquiryInput` lacks `formStartedAt`, `website`, `whatsappOptIn`; pages intersect-type them). No orval config or OpenAPI spec is in the repo, so cannot be regenerated yet.
2. **`lib/ops-api.ts`**: exports `request<T>(path, init)` (adds Bearer token, 401 -> `handleUnauthorized`, 402 -> `announcePlanRequired`, JSON/FormData). ~1100 lines of per-endpoint functions, many typed `Record<string, unknown>`/`unknown[]`. `acquisition-api.ts` reuses `request`. **This is the default for new endpoints.**
3. **`auth-api.ts` and `notifications-api.ts`** each re-implement `fetch` + `API_BASE` stripping (duplicated). `auth-api` throws `ApiValidationError(fieldErrors)` on 422.

- Token: `localStorage["driveiq_auth_token"]` (Sanctum bearer). `VITE_API_BASE_URL` may or may not end in `/api`; every client strips it and re-adds `/api`.
- 401 on signed-in request -> token dropped; inside a portal path hard-redirects `/auth/login?expired=1`.
- `402 plan_required` -> window event -> `PlanRequiredListener` toast. UI locks via `useEntitlements().has(feature)`; routes->feature map is `ROUTE_FEATURE` in `lib/plan.ts` (mirrors backend `config/plans.php`).
- Query keys: generated pages use `getXQueryKey()`; hand-written use arrays, school-scoped: `["entitlements", schoolId]`, `["admin","users",{...}]`, `["instructor","me"]`. Invalidate with `queryClient.invalidateQueries({ queryKey })`; some pages use a sloppy `predicate: String(key[0]).includes("inquiries")`.
- Always gate school queries with `enabled: !!schoolId` (`useSchoolId()`).
- Realtime: `lib/realtime.ts` lazily imports `laravel-echo`/`pusher-js` only if `VITE_REVERB_APP_KEY` set; notifications poll every 30s otherwise (Reverb not live: DIQ-1404/1405).

## 4. State

- Zustand: `useAuthStore` (isLoggedIn, userRole, user, token, schoolId, schoolRole, login/register/logout/hydrateAuth), `useLocaleStore`, `useThemeStore` (`vite-ui-theme`), `features/geo-rules/stores/location-store` (`driveiq-location`, localStorage), `features/comparison/stores/comparison-store` (sessionStorage `driveiq-compare`, max 4).
- Server state = TanStack Query. Search filters are local `useState` in `pages/search.tsx` (not the store).
- localStorage keys all use `driveiq_` prefix (kept after rebrand to DriveQ; do not rename): `driveiq_auth_token`, `driveiq_locale`, `driveiq_attribution`, `driveiq_device_id`, consent keys. Always wrap storage in try/catch.

## 5. Forms

React Hook Form + Zod + `@hookform/resolvers` are installed but **no page uses them** (only `components/ui/form.tsx` wrapper). Every form is controlled `useState` + manual `required`/toast. When adding a form, prefer RHF+Zod (matches stack decision in PROJECT-PLAN) but staying consistent with neighbours is acceptable; surface server `errors` (Laravel 422 `{message, errors:{field:[...]}}`) via toast or `fieldErrors`.
Public inquiry form (`school-detail.tsx`) must send anti-spam fields `formStartedAt` (ms when opened) and honeypot `website`; WhatsApp opt-in is `whatsappOptIn`.

## 6. i18n, consent, SEO, maps

- `useT()` -> `t("ns.key", {var})`; fallback to English then the key. Add keys to `en.ts`, then mirror `hi.ts` and `mr.ts` (TS enforces shape). Coverage is partial: layouts/nav, login/register chrome, school-detail, claim, compare, reset flows use `t()`; most portal page bodies and `search.tsx` filters are hardcoded English.
- Consent (DPDP): server `/api/consents` is truth; `lib/consent.ts` caches in localStorage and retries pending grants. `CookieConsent` banner (global), `RoleConsentGate` (portals), register-time consent (`saveRegisterConsent`), search "use my location" shows a purpose notice before `navigator.geolocation`.
- SEO: `index.html` has the only `<title>`/meta/OG; **no per-route title/meta management** (no Helmet or `document.title`). Per-page structured data via `<JsonLd data>` (school-detail: LocalBusiness+FAQ+Breadcrumb; locality-detail). `JsonLd` escapes `<`.
- Maps: `school-map.tsx` = click-to-load iframe embed (needs `VITE_GOOGLE_MAPS_API_KEY`, else only "Open in Google Maps" link). `schools-map.tsx` = singleton script loader, only when map view opened. Never load Google scripts eagerly (DPDP + LCP).
- Near me: homepage has no near-me entry (open item DIQ-1312); search page has it.

## 7. Styling / design system

- Tailwind 4 via `@tailwindcss/vite`; tokens in `src/index.css` (`@theme inline` -> HSL CSS vars; light "Deep Blue Premium", `.dark` class variant; primary `221 83% 53%`). Font Inter (Google Fonts import in CSS and `index.html`).
- Use semantic tokens (`bg-card`, `text-muted-foreground`, `border`, `text-primary`), `cn()` from `@/lib/utils`, lucide icons, framer-motion for page entrances. Status colours use paired light/dark classes (see `lib/lead-status.ts`).
- Brand text is "DriveQ" in UI; identifiers/keys remain `driveiq`. Some old docs/design files still say DriveIQ.
- Every interactive element gets `data-testid` (`button-*`, `input-*`, `text-*`, `link-*`); 57 files follow it.

## 8. Commands

```bash
cd frontend && pnpm install --frozen-lockfile
pnpm dev                # :5173, host 0.0.0.0
pnpm typecheck          # tsc --noEmit (CI gate)
pnpm build              # vite build -> dist/ (CI gate, VITE_API_BASE_URL=http://localhost:8000)
make frontend-check     # typecheck + build from repo root
make demo               # demo data for marketing/ scripts
```
Env (`frontend/.env.example`): `VITE_API_BASE_URL`, `VITE_GOOGLE_MAPS_API_KEY`, `VITE_REVERB_*`. No test runner is configured (no vitest/jest/RTL, no lint, no prettier). CI (`.github/workflows/ci.yml`) = typecheck + build only. Verified: typecheck fails locally only on `laravel-echo`/`pusher-js` types when node_modules is stale; run `pnpm install`.

## 9. Adding things (details in `.claude/skills/frontend-*`)

- **Page**: create `pages/<area>/<name>.tsx` (default export, wrap in the right layout), add `<Route>` + guard in `App.tsx`, add nav link in the layout and `t()` keys.
- **Endpoint**: add typed function to `lib/ops-api.ts` using `request<T>()`; wrap in `useQuery`/`useMutation` in the page or a `hooks/` file; school-scoped key + `enabled`.
- **Plan-gated module**: add to `PlanFeature`, `ROUTE_FEATURE`, `FEATURE_PLAN` in `lib/plan.ts` (mirror backend).

## 10. Gotchas

- Hard-coded `window.confirm`/`window.prompt` used in 14 places for destructive/lost-reason flows.
- `Dockerfile` is dev-only (no build stage/CMD); compose runs `pnpm dev`. Production image is open work (DIQ-1301).
- `AuthGuard` returns `null` during redirect; `isAuthLoading` starts true only if a token exists.
- Search `ListSchoolsParams` values are lower-cased before sending (`locality`, `vehicleType`).
- Compare route takes numeric IDs (`?schools=1,2,3`), not slugs as in `docs/Features/School-Comparison-Engine.md`.
- Locality route is `/locality/:slug`, not `/{slug}` as in PROJECT-PLAN 1.5.
- Do not touch `api-client/generated/*`; add types in the `lib/*-api.ts` file or a local type.

## 11. Tech debt (found, ranked)

1. No frontend tests at all; CI only typechecks/builds. 2. Three divergent fetch clients + generated types out of date, no spec/orval config; many `unknown`/`Record<string, unknown>` types in `ops-api.ts` (1130 lines, should split by domain). 3. No per-route `<title>`/meta/canonical (SEO phase E gap); SPA-only rendering. 4. i18n partial (portal bodies, search filters). 5. RHF/Zod unused; ad-hoc controlled forms with duplicated validation. 6. No code splitting/ErrorBoundary; 60 pages in one bundle. 7. ~27 unused `components/ui` files, `useFilterStore` unused, 2 Toaster systems (`use-toast` + sonner) both in use. 8. `window.confirm/prompt` UX. 9. Homepage lacks near-me entry. 10. Mock-data remnants: none found; `features/geo-rules/data/india.json` is static curated data by design.

## 12. Security checklist (audit: `../docs/security/FRONTEND-SECURITY-AUDIT.md`)

- Guards/`useEntitlements` hide UI only; the API must enforce role, school and plan. Never put secrets behind a client check.
- No `dangerouslySetInnerHTML`/`innerHTML`/`eval` except `JsonLd` (must escape `<`) and `ui/chart.tsx` (developer config). API text is rendered as React text only.
- `target="_blank"` always with `rel="noopener noreferrer"`; API-supplied URLs, `tel:`, `mailto:`, `wa.me` go through a safe-URL helper; `next`/`returnTo` through `safeNextPath()`.
- Token is in localStorage (`driveiq_auth_token`): never log it or put it in URLs. Logout/401 must clear auth store, `QueryClient`, Echo (`window.Echo`) and per-user storage (known gap FSEC-01/04/05).
- Show server messages only for 4xx; no `console.*` of responses or PII.
- Single-use tokens in URLs (claim/reset/invite/review): read once, strip with `history.replaceState`.
- Consent boxes unticked by default and posted to `/api/consents`; keep honeypot + `formStartedAt`.
- Use a confirm dialog, not `window.confirm/prompt`, for destructive actions.
- Third-party scripts (Maps) only on user action; keep a future CSP in mind (no inline scripts).
- Before finishing: `pnpm typecheck && pnpm build`, `pnpm audit --prod --audit-level=high`, no `.map` in `dist/`, `.env` untracked.
