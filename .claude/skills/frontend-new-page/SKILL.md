---
name: frontend-new-page
description: Add a new route/page to the DriveQ React frontend (public, school dashboard, admin, instructor, learner) with correct layout, role guard, nav link, i18n keys and data-testids. Use when asked to "add a page/screen/route" under frontend/.
---

# Add a page to frontend/

1. **Pick the area and layout** (each page wraps itself; there is no nested router):
   - Public: `src/pages/<name>.tsx`, wrap in `PublicLayout` (`components/layout/public-layout.tsx`).
   - School portal: `src/pages/dashboard/<name>.tsx`, wrap in `DashboardLayout`.
   - Admin: `src/pages/admin/<name>.tsx` -> `AdminLayout`. Trainer: `pages/instructor/` -> `InstructorLayout`. Learner: `pages/learner/` -> `LearnerLayout`.
   - Portal layouts already include `RoleConsentGate`, notification bell, language switcher; do not re-add.
2. **Write the component**: `export default function XPage()`. Follow `pages/dashboard/leads.tsx` (school data) or `pages/instructor/index.tsx` (ops-api data). Use `Skeleton` while `isLoading`, empty states, `useToast()` for results.
3. **Data**: see skill `frontend-api-integration`. School data needs `const schoolId = useSchoolId()` and `enabled: !!schoolId`.
4. **Register route in `src/App.tsx`**:
   - Import the page at the top (static import; the app has no lazy loading).
   - Public: `<Route path="/x" component={XPage} />`.
   - School: `<Route path="/dashboard/x">{() => schoolGuard(<XPage />)}</Route>`.
   - Others: `<Route path="/admin/x">{() => <AuthGuard requireRole="admin"><XPage /></AuthGuard>}</Route>` (roles: `school|admin|instructor|learner`; admin may enter school routes).
   - Put it above the catch-all `<Route component={NotFound} />`. Dynamic params via `useParams<{slug:string}>()`; query string via `window.location.search`/`useSearch`.
5. **Navigation**: add a link object to the `links` array in the matching layout (`components/layout/*-layout.tsx`) with a lucide icon and a `t("xxxNav.key")` label; owner-only links check `schoolRole !== "manager"`.
6. **Plan-gated module** (school premium features): add the feature to `PlanFeature`, `ROUTE_FEATURE`, `FEATURE_PLAN` in `src/lib/plan.ts` (must mirror backend `config/plans.php`); sidebar lock icon appears automatically; writes may 402 and the global listener toasts.
7. **i18n**: new nav/chrome/primary-flow strings go in `src/i18n/locales/en.ts`, then mirrored in `hi.ts` and `mr.ts` (type-checked), used as `t("ns.key")` via `useT()`.
8. **SEO (public pages)**: no per-route meta exists; add `<JsonLd data={...}/>` from `components/seo/json-ld.tsx` for structured data (see `school-detail.tsx`). Do not add Google scripts eagerly.
9. **Test hooks**: add `data-testid` to inputs/buttons/status text (`button-save`, `input-name`, `text-error`).
10. **Verify**: `cd frontend && pnpm typecheck && pnpm build`; open in `pnpm dev` with backend seeded (`make seed`, demo logins in `marketing/README.md`).

## Security checklist for new pages
- The `AuthGuard`/`schoolGuard()` is UX only: confirm the backend endpoint enforces role, school and plan, and note the guard in the PR.
- Query keys include the school/user id; gate with `enabled: !!schoolId`; handle 401/402/403 and empty/error states without showing raw 5xx messages.
- Token-in-URL pages (`?token=`, `/x/:token`): read once, then `history.replaceState`; never log or send it to third parties.
- Any `next`/`returnTo` goes through `safeNextPath()`. Any API URL goes through the safe-URL helper before `href`.
- Rendering user/school text: plain React text only; no `dangerouslySetInnerHTML`.
- Destructive buttons use the confirm dialog, not `window.confirm`.
