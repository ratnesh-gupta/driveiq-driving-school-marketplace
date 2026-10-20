# DriveQ marketing kit (M10)

Everything here is generated from the real app with demo data, so it can be
refreshed whenever the screens change. Generated files (videos, screenshots)
are not committed; share them from wherever you keep marketing assets.

## 1. Load the demo

```bash
make demo          # or: cd backend && php artisan driveiq:demo --fresh
```

This wipes the **local** database and loads fictional Pune schools, trainers,
leads, learners, sessions and an admin outreach pipeline. It refuses to run in
production. Logins (password `password123`):

| Who | Email |
|---|---|
| School owner (Skyline) | `info@skylinedrive.in` |
| Admin | `admin@driveiq.in` |
| Trainer | `trainer.skyline@driveiq.in` |
| Learner | `learner.asha@driveiq.in` |

Start the API on `:8000` and the website on `:5173` (`make up`, or
`php artisan serve` + `pnpm dev`).

## 2. Demo videos

`video/record.cjs` drives the app in a real browser, adds captions from
`video/captions.json` and records:

| Format | Size | Length | Story |
|---|---|---|---|
| `walkthrough` | 1920×1080 | ~2–2.5 min | Learner searches near me → filters → school page → enquiry; school gets the lead, adds a note; dashboard, schedule, learners; independent trainers; claiming a listing |
| `short` | 1080×1920 (vertical) | ~35–45 s | Near me → school → enquiry → school replies → "list your school free" |

```bash
# Reset the demo before each recording so names and counts match the captions.
make demo
FFMPEG=/path/to/ffmpeg CLAIM_URL="<claim link>" node marketing/video/record.cjs --lang en --format walkthrough
node marketing/video/record.cjs --lang hi --format short
```

- Languages: `en`, `hi`, `mr`. Edit `video/captions.json` to change the words;
  have a native speaker check Hindi and Marathi before publishing.
- `CLAIM_URL` (optional) shows the claim page at the end of the walkthrough.
  Make one with:
  `php artisan tinker --execute='$p=App\Models\Prospect::where("name","Hadapsar Highway Driving")->first(); echo app(App\Services\ListingClaimService::class)->issue($p->school,$p)["url"];'`
- Without ffmpeg you get a `.webm`; with it, an MP4 (H.264) that plays
  everywhere. `pip install imageio-ffmpeg` gives a bundled ffmpeg.
- The videos have no sound: add music or a voice-over in any editor.

## 3. Screenshots

```bash
node marketing/screenshots.cjs      # → marketing/screenshots/*.png
```

Desktop (1440×900 @2x) and phone (390×844 @3x) shots of the public pages,
the school dashboard and the admin acquisition screens.

## 4. Outreach email copy

Ready-made 3-email sequences for schools and independent trainers in English,
Hindi and Marathi live in `backend/config/outreach_presets.php`; the words the
email layout adds (button, card, unsubscribe line) are in
`backend/config/outreach_copy.php`. Admins pick them in Admin → Outreach →
New campaign → "Start from our ready-made emails".

## 5. Decks

The school pitch deck and the investor deck are published as DriveQ slide
artifacts (exportable to PowerPoint and PDF). Investor numbers marked
⟨fill in⟩ are placeholders.
