---
name: frontend-security-review
description: Repeatable security review checklist for any PR or change touching frontend/ in DriveQ (XSS, URLs, auth/session, data exposure, third-party, forms/consent, headers, dependencies). Use when reviewing or self-reviewing frontend changes before merge.
---

# Frontend security review (DriveQ)

Baseline and open findings: `docs/security/FRONTEND-SECURITY-AUDIT.md` (FSEC-xx). Backend is the authority; the client only hides UI.

## Quick scans (run from repo root)
```bash
git diff main -- frontend | grep -nE "dangerouslySetInnerHTML|innerHTML|eval\(|new Function|document\.write"
git diff main -- frontend | grep -nE "console\.|localStorage|sessionStorage|window\.(confirm|prompt|open)|location\.(assign|href)"
git diff main -- frontend | grep -nE "target=\"_blank\"|href=\{|src=\{"
cd frontend && pnpm typecheck && pnpm build && pnpm audit --prod --audit-level=high
ls dist/assets | grep -c '\.map$'      # expect 0
git ls-files frontend | grep -E "(^|/)\.env$"   # expect nothing
```

## Checklist
1. **XSS**: no new `dangerouslySetInnerHTML`/`innerHTML`; JSON-LD only via `JsonLd` (escapes `<`); user/school/review/note text rendered as React text; no HTML from API.
2. **URLs**: API-provided URLs pass a safe-URL helper (https, allowlisted host) before `href`, `src`, `location.assign`; no `javascript:`/`data:`; `tel:`/`mailto:` normalised and encoded; `wa.me` digits only; every `_blank` has `rel="noopener noreferrer"`.
3. **Redirects**: `next`/`returnTo` validated by `safeNextPath()`; no `window.location = userInput`.
4. **Auth/session**: token via `getStoredToken()` only; not logged, not in URLs, not sent to third parties; logout/401/login switch clear auth store, `QueryClient`, Echo and per-user storage; 401 and 402 handled; query keys scoped by user/school.
5. **Authorization**: new routes/buttons have a server-side check (name the controller/middleware in the PR); no secret or privileged data fetched "and hidden"; admin vs school behaviour deliberate.
6. **Data exposure**: no `console.*` with responses/PII; storage limited to documented `driveiq_*` keys, no coordinates, no PII; 5xx messages generic; no new `VITE_` secret; no source maps.
7. **URL tokens** (claim, reset, invite, review, unsubscribe): read once then `history.replaceState`; no third-party requests on those pages.
8. **Realtime**: only the user's own private channel; Echo torn down on logout; no `school.{id}` subscription without a backend membership check.
9. **Third party**: scripts loaded lazily after user action; no analytics before "Accept all"; new CDN/script origins listed for CSP; SRI where the vendor allows.
10. **Forms/consent**: boxes unticked, grants POSTed to `/api/consents`; honeypot and `formStartedAt` kept; file inputs check type and size client-side (server authoritative); destructive actions use the confirm dialog, with reason inputs length-limited.
11. **Headers/CSP**: no inline `<script>` added to `index.html`; no new framing, no `unsafe-eval` dependency.
12. **Dependencies**: new packages justified, maintained, no install scripts surprises; `pnpm audit` has no new high/critical; unused packages removed.

Report: list each checked item as OK / N/A / finding with file:line, severity (High/Medium/Low) and fix. Do not include working exploit payloads.
