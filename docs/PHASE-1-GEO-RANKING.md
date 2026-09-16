# Phase 1 — Geo Ranking v1

## Query parameters

| Param | Description |
|-------|-------------|
| `nearLat` / `nearLng` | Search origin (required for geo mode) |
| `radiusKm` | Radius (1–50). Default **5**. UI offers 2 / 5 / 10 |
| `sortBy` | `rank` (default) or `distance` |

## Distance

Haversine great-circle distance (km) with a **bounding-box pre-filter** before the formula for index-friendly filtering on `latitude` / `longitude`.

PostGIS is enabled when the `postgis/postgis` Docker image is used (`CREATE EXTENSION IF NOT EXISTS postgis`). Ranking still uses Haversine so SQLite tests keep working.

## Ranking score (default sort)

```
rankingScore =
    0.45 * distanceScore
  + 0.30 * ratingScore
  + 0.15 * reviewScore
  + 0.10 * verifiedBonus
```

| Component | Formula |
|-----------|---------|
| `distanceScore` | `1 - min(distanceKm, radiusKm) / radiusKm` |
| `ratingScore` | `rating / 5` |
| `reviewScore` | `min(review_count / 50, 1)` |
| `verifiedBonus` | `1` if verified, else `0` |

Weights live in `backend/config/geo.php` and are tunable without code changes.

## Response fields

- `distanceKm` — rounded to 2 decimals when geo mode is active
- `rankingScore` — present when geo mode is active
