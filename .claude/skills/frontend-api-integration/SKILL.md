---
name: frontend-api-integration
description: Call the DriveQ Laravel API from the React frontend - choosing between the generated orval client and the hand-written ops-api request() helper, auth token handling, TanStack Query keys, error/402/401 handling. Use when adding or changing any fetch/useQuery/useMutation in frontend/.
---

# API integration in frontend/

## Which client
| Need | Use |
|---|---|
| schools, localities, reviews, inquiries, packages, stats (original 7 modules) | generated hooks from `@/api-client` (`useListSchools`, `useUpdateInquiry`, `getListInquiriesQueryKey`, ...). Never edit `src/api-client/generated/*` |
| Anything else (learners, instructors, schedules, vehicles, payments, billing, admin, consent, contact, data requests) | `request<T>()` in `src/lib/ops-api.ts` |
| Prospects / outreach / claim flow | `src/lib/acquisition-api.ts` (imports `request`) |
| login/register/me/password reset | `src/lib/auth-api.ts` via `useAuthStore` (`src/lib/store.ts`) - do not call directly from pages |
| notifications | `src/lib/notifications-api.ts` + `hooks/use-notifications.ts` |

New code should extend `ops-api.ts` (or a new `lib/<domain>-api.ts` that imports `request`), never add a fourth copy of `fetch` + `API_BASE`.

## Steps to add an endpoint
1. In `lib/ops-api.ts` (match neighbours):
   ```ts
   export type Foo = { id: number; name: string };
   export function listFoos(schoolId: number) { return request<Foo[]>(`/api/schools/${schoolId}/foos`); }
   export function createFoo(schoolId: number, body: Record<string, unknown>) {
     return request<Foo>(`/api/schools/${schoolId}/foos`, { method: "POST", body: JSON.stringify(body) });
   }
   ```
   Paths include the `/api` prefix. `FormData` bodies are passed raw (no JSON header). 204 returns `undefined`. Type the response properly; avoid `unknown[]` like older functions.
2. In the page/hook:
   ```ts
   const schoolId = useSchoolId();
   const q = useQuery({ queryKey: ["foos", schoolId], queryFn: () => listFoos(schoolId!), enabled: !!schoolId });
   const m = useMutation({ mutationFn: (b) => createFoo(schoolId!, b),
     onSuccess: () => { qc.invalidateQueries({ queryKey: ["foos", schoolId] }); toast({ title: "Saved" }); },
     onError: (e: Error) => toast({ title: "Failed", description: e.message, variant: "destructive" }) });
   ```
   Key shape: `[resource, schoolId, ...filters]`; admin: `["admin", resource, filters]`; portal-self: `["instructor","me"]`. Defaults in `App.tsx`: retry 1, staleTime 30s.
3. For generated hooks pass `{ query: { enabled, queryKey: getXQueryKey(params) } }` (see `pages/dashboard/leads.tsx`).

## Cross-cutting behaviour (already handled; do not duplicate)
- Token: `localStorage["driveiq_auth_token"]`, attached by `request()` and by the generated client via `setAuthTokenGetter` (set in `main.tsx`).
- Base URL: `VITE_API_BASE_URL` with trailing `/api` stripped by each client; generated client gets `${origin}/api`.
- 401 with token -> `handleUnauthorized()` clears token, redirects portal paths to `/auth/login?expired=1`.
- 402 `{code:"plan_required"}` -> `announcePlanRequired` -> global toast + `["entitlements"]` invalidation. Pre-emptively lock UI with `useEntitlements().has("learners")`.
- Errors: `request()` throws `Error(message)` using Laravel `message` or joined `errors`. Generated client errors expose `err.data.{message,errors}` (see `school-detail.tsx` handleInquiry).
- Public inquiry POST must include `formStartedAt`, `website` (honeypot, empty), `whatsappOptIn`; the generated `InquiryInput` type lacks them, so intersect the type.
- Consent writes go through `lib/consent.ts` (`recordConsents` queues retries) not raw requests.
- Server is the authority on RBAC and school isolation; client never sends trust-bearing `school_id` assumptions beyond route params.

## Checklist
- Typed response, `enabled` guard, school-scoped key, invalidate after mutation, toast on error, loading `Skeleton`.
- Run `pnpm typecheck`. If backend response shape is unknown, read the controller in `backend/app/Http/Controllers/` rather than guessing.

## Security rules (see `docs/security/FRONTEND-SECURITY-AUDIT.md`)
- The token lives in localStorage (`driveiq_auth_token`), so never log it, put it in a URL, or hand it to third-party scripts. Read it only through `getStoredToken()`.
- Logout, 401 and login switch must clear the TanStack `QueryClient` (`queryClient.clear()`) and per-user storage; scope query keys by user/school id.
- Show server `message` only for 4xx; for 5xx/network show a generic string. Never render API strings as HTML.
- URLs from the API (`url`, `link`, `website`, `tel`, `mailto`) go through a safe-URL helper (https/allowlisted host, normalised phone, encoded email) before `href`/`location.assign`.
- Return URLs (`next`, `returnTo`) must pass `safeNextPath()` (leading single `/`, no `//`, `\`, control chars).
- Guards and `useEntitlements` only hide UI. Never ship secrets or rely on a client role check; the endpoint must enforce role, school and plan (402).
- Single-use tokens in URLs (claim, reset, invite, review): read once, then `history.replaceState` to remove them.
- Do not add `console.log` of responses; no PII or tokens in storage beyond the documented `driveiq_*` keys.
