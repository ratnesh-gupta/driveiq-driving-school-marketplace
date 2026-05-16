# Module Migration Tracker

Status values: `planned` | `in_progress` | `migrated` | `verified` | `deprecated`

| Module | Frontend Adapter | Laravel API | Legacy Express Usage | Status | Notes |
|---|---|---|---|---|---|
| health | no code change needed (uses `/api`) | implemented (`/api/healthz`) | transitional | verified | Laravel route active + tests passing |
| localities | no code change needed (uses `/api`) | implemented | transitional | verified | list/create/get-by-id/get-by-slug implemented |
| schools | no code change needed (uses `/api`) | implemented | transitional | verified | list/featured/get/create/update/delete implemented |
| packages | no code change needed (uses `/api`) | implemented | transitional | verified | list/create/update/delete implemented |
| reviews | no code change needed (uses `/api`) | implemented | transitional | verified | list/create/update/delete implemented |
| inquiries | no code change needed (uses `/api`) | implemented | transitional | verified | list/create/update implemented |
| stats | no code change needed (uses `/api`) | implemented | transitional | verified | overview + school stats implemented |

## Branching policy
- One branch per module: `feature/module-<name>-<scope>`
- Keep branch synced with `main` daily while active.
- Merge only after module tests + frontend flow verification.
