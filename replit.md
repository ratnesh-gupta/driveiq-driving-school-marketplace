# DriveIQ — Driving School Marketplace

The premium marketplace for discovering and booking driving schools in Pune, India. Airbnb-style geo-intelligent platform connecting learners with verified driving schools.

## Run & Operate

- `pnpm --filter @workspace/api-server run dev` — run the API server (port 8080, proxied at /api)
- `pnpm --filter @workspace/driveiq run dev` — run the frontend (Vite, port auto-assigned)
- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from the OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- Required env: `DATABASE_URL` — Postgres connection string

## Stack

- pnpm workspaces, Node.js 24, TypeScript 5.9
- Frontend: React + Vite, wouter routing, Tailwind CSS v4, shadcn/ui, framer-motion, recharts, Zustand, TanStack Query
- API: Express 5 (port 8080, served at /api)
- DB: PostgreSQL + Drizzle ORM
- Validation: Zod (`zod/v4`), `drizzle-zod`
- API codegen: Orval (from OpenAPI spec)
- Build: esbuild (CJS bundle)

## Where things live

- `artifacts/driveiq/` — React+Vite frontend (previewPath `/`)
- `artifacts/api-server/` — Express API server (previewPath `/api`)
- `lib/db/src/schema/` — Drizzle ORM schema (localities, schools, reviews, inquiries, packages)
- `lib/api-spec/openapi.yaml` — OpenAPI 3 contract (source of truth)
- `lib/api-client-react/` — generated React Query hooks
- `lib/api-zod/` — generated Zod schemas

## Architecture decisions

- Contract-first: OpenAPI spec → Orval codegen → typed hooks + Zod validators used in both client and server
- Auth is demo-only (Zustand store, no real session). Login page has a role picker (user/school/admin) for demo access
- Dashboard uses hardcoded `schoolId=1` (Skyline Driving Academy) for demo
- All Select components use `"__all__"` sentinel instead of empty string (Radix UI constraint)
- API routes use Zod schemas from `@workspace/api-zod` for request validation

## Product

**Public marketplace:**
- `/` — Homepage: hero search, popular localities, featured schools, how-it-works, trust section
- `/search` — Full search with sidebar filters (locality, vehicle type, transmission, pickup, rating, price)
- `/school/:slug` — Full school profile with packages, reviews, inquiry form, WhatsApp CTA, FAQ
- `/locality/:slug` — SEO locality page listing all schools in that area
- `/about`, `/contact` — Informational pages
- `/auth/login`, `/auth/register` — Split-screen auth pages

**School owner dashboard** (requires "school" role):
- `/dashboard` — Overview stats (inquiries, rating, this month's leads)
- `/dashboard/leads` — Lead management table with status updates
- `/dashboard/profile` — Edit school info, features, timings
- `/dashboard/packages` — CRUD for pricing packages
- `/dashboard/reviews` — Approve/delete reviews
- `/dashboard/analytics` — Charts: monthly inquiries, status breakdown, rating distribution

**Admin portal** (requires "admin" role):
- `/admin` — Platform overview stats
- `/admin/schools` — Verify/remove schools
- `/admin/reviews` — Approve/remove all reviews
- `/admin/localities` — Add/view localities
- `/admin/users` — User list (placeholder)

## Seeded Demo Data

- 6 localities: Baner, Hinjewadi, Wakad, Hadapsar, Kothrud, Pimpri
- 6 schools: Skyline Driving Academy (Baner, 4.8★), PuneAuto Academy (Hinjewadi, 4.6★), RoadStar (Wakad, 4.3★), Eastern Drive (Hadapsar, 4.5★), Kothrud Driving Hub (4.7★), Pimpri AutoDrive (4.1★)
- 9 reviews, 8 packages, 6 inquiries

## User preferences

- No emojis in UI
- data-testid attributes on all interactive/display elements
- Premium light UI: deep blue primary hsl(221,83%,53%), soft neutral backgrounds, dark mode supported

## Gotchas

- Radix UI `<SelectItem>` does not allow `value=""` — use a sentinel like `"__all__"` and convert to `""` in `onValueChange`
- `zustand` is a runtime `dependencies` in driveiq (not devDependencies) because it's needed at runtime
- Do not run `pnpm dev` at workspace root — use `restart_workflow` instead
- Run `pnpm --filter @workspace/db run push` after schema changes

## Pointers

- See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details
