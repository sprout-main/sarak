# Sarak

A live map of every fast food chain, hospital, pharmacy, grocery, and coffee shop in Bangued, Abra. Residents submit places, administrators verify them, and every change is audited and exportable as open data.

## Stack

- Laravel 11 monolith (PHP 8, Breeze auth with roles: user, admin)
- MariaDB 10.4 with MySQL Spatial extensions (the geometry engine behind the map queries)
- Leaflet.js public map shell with a dark wayfinding base map
- OSM Overpass, Nominatim, and official list imports for data intake
- Rowlets: no extra services, a single Laravel app

## Quickstart

```bash
php artisan key:generate
copy .env.example .env
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan storage:link
php artisan serve
```

The app ships with two seeded logins, all with password `password`:

- admin@bangued.ph (admin)
- resident@bangued.ph (user)

## How submissions become map pins

1. A logged-in resident submits a new place via `POST /api/places`.
2. The submission lands in the moderation queue (`mod_queue`) as a pending row. It never touches the `places` table directly. Submissions by administrators via `/api/admin/places` or `/api/places` bypass moderation and publish directly with audit logging.
3. An administrator reviews queued rows, sees a before/after diff, and approves or rejects with a reason.
4. On approval the place is inserted as approved, timestamped, and added to the audit ledger. Moves, edits, photo uploads, and merges follow the same queue.

Nothing unapproved ever mutates the public read output, a guarantee enforced by the test suite.

## Imports

```bash
php artisan sarak:import overpass            # pull POIs from the Overpass API
php artisan sarak:import official_list <path|url>   # CSV or GeoJSON list
php artisan sarak:import nominatim <terms>   # geocode terms line by line
```

Imported candidates are deduped against existing approved places and parked in the moderation queue tagged with `source`, `source_ref`, and `fetched_at`. Imports never write to `places` directly.

## API summary

| Endpoint | Method | Access | Purpose |
|---|---|---|---|
| `/api/categories` | GET | public | Category list with approved place counts |
| `/api/barangays` | GET | public | Barangay list with approved place counts |
| `/api/places` | GET | public | Approved places with bbox, category, search, radius, and hours filters |
| `/api/places/{id}` | GET | public | Full profile: hours, approved photos, source, audit trail |
| `/api/places` | POST | user | Submit a place with hours, goes to the moderation queue |
| `/api/places/{id}/photos` | POST | user | Submit a photo, goes to the moderation queue |
| `/api/export.geojson` | GET | public | Full approved dataset as GeoJSON |
| `/api/export.csv` | GET | public | Full approved dataset as CSV |
| `/api/admin/queue` | GET | admin | Pending moderation rows with diffs |
| `/api/admin/queue/{id}/approve` | POST | admin | Approve a queued action |
| `/api/admin/queue/{id}/reject` | POST | admin | Reject with a note |
| `/api/admin/queue/{id}/merge` | POST | admin | Merge a duplicate into another place |
| `/api/admin/places` | GET | admin | Admin place browser across all statuses |
| `/api/admin/places/{id}` | PATCH | admin | Edit fields and hours with audit trail |
| `/api/admin/places/{id}/location` | PATCH | admin | Move a pin; the old and new coordinates are audited |
| `/api/admin/import` | POST | admin | Start an import job |
| `/api/admin/imports` | GET | admin | Import run history |
| `/api/admin/boundaries` | GET | admin | Barangay boundary list with place counts |
| `/api/admin/analytics/coverage` | GET | admin | Category x barangay coverage matrix |
| `/api/admin/analytics/pending` | GET | admin | Pending queue totals by action |

## Tests

```bash
php artisan test
```

The suite covers the public read API, the moderation invariant (nothing unapproved mutates public output), a 5000 place spatial performance check, exports, admin read endpoints, and the import pipeline. Tests run against the disposable `bangued_atlas_test` database only.

## Why MariaDB 10.4

Places are stored as `POINT` geometries (SRID 4326) and barangay boundaries as `POLYGON` geometries, queried with `MBRContains` for bounding boxes and `ST_Distance_Sphere` for radius searches. MariaDB 10.4 gives the atlas real spatial indexing and functions, which is what keeps map queries interactive as the town fills in.