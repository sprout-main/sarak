# Backend Contract (Shared Geospatial Interface)

This document is the authoritative single source of truth for the Sarak Cartographic Platform.
The live database `bangued_atlas` (MariaDB 10.4 SRID 4326) is authoritative; no schema drift is allowed.

---

## 1. Environment Facts & Foundations

- **Framework & Runtime:** Laravel 11.x, PHP 8.2+, MariaDB 10.4.32+ with MySQL Spatial Extensions.
- **Primary Database:** `bangued_atlas` (MariaDB 10.4).
- **Test Database:** `bangued_atlas_test` (disposable testing instance).
- **Charset & Collation:** `utf8mb4`, `utf8mb4_unicode_ci`.
- **Application Timezone:** `Asia/Manila` (PHT, UTC+8).
- **Spatial Geometry Standard:** SRID 4326 (WGS 84 coordinate reference system).
- **All 18 Migration Registrations:**
  1. `0001_01_01_000000_create_users_table`
  2. `0001_01_01_000001_create_cache_table`
  3. `0001_01_01_000002_create_jobs_table`
  4. `2026_09_21_103738_create_personal_access_tokens_table`
  5. `2026_09_21_110000_create_categories_table`
  6. `2026_09_21_110100_create_boundary_areas_table`
  7. `2026_09_21_110200_create_places_table`
  8. `2026_09_21_110300_create_place_hours_table`
  9. `2026_09_21_110400_create_place_photos_table`
  10. `2026_09_21_110500_create_mod_queue_table`
  11. `2026_09_21_110600_create_audit_log_table`
  12. `2026_09_21_110700_create_import_runs_table`
  13. `2026_09_24_080000_add_spatial_compound_index_and_civic_moat_columns`
  14. `2026_09_24_093000_remove_moderator_role`
  15. `2026_09_25_010000_add_avatar_path_to_users_table`
  16. `2026_09_26_220000_add_censor_contributor_name_to_users_table`
  17. `2026_09_28_080000_create_place_reports_table`
  18. `2026_10_04_080000_add_sort_order_to_place_photos_table`

---

## 2. Enums & Coding Conventions

- **PSR-12 Compliance.**
- **Enums (`app/Enums`):**
  - `UserRole`: `user` (rank 1), `admin` (rank 2).
  - `Source`: `manual`, `overpass`, `nominatim`, `official_list`, `google_ref`, `community`.
  - `PlaceStatus`: `pending`, `approved`, `rejected`, `archived`.
  - `VerificationTier`: `verified`, `community`, `imported`.
  - `QueueAction`: `create`, `update`, `delete`, `photo`, `move`.
  - `QueueStatus`: `pending`, `approved`, `rejected`.
  - `BoundaryType`: `barangay`, `municipality`.
  - `PhotoStatus`: `pending`, `approved`, `rejected`.
  - `ImportStatus`: `running`, `done`, `failed`.
- **Root Admin Invariant:** Head Admin is permanently anchored to `admin@bangued.ph`. Deletion, role alteration, or demotion attempts are strictly blocked (`422 Unprocessable Entity`).
- **All times:** ISO-8601 strings or `HH:MM` 24h format based on `Asia/Manila`.

---

## 3. Database Schema Models & Column Invariants

### 3.1 `users`
`id, name, email, email_verified_at, password, role enum('user','admin') default 'user', karma_points int default 0, reputation_badge varchar null, avatar_path varchar null, censor_contributor_name bool default 0, remember_token, timestamps`
- Model: `App\Models\User`.
- Accessors: `avatar_url`, `isAdmin()`, `isHeadAdmin()`, `isModerator()`, `hasRank(int $rank)`.

### 3.2 `categories`
`id, slug, label, icon_key, color, sort_order, is_active, timestamps`
- Active 5 Seeded Categories: `coffee_shop` (#a16207), `fast_food` (#fb923c), `grocery` (#34d399), `hospital` (#fb7185), `pharmacy` (#2dd4bf). Sort order 1..5.

### 3.3 `boundary_areas`
`id, name, type enum('barangay','municipality'), geom polygon null (SRID 4326), boundary_source, psgc, imported_at, timestamps`

### 3.4 `places`
`id, category_id, name, slug, description text null, address varchar null, barangay_id null, geom point NOT NULL (SRID 4326), lat decimal(10,7), lng decimal(10,7), source enum default 'manual', source_ref varchar null, source_url varchar null, status enum default 'pending', submitted_by unsigned bigint null, approved_by unsigned bigint null, approved_at timestamp null, verified_tier enum default 'community', is_claimed bool default 0, claimed_by unsigned bigint null, phone varchar null, website varchar null, timestamps, deleted_at`
- Spatial index on `geom`. Compound indexes on `(category_id, status)` and `(barangay_id, status)`.

### 3.5 `place_hours`
`id, place_id, day tinyint (0=Sun, 6=Sat), opens time null, closes time null, is_closed bool default 0, note varchar null, timestamps`

### 3.6 `place_photos`
`id, place_id, path varchar, uploader_id null, is_cover bool default 0, sort_order int default 0, status enum default 'pending', timestamps`
- Ordering precedence: `is_cover DESC`, `sort_order ASC`, `id ASC`.

### 3.7 `mod_queue`
`id, place_id null, action enum('create','update','delete','photo','move'), payload json, requested_by null, status enum default 'pending', reviewed_by null, reviewed_at null, reviewer_note text null, timestamps`

### 3.8 `audit_log`
`id, model varchar, model_id unsigned bigint, actor_id unsigned bigint null, action varchar, diff json null, created_at` (no `updated_at`).

### 3.9 `place_reports`
`id, place_id, user_id unsigned bigint null, issue_type varchar, details text null, status enum('pending','resolved','dismissed') default 'pending', resolved_by unsigned bigint null, resolved_at timestamp null, timestamps`

### 3.10 `import_runs`
`id, source varchar, batch_summary json, status enum('running','done','failed') default 'running', started_at, finished_at`

---

## 4. Geospatial Query Syntax (MariaDB 10.4)

- **Point Storage:**
  `DB::raw("ST_GeomFromText('POINT({lng} {lat})', 4326)")`
- **Bounding Box Query:**
  `MBRContains(ST_GeomFromText('POLYGON((west south, east south, east north, west north, west south))', 4326), geom)`
- **Distance Sphere Query:**
  `ST_Distance_Sphere(geom, ST_GeomFromText('POINT(lng lat)', 4326), 6371008.8)`
- **Point-in-Polygon Boundary Containment:**
  `ST_Contains(boundary.geom, place.geom)`

---

## 5. API Surface Specification

### 5.1 Public Read Endpoints

| Endpoint | Method | Query Parameters / Payload | Description |
|---|---|---|---|
| `/api/categories` | GET | None | Active category list with approved place counts |
| `/api/barangays` | GET | None | Barangay list with approved place counts |
| `/api/boundaries` | GET | None | GeoJSON boundary polygons of all 25 barangays |
| `/api/places` | GET | `bbox`, `categories`, `q`, `barangay`, `tier`, `hours`, `lat`, `lng`, `radius`, `page`, `per_page` | Paginated approved places matching spatial viewport or filters |
| `/api/places/{place}` | GET | None | Complete place profile including operating schedule, approved photos, and barangay |
| `/api/geo/barangay` | GET | `lat`, `lng` | Reverse spatial lookup resolving coordinates to containing barangay |
| `/api/route` | GET | `start_lat`, `start_lng`, `dest_lat`, `dest_lng`, `mode` | Step-by-step navigational route geometry |
| `/api/search/suggest` | GET | `q` | Real-time lightweight search suggestions |
| `/api/search/semantic` | GET | `q`, `lat`, `lng` | Semantic keyword/intent parser matching establishments |
| `/api/contributors/leaderboard` | GET | None | Top crowdsourcing contributors ranked by verified karma |

### 5.2 Authenticated Resident Endpoints (`auth`)

| Endpoint | Method | Payload | Description |
|---|---|---|---|
| `/api/places` | POST | `name, category_id, address, lat, lng, phone, website, hours[]` | Submit new place proposal (stages to `mod_queue`) |
| `/api/places/{place}/reports`| POST | `issue_type, details` | File inaccuracy/closure incident report |
| `/api/places/{place}/photos` | POST | Multipart `photo` | Upload photo for place (stages to `mod_queue`) |
| `/api/places/{place}/photos/{photo}` | DELETE | None | Delete self-uploaded photo (or staff admin override) |
| `/api/places/{place}/photos/{photo}/cover` | PATCH | None | Designate photo as primary cover |
| `/api/places/{place}/claim` | POST | `verification_notes` | Request ownership verification of establishment |

### 5.3 Administrative Console Endpoints (`auth`, `role:admin`)

| Endpoint | Method | Payload / Query | Description |
|---|---|---|---|
| `/api/export.geojson` | GET | `categories`, `bbox` | Stream verified dataset as GIS GeoJSON |
| `/api/export.csv` | GET | `categories` | Stream verified dataset as tabular CSV |
| `/api/admin/queue` | GET | `status=pending` | Staged moderation proposals with before/after diffs |
| `/api/admin/queue/{id}/approve` | POST | None | Approve queued action, applying mutations to live tables |
| `/api/admin/queue/{id}/reject` | POST | `note` (required) | Reject queued submission with explanation note |
| `/api/admin/queue/{id}/merge` | POST | `into_place_id` | Merge duplicate entity into target place |
| `/api/admin/places` | GET | `q, category_id, status, page` | Master listing browser across all statuses |
| `/api/admin/places` | POST | Place fields | Direct-publish place bypassing moderation queue |
| `/api/admin/places/{id}` | PATCH | Place metadata, hours | Direct edit metadata with audit trail |
| `/api/admin/places/{id}/location` | PATCH | `lat, lng, barangay_id?` | Relocate pin with automatic barangay re-snapping |
| `/api/admin/places/{id}` | DELETE | None | Archive / soft-delete place listing |
| `/api/admin/places/mass-relocate` | POST | `delta_lat, delta_lng, place_ids[]` | Batch coordinate drift correction vector |
| `/api/admin/places/{id}/photos` | GET / POST | Multi-photo inspection and direct photo ingestion |
| `/api/admin/places/{id}/photos/reorder` | PATCH | `photos: [id, ...]` | Atomic update to `sort_order` across photos |
| `/api/admin/places/{id}/photos/{photo}` | DELETE | None | Evict photographic asset and purge disk storage |
| `/api/admin/places/{id}/photos/{photo}/cover` | PATCH | None | Designate place cover photo |
| `/api/admin/users` | GET | `q, role, page` | User account browser |
| `/api/admin/users/{user}/role` | PATCH | `role` | Promote/demote staff role (**Head Admin only**) |
| `/api/admin/users/{user}` | DELETE | None | Expel malicious user account |
| `/api/admin/reports` | GET | `status` | Master incident report queue |
| `/api/admin/reports/{report}/status` | PATCH | `status` | Transition report (`resolved`, `dismissed`) |
| `/api/admin/audit-logs` | GET | `page` | Forensic append-only audit trail browser |
| `/api/admin/analytics/coverage`| GET | None | Category $\times$ Barangay spatial coverage matrix |
| `/api/admin/analytics/pending` | GET | None | Total pending queue actions breakdown |
| `/api/admin/quality/barangay` | GET | None | Scan for spatial point-in-polygon containment breaches |
| `/api/admin/quality/barangay/fix` | POST | None | Batch repair coordinates into containing barangays |
| `/api/admin/import` | POST | `source, payload` | Trigger Overpass / Nominatim / Official List intake |
| `/api/admin/imports` | GET | None | Historical ingestion daemon execution runs |
| `/api/admin/boundaries` | GET | None | Administrative boundary listing with place density |

---

## 6. Web Routes & Static Asset Streaming

- `GET /` $\rightarrow$ Interactive Leaflet Map Shell (`resources/views/map.blade.php`).
- `GET /dashboard` $\rightarrow$ Contributor Workspace (redirects Admins to `/admin`).
- `GET /admin` $\rightarrow$ Administrative Command Hub.
- `GET /tiles/{style}/{z}/{x}/{y}.png` $\rightarrow$ Tile Proxy cache with `@2x` retina scaling.
- `GET /storage/{path}` $\rightarrow$ Controlled asset streaming controller (`StorageFileController`).
- `GET /profile` $\rightarrow$ Account management, avatar uploads, and contributor privacy toggles.