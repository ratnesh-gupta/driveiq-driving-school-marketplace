# Phase 1 — Geo Ranking v1

## Query parameters

| Param | Description |
|-------|-------------|
| `nearLat` / `nearLng` | Search origin (required for geo mode) |
| `radiusKm` | Radius (1–50). Default **5**. UI offers 2 / 5 / 10 |
| `sortBy` | `rank` (default) or `distance` |

## Distance (PostGIS)

- `schools.location` is a `geography(Point, 4326)` column with a **GIST index**.
- It is derived from `latitude` / `longitude` by a database trigger
  (`schools_sync_location`), so every write path keeps it in sync; the
  application never writes it directly.
- Radius filter: `ST_DWithin(location, origin, radiusKm * 1000)` (index-assisted).
- Distance: `ST_Distance(location, origin) / 1000` km (geodesic, WGS 84).

PostgreSQL with the PostGIS extension is required (local `postgis/postgis`
Docker image, CI service, and PHPUnit all use it).

## Ranking score (default sort)

```
rankingScore =
    0.40 * distanceScore
  + 0.25 * ratingScore
  + 0.12 * reviewScore
  + 0.08 * verifiedBonus
  + 0.15 * premiumBoost
```

| Component | Formula |
|-----------|---------|
| `distanceScore` | `1 - min(distanceKm, radiusKm) / radiusKm` |
| `ratingScore` | `rating / 5` |
| `reviewScore` | `min(review_count / 50, 1)` |
| `verifiedBonus` | `1` if verified, else `0` |
| `premiumBoost` | active plan's `ranking_boost / 100` (basic 0, featured 0.15, premium 0.25, enterprise 0.30) |

`rating` and `review_count` are derived from **approved** reviews only.

### Sort order

- `sortBy=rank` (and search without a location):
  1. **Sponsored slots** (M6/DIQ-805): at most `sponsored_slots_per_page`
     (admin setting, default 2) sponsor-eligible schools are pinned to the top,
     best `rankingScore` first. Sponsor-eligible = a plan in
     `geo.top_placement_plans` (default `premium`, `enterprise`) or a live
     `featured_placements` campaign (`search_top`, or `locality` for the
     locality being browsed). Trials are never sponsor-eligible.
  2. Everyone else, including sponsor-eligible schools beyond the cap, by
     `rankingScore` descending (the plan boost is part of the score).
  3. Then distance ascending (geo only).
  4. Then `id` (deterministic tie-break).
- Without a location the score uses the same weights minus the distance term.
- `sortBy=distance`: distance, then plan boost, verified, rating, `id`.

Weights, review cap, and top-placement plans live in `backend/config/geo.php`
and are tunable without code changes.

## Response fields

- `distanceKm` — rounded to 2 decimals when geo mode is active
- `rankingScore` — present when geo mode is active
- `isSponsored` — paid top placement (plan or campaign); always labelled "Sponsored" in the UI
- `isFeatured` — Featured plan (boost + highlighted card, labelled "Featured")
- `isPinned` — occupies one of the capped sponsored slots in this result set
- `listingTier` — `basic` | `featured` | `premium` | `enterprise`
