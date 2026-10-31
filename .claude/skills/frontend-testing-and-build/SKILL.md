---
name: frontend-testing-and-build
description: Run and verify the DriveQ frontend - pnpm commands, typecheck/build as the CI gate, env vars, Docker/make helpers, manual verification with demo data, and the marketing/ screenshot and video scripts. Use before finishing any frontend change or when asked about frontend tests, CI or build.
---

# Testing and build (frontend/)

## Reality check
There is **no automated frontend test runner** (no vitest/jest/React Testing Library, no ESLint/Prettier config, no `test` script in `frontend/package.json`; tsconfig excludes `**/*.test.ts`). The CI job `Frontend typecheck & build` (`.github/workflows/ci.yml`, Node 22, pnpm via corepack) runs only:
```bash
pnpm install --frozen-lockfile
pnpm typecheck          # tsc -p tsconfig.json --noEmit (strict, noImplicitReturns)
VITE_API_BASE_URL=http://localhost:8000 pnpm build
```
Both must pass. Do not claim a feature is tested unless you drove it in a browser.

## Before you finish
1. `cd frontend && pnpm typecheck` (fix all errors; if only `laravel-echo`/`pusher-js` modules are missing, run `pnpm install` - they are real dependencies, used by dynamic import in `src/lib/realtime.ts`).
2. `pnpm build` for route/import errors.
3. Manual run: backend + seed, then `pnpm dev` (http://localhost:5173).
   - `make up` (docker) or `make up-infra` + `cd backend && php artisan serve` (:8000) + `php artisan queue:listen`.
   - `make demo` loads showcase data; logins (password `password123`): owner `info@skylinedrive.in`, admin `admin@driveiq.in`, trainer `trainer.skyline@driveiq.in`, learner `learner.asha@driveiq.in`. Never run demo/seed against production.
   - Walk the changed route in each affected role and in light/dark and at 390px width; check console and network for 401/402/422.
4. Env: copy `frontend/.env.example` to `.env`: `VITE_API_BASE_URL` (origin or ending `/api`), `VITE_GOOGLE_MAPS_API_KEY` (optional; map hidden without it), `VITE_REVERB_*` (optional; polling fallback).

## If you must add tests
Keep scope small: add `vitest` + `@testing-library/react` + `jsdom` as devDependencies, a `test` script, and a CI step after typecheck; put tests next to units (pure logic first: `lib/lead-status.ts`, `lib/plan.ts`, `i18n/translate`, `lib/consent.ts`). Update `frontend-testing-and-build` and `frontend/CLAUDE.md` when you do. Lean on existing `data-testid` attributes for selectors.

## Marketing scripts (root `marketing/`)
- `node marketing/screenshots.cjs` -> desktop 1440x900@2x and phone 390x844@3x PNGs of public, school dashboard and admin acquisition pages (needs app on :5173, API :8000, demo data).
- `node marketing/video/record.cjs --lang en|hi|mr --format walkthrough|short` records captioned demos (captions: `marketing/video/captions.json`; optional `FFMPEG`, `CLAIM_URL`). These script against real selectors/`data-testid`s, so renaming UI text or testids can break them; re-run after UI changes.

## Docker
`frontend/Dockerfile` is dev-only (no build stage); compose runs `pnpm install && pnpm dev` with `VITE_API_BASE_URL=http://localhost:8000/api`. Production image/reverse proxy is open work (DIQ-1301/1302); `make prod-up` uses `docker-compose.prod.yaml`.

## Security verification
- Run `cd frontend && pnpm audit --prod --audit-level=high` before finishing any change that touches `package.json`/lockfile (needs network; if offline say so). Last run: zod high and postcss-selector-parser moderate open (FSEC-08).
- Confirm `grep -rn "dangerouslySetInnerHTML\|innerHTML\|console\.\(log\|debug\)" frontend/src` shows no new hits, and `dist/` has no `*.map` files.
- Never commit `frontend/.env`; only `VITE_` variables that are safe to be public (API base URL, Reverb app key, restricted Maps key).
- For PRs touching `frontend/`, walk `.claude/skills/frontend-security-review/SKILL.md`.
