---
name: frontend-ui-conventions
description: DriveQ frontend UI conventions - Tailwind 4 tokens, shadcn/Radix components, layouts, icons, i18n t() usage, data-testid naming, consent/Maps/JSON-LD rules, state (Zustand) and form patterns. Use when writing or reviewing any React component or styling in frontend/.
---

# UI conventions (frontend/)

**Components**
- Import primitives from `@/components/ui/*` (shadcn new-york; config `frontend/components.json`; aliases `@/components`, `@/lib/utils`, `@/hooks`). Add new primitives with the shadcn CLI conventions; do not fork existing ones for one-off styling.
- Merge classes with `cn()` from `@/lib/utils`. Icons: `lucide-react`. Animation: `framer-motion` (`initial/animate`, see `school-detail.tsx`). Toasts: `useToast()` from `@/hooks/use-toast` is the dominant pattern; `sonner` is used by `PlanRequiredListener`/notifications. Charts: `recharts` via `components/ui/chart.tsx`.
- Side sheets for detail editing (`Sheet`, e.g. `lead-timeline-sheet.tsx`, `learner-detail-sheet.tsx`); `Dialog` for short forms (inquiry/review); avoid new `window.confirm`/`prompt` - prefer `AlertDialog`.

**Styling (Tailwind 4, `src/index.css`)**
- Only semantic tokens: `bg-background|card|muted|primary|secondary|accent|destructive`, `text-foreground|muted-foreground`, `border`. Tokens are HSL vars in `:root` and dark overrides under `.dark`; never hardcode hex for brand blue (primary 221 83% 53%).
- Colour chips need a dark pair: `bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400` (see `lib/lead-status.ts`; reuse `leadStatusColor/Label`).
- Mobile first: layouts are responsive (sidebar becomes `Sheet` on small screens). Check at 390px (marketing screenshots use 390x844 phone).
- Brand name in UI is **DriveQ**. Keep identifiers/localStorage keys as `driveiq_*`.
- Money: `formatInr()` from `lib/plan.ts`; durations `formatDuration()` in `lib/response-time.ts`; dates via `date-fns`.

**i18n**
- `const t = useT();` from `@/i18n/use-locale`; keys like `home.heroTitle`, interpolation `t("search.found",{count})`. Add to `en.ts`, mirror `hi.ts`/`mr.ts`. New nav/chrome/primary-flow strings must have keys (rule in `docs/I18N.md`). Error text from API stays English.

**State**
- Server data -> TanStack Query. Global client state -> Zustand with `create`; persist only via try/catch-wrapped storage. Auth: `useAuthStore`; school id: `useSchoolId()`; plan: `useEntitlements()`.
- Local UI/filter state -> `useState`. Comparison selection -> `features/comparison/stores/comparison-store.ts`.

**Forms**
- Existing forms are controlled `useState`. RHF + Zod + `components/ui/form.tsx` are available and are the preferred choice for new multi-field forms. Show server field errors (422 `errors`). Phone is Indian format (+91). Password confirm fields mirror `reset-password.tsx`.

**Privacy / performance rules (DPDP, LCP)**
- Never load Google Maps or third-party scripts on first paint: use click-to-load as in `components/maps/school-map.tsx` or the singleton loader in `schools-map.tsx`.
- Ask before geolocation with a purpose notice (search.tsx `locationPrompt`).
- Cookie/role consent via `CookieConsent`, `RoleConsentGate`, `lib/consent.ts`; any new data-collecting flow should record a consent purpose (`ConsentPurpose` in `ops-api.ts`).
- Structured data via `<JsonLd>`; WhatsApp links use click-to-chat deeplinks.

**Testability**: every button/input/status text gets `data-testid="kind-name"` (existing: `input-login-email`, `button-show-map`, `text-session-expired`).

**Don't**: edit `api-client/generated`, add inline styles for theme colours, add a second toaster system, or leave English-only strings in nav/layouts.

## Security conventions
- No `dangerouslySetInnerHTML` unless the content is developer-owned or passed through a sanitizer/escape helper (`JsonLd` for structured data; it must escape `<`, `>`, `&`, U+2028/2029). Never `innerHTML`, `eval`, `document.write`.
- Every `target="_blank"` needs `rel="noopener noreferrer"`. WhatsApp links: `https://wa.me/<digits only>`; `tel:` and `mailto:` values normalised/encoded.
- Destructive or money actions use a confirm dialog (shadcn `AlertDialog`, validated reason input with max length), not `window.confirm`/`window.prompt`.
- Consent checkboxes are never pre-ticked and must POST to `/api/consents`; keep honeypot (`website`, hidden, `aria-hidden`) and `formStartedAt` on public forms.
- Load third-party scripts (Maps) only after an explicit user action; no analytics before "Accept all".
