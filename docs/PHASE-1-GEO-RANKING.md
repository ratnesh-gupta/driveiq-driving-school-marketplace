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

- `sortBy=rank`:
  1. **Top placement** first: schools on a plan listed in
     `geo.top_placement_plans` (default `premium`, `enterprise`).
  2. Then `rankingScore` descending.
  3. Then distance ascending.
  4. Then `id` (deterministic tie-break).
- `sortBy=distance`: distance, then plan boost, verified, rating, `id`.

Weights, review cap, and top-placement plans live in `backend/config/geo.php`
and are tunable without code changes.

## Response fields

- `distanceKm` — rounded to 2 decimals when geo mode is active
- `rankingScore` — present when geo mode is active
- `isSponsored` — true for paid sponsored tiers
