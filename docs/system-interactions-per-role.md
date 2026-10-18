# Sarak Atlas: Key System Interactions Per Role Matrix

> Generated under the **WomanA 1.0** architectural amplification protocol.  
> Grounded in the **Sarak** geospatial engine (Laravel 11, MariaDB 10.4 SRID 4326 spatial engine, Leaflet.js wayfinding).

---

## 1. Architectural Role Hierarchy & Topology

The Sarak Cartographic Platform operates with four strictly separated actor tiers:

```
                                  ┌───────────────────────────────┐
                                  │      SARAK SPATIAL CORE       │
                                  │   (MariaDB 10.4 SRID 4326)    │
                                  └───────────────┬───────────────┘
                                                  │
                 ┌────────────────────────────────┼────────────────────────────────┐
                 │                                │                                │
                 ▼                                ▼                                ▼
    ┌─────────────────────────┐      ┌─────────────────────────┐      ┌─────────────────────────┐
    │       HEAD ADMIN        │      │          USER           │      │          GUEST          │
    │ Root Sovereign & Anchor │      │ Registered Contributor  │      │ Unauthenticated Visitor │
    │   (admin@bangued.ph)    │      │    & Verified Owner     │      │   & Wayfinding Citizen  │
    └────────────┬────────────┘      └─────────────────────────┘      └─────────────────────────┘
                 │
                 ▼
    ┌─────────────────────────┐
    │          ADMIN          │
    │  Deputy Cartographer    │
    │  & Moderation Reviewer  │
    └─────────────────────────┘
```

---

## 2. Role I: Head Admin (Root Cartographic Sovereign)
**Identity & Scope:** Root authority anchored to `admin@bangued.ph`. Possesses irrevocable administrative rank (`rank = 2`), distinct amber badge insignia (`text-amber-300 bg-amber-500/20`), and permanent immunity from demotion or account deletion.

### 2.1 Identity Sovereignty & Administrative Governance (`/admin/users`)
* **Sole Authority for Role Promotion & Demotion:**
  * **Promote User to Admin:** Head Admin holds sole authority to promote registered residents (`user`) to staff administrators (`admin`) via [`PATCH /api/admin/users/{user}/role`](../app/Http/Controllers/Api/Admin/UserController.php).
  * **Demote Admin to User:** Head Admin holds sole authority to demote staff administrators (`admin`) back to regular users. Deputy admins attempting this action are rejected with `403 Forbidden`.
* **Root Account Immunity Invariants:**
  * Protected by [`User::isHeadAdmin()`](../app/Models/User.php). The system strictly rejects any role alteration or account termination attempt targeting the Head Admin account (`422 Unprocessable Entity`), preventing accidental or hostile lockouts.
* **Account Expulsion & Asset Purging:**
  * Permanently delete malicious, abusive, or spam accounts via [`DELETE /api/admin/users/{user}`](../app/Http/Controllers/Api/Admin/UserController.php), cascading avatar file removal from `storage/app/public/avatars/` and committing an audit trail entry.

### 2.2 Macro-Spatial Relocation & Territorial Integrity
* **Batch Cluster Relocation (Mass Drift Correction):**
  * Execute macro-spatial coordinate shifts across multiple establishments simultaneously via [`POST /api/admin/places/mass-relocate`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php).
  * Applies delta latitude and longitude vectors to repair municipal cadastral survey revisions, GPS satellite drift, or base-satellite tile layer recalibrations in a single atomic transaction.
* **Municipal Boundary & Anomaly Repair (`/admin/boundaries`):**
  * Oversee the 25 official Bangued barangay administrative boundary polygons stored in SRID 4326.
  * Execute automated spatial integrity scans (`GET /api/admin/quality/barangay` via [`DataQualityController`](../app/Http/Controllers/Api/Admin/DataQualityController.php)) to pinpoint point anomalies where coordinates mathematically breach their assigned polygon boundaries.
  * Trigger automated batch repair (`POST /api/admin/quality/barangay/fix`): Runs spatial intersection `ST_Contains(boundary.geom, place.geom)` across all unaligned places to re-anchor them to their true barangay.

### 2.3 Automated Ingestion Daemons & Spatial Pipelines (`/admin/import`)
* **External Ingestion Pipeline Execution:**
  * Trigger automated harvesting jobs from global and civic data sources:
    * `overpass`: Harvest POIs dynamically from OpenStreetMap Overpass API.
    * `nominatim`: Batch geocode unmapped establishments line by line.
    * `official_list`: Ingest official municipality CSV or GeoJSON registries.
  * Ingested candidate POIs bypass live tables and are safely staged in `mod_queue` tagged with immutable provenance tags (`source`, `source_ref`, `fetched_at`).
* **Run Monitoring & Failure Analysis:**
  * Inspect historical pipeline execution runs (`GET /api/admin/imports` via [`ImportController`](../app/Http/Controllers/Api/Admin/ImportController.php)) tracking processed batches, deduplication rates, and network failure diagnostics.

### 2.4 Master Cartographic Audit Supervision (`/admin/audit-logs`)
* **System-Wide Forensic Audit Ledger:**
  * Inspect the append-only [`audit_log`](../app/Services/AuditService.php) containing every creation, update, coordinate move, photo modification, merge, and account alteration across the entire atlas.
  * Inspect deep JSON before/after state diffs to verify staff administrative integrity and trace historical data provenance.

### 2.5 Administrative Open Data Exports
* **Privileged Cartographic & Tabular Exports:**
  * Full administrative access to stream the complete verified atlas in GeoJSON format (`GET /api/export.geojson?categories=&bbox=`) for municipal GIS mapping.
  * Stream spreadsheet-ready CSV exports (`GET /api/export.csv`) for tabular planning and verification.

---

## 3. Role II: Admin (Deputy Cartographer & Moderation Reviewer)
**Identity & Scope:** Staff administrator possessing elevated administrative clearance (`rank = 2`), distinct emerald badge insignia (`text-emerald-400 bg-emerald-400/15`), and operational authority over catalog data, photos, and community moderation.

### 3.1 Photographic Asset Mastery & Lifecycle Governance
* **Multi-Status Photo Inspection Console (`/admin/places`):**
  * Inspect every photographic asset attached to any place in [`admin.places`](../resources/views/admin/places.blade.php) across all lifecycle states (`approved`, `pending`, `rejected`).
  * Live status pill indicators (`emerald-500` for approved, `amber-500` for pending, `rose-500` for rejected).
* **Direct Administrative Photo Ingestion (Queue Bypass):**
  * Upload single or batch photos directly via [`POST /api/admin/places/{place}/photos`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php).
  * Direct filesystem write to `storage/app/public/photos/{placeId}/`, auto-assigning `status = 'approved'` and logging to the audit ledger without requiring queue moderation.
* **Manual Photo Ordering per Place:**
  * Adjust display order of place imagery using tactile shifters (`#order` badge, `◀`, `▶` controls) in [`admin.places`](../resources/views/admin/places.blade.php) drawer via `movePhoto(index, direction)`.
  * Persisted atomically via [`PATCH /api/admin/places/{place}/photos/reorder`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php) updating the `sort_order` database column on `place_photos`.
  * Guaranteed display hierarchy: Primary cover photo always anchors first (`orderByDesc('is_cover')`), followed by explicit sequence (`orderBy('sort_order')`), and primary key (`orderBy('id')`).
* **Cover Photo Designation:**
  * Star/designate any image as the listing's primary cover photo via [`PATCH /api/admin/places/{place}/photos/{photo}/cover`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php).
  * Automatically demotes previously assigned cover photos in an atomic transaction; immediately updates public map cards, search suggestions, and list views.
* **Dual-Surface Photographic Asset Eviction (Console + Public Map Canvas):**
  * **Admin Console Drawer:** Delete photos directly within the place edit drawer in `/admin/places` via trash action button.
  * **Public Map Canvas (Hero Card & Fullscreen Lightbox):** When browsing the public interactive map with an active staff session (`window.SarakIsAdmin === true`), administrators can immediately delete inappropriate, outdated, or low-quality photos directly from the place detail hero card or inside the high-resolution lightbox modal header via [`api.adminDeletePhoto()`](../resources/js/map/api.js).
  * Drops the database record, physically purges the image file from `storage/app/public/photos/{placeId}/`, purges empty parent directories via [`PhotoService`](../app/Services/PhotoService.php), and records the deletion actor in `audit_log`.

### 3.2 Place Catalog Maintenance & Spatial Editing
* **Catalog Command Browser (`/admin/places`):**
  * Search, filter, and page through all places across categories, administrative barangays, data sources, and lifecycle statuses (`pending`, `approved`, `rejected`, `archived`).
* **Direct Place Publishing (Queue Bypass):**
  * Create and immediately publish places via [`POST /api/admin/places`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php). Bypasses moderation, assigns `approved_by` to the current admin, records timestamps, and creates the spatial point geometry.
* **Single-Place Pin Precision Relocation:**
  * Drag-and-drop pin repositioning or manual latitude/longitude coordinate entry via [`PATCH /api/admin/places/{id}/location`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php).
  * Atomic update to both scalar `lat`/`lng` decimal fields and MariaDB spatial geometry `geom = ST_GeomFromText('POINT(lng lat)', 4326)`.
  * Auto-resolves enclosing barangay boundary via [`GeoUtilityController::resolveBarangay`](../app/Http/Controllers/Api/GeoUtilityController.php) and logs coordinate movement distances in the audit trail.
* **Comprehensive Metadata & Hours Editing:**
  * Update name, category, description, physical address, phone, website, and the complete 7-day operating hours matrix (`opens`, `closes`, `is_closed`, seasonal notes) via [`PATCH /api/admin/places/{id}`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php).
* **Place Soft & Hard Purging:**
  * Archive or permanently delete listings via [`DELETE /api/admin/places/{id}`](../app/Http/Controllers/Api/Admin/PlaceAdminController.php) with cascading cleanup of operating hours, photos, and location geometries.

### 3.3 The Moderation Queue Command Center (`/admin/queue`)
* **Staged Community Action Triage:**
  * Review community proposals in [`admin.queue`](../resources/views/admin/queue.blade.php) across 5 core action vectors:
    1. `create`: New place listings submitted by residents.
    2. `update`: Metadata or operating hour modifications.
    3. `move`: Coordinate repositioning proposals.
    4. `photo`: Community-submitted photo uploads.
    5. `delete`: Place closure and removal requests.
* **Side-by-Side Before/After Diff Engine:**
  * Inspect diffs generated by [`ModerationService::diffBeforeAfter`](../app/Services/ModerationService.php), highlighting field edits, distance moved in meters, and image comparisons before commit.
* **One-Click Approval Execution & Karma Attribution:**
  * Approve queued item (`POST /api/admin/queue/{id}/approve`): Promotes staging payload into canonical `places` and `place_photos` tables, attributes `approved_by` to the current admin, records timestamp, and awards +20 reputation karma to the original place submitter.
* **Contextual Rejection with Audit Trail:**
  * Reject submission (`POST /api/admin/queue/{id}/reject`) with mandatory reviewer explanation note stored for administrative history.
* **Duplicate Detection & Entity Merge:**
  * Identify duplicated places and trigger canonical merge (`POST /api/admin/queue/{id}/merge`). Migrates all operating hours, approved photos, and relation links from the duplicate to the target place ID, soft-deleting the redundant entity and recording an explicit `merge` audit event.

### 3.4 Feedback & Incident Reports Administration (`/admin/reports`)
* **Community Report Triage:**
  * Filter, inspect, and resolve community-filed reports regarding inaccurate business hours, incorrect pin placements, or permanent closures ([`admin.reports`](../resources/views/admin/reports.blade.php)).
* **Resolution State Management:**
  * Transition report tickets across `pending`, `resolved`, and `dismissed` states with deep-links to the affected place profile for rapid corrective action.

### 3.5 Role Alteration Barrier & Privileged Exports
* **Role Promotion & Demotion Blocked (`❌ Blocked`):**
  * Deputy administrators cannot promote or demote user accounts. Attempted role updates return `403 Forbidden` (`Only the head administrator can promote or demote user accounts.`).
* **Non-Head User Account Deletion:**
  * Delete abusive non-head user accounts via [`DELETE /api/admin/users/{user}`](../app/Http/Controllers/Api/Admin/UserController.php).
* **Administrative Open Data Exports:**
  * Export verified atlas data in GeoJSON (`GET /api/export.geojson`) and CSV (`GET /api/export.csv`) formats.

---

## 4. Role III: User (Registered Resident / Contributor / Business Owner)
**Identity & Scope:** Authenticated citizen possessing baseline user clearance (`rank = 1`), standard contributor badge (`text-zinc-300 bg-zinc-800`), profile sovereignty, and crowdsourcing privileges.

### 4.1 Spatial Exploration & Wayfinding
* **Interactive Map Navigation:**
  * Full interactive wayfinding using Leaflet dark/light basemap tiles.
  * Bounding box filtering (`MBRContains`) and radial proximity queries (`ST_Distance_Sphere`) from device GPS coordinates.
  * Real-time "Open Now" status calculations based on `Asia/Manila` timezone via [`HoursService`](../app/Services/HoursService.php).
* **Turn-by-Turn Wayfinding & Routing:**
  * Compute pedestrian and transit routing overlays (`GET /api/route` via [`RouteController`](../app/Http/Controllers/Api/RouteController.php)) with step-by-step navigation instructions.
* **Predictive & Semantic Search:**
  * Real-time search suggestions (`/api/search/suggest`) and semantic search parsing (`/api/search/semantic`).

### 4.2 Crowdsourced Submissions & Photographic Contributions
* **Place Submissions (Staged to Queue):**
  * Propose new places on the interactive map (`POST /api/places`). Pin exact latitude/longitude, specify 7-day operating hours, contact phone, website, and physical address.
  * Submissions enter `mod_queue` with status `pending`. Public read APIs remain protected from unvetted mutations.
* **Community Photo Uploading:**
  * Upload JPG, PNG, or WebP images up to 5MB for any approved establishment (`POST /api/places/{place}/photos`).
  * Uploads are held in `storage/app/public/photos/{placeId}/` and routed to the moderation queue until an administrator approves them.
* **Self-Authored Photo Lifecycle Management:**
  * Delete own photos (`DELETE /api/places/{place}/photos/{photo}`).
  * Request cover photo status for self-uploaded imagery (`PATCH /api/places/{place}/photos/{photo}/cover`).

### 4.3 Ownership, Feedback & Reputation
* **Listing Verification & Ownership Claiming:**
  * Verified merchants and resident owners can claim approved listings (`POST /api/places/{place}/claim` via [`ClaimController`](../app/Http/Controllers/Api/ClaimController.php)). Sets `is_claimed = true` and assigns `claimed_by`.
* **Incident & Inaccuracy Reporting:**
  * Submit issue reports against places (`POST /api/places/{place}/reports`) detailing incorrect pin locations, out-of-date hours, or permanent closures.
* **Strict Karma Invariants & Reputation Safeguard:**
  * **Exclusivity of Karma Accrual:** Contributor karma is earned **exclusively** upon submitting a new establishment listing and having it approved by an administrator (+20 karma points) via [`KarmaService`](../app/Services/KarmaService.php).
  * Proposing updates, uploading photos, submitting reports, or claiming business listings yield **0 karma points**, maintaining anti-gaming integrity.
  * Compete on the public Contributor Leaderboard (`GET /api/contributors/leaderboard`).
  * Contributor Privacy Safeguard: Toggle `censor_contributor_name` in profile settings to anonymize public attribution while preserving earned karma points.
  * Custom avatar upload/removal (`/profile/avatar`).
  * Personal dashboard (`/dashboard`): Track own submitted places, pending queue status, approved listings, and claimed establishments.

---

## 5. Role IV: Guest (Public Explorer / Unauthenticated Visitor)
**Identity & Scope:** Unauthenticated public citizen or visitor. Read-only access to published public map assets, search, and boundary visualizations.

### 5.1 Frictionless Map Exploration
* **Full-Canvas Map Wayfinding:**
  * Navigate the interactive Leaflet map rendered with dark/light wayfinding basemap tiles proxied through [`TileProxyController`](../app/Http/Controllers/TileProxyController.php) with `@2x` retina support.
  * Cluster markers, dynamic viewport querying, and device geolocation proximity searches.
* **Place Profile & Photographic Lightbox:**
  * View verified profiles: 7-day operational schedule, real-time "Open Now" / "Closed" status pill, phone numbers, external website links, and barangay assignments.
  * Browse photographic galleries in a full-screen modal lightbox with keyboard shortcuts (`Escape`, arrow keys) and swipe navigation.
* **Pedestrian & Vehicular Routing:**
  * Calculate step-by-step navigational routes from current device location to any place.

### 5.2 Civic Cartography Consumption
* **Administrative Boundary Visualizations:**
  * Query barangay boundary GeoJSON polygons (`GET /api/boundaries`) for overlaying local government territory limits.
* **Public Contributor Recognition:**
  * View top community cartographers on the public leaderboard without needing an account.
* **Frictionless Auth Gates:**
  * Attempting to submit places, upload imagery, claim establishments, or file reports seamlessly prompts an authentication modal without disrupting map viewport coordinates. Raw data exports (GeoJSON/CSV) are protected and restricted to staff administrators.

---

## 6. Comprehensive 4-Role Interaction Matrix

| Interaction Domain | Guest | User | Admin | Head Admin |
| :--- | :---: | :---: | :---: | :---: |
| **Interactive Map & Wayfinding** | Full Read | Full Read | Full Read | Full Read |
| **Turn-by-Turn Routing** | Full Read | Full Read | Full Read | Full Read |
| **View Place Photos & Lightbox** | Approved Only | Approved Only | All Statuses | All Statuses |
| **Upload Place Photos** | ❌ (Auth Prompt) | ✅ Staged to `mod_queue` | ✅ **Direct Publish** | ✅ **Direct Publish** |
| **Set Cover Photo** | ❌ | Own Photos Only | **Any Photo** | **Any Photo** |
| **Delete Place Photos** | ❌ | Own Photos Only | **Any Photo (Purge)** | **Any Photo (Purge)** |
| **Submit New Place** | ❌ (Auth Prompt) | Staged to `mod_queue` | **Direct Publish** | **Direct Publish** |
| **Edit Place Metadata & Hours** | ❌ | Staged to `mod_queue` | **Direct Mutation** | **Direct Mutation** |
| **Relocate Place Pin (Single)** | ❌ | Staged to `mod_queue` | **Drag-and-Drop** | **Drag-and-Drop** |
| **Batch Mass Relocation** | ❌ | ❌ | **Execute Batch Shift** | **Execute Batch Shift** |
| **Delete / Archive Place** | ❌ | Propose Deletion | **Permanent / Archive**| **Permanent / Archive**|
| **Moderate Queue (Approve/Reject)** | ❌ | ❌ | **Full Authority** | **Full Authority** |
| **Duplicate Place Merge** | ❌ | ❌ | **Full Authority** | **Full Authority** |
| **Claim Business Ownership** | ❌ | ✅ Verified Claim (0 Karma) | Admin Reassign | Admin Reassign |
| **Submit Incident Report** | ❌ | ✅ | Review & Resolve | Review & Resolve |
| **Export GeoJSON / CSV** | ❌ | ❌ | ✅ | ✅ |
| **Promote User to Admin** | ❌ | ❌ | ❌ (Blocked)| ✅ (Full Authority) |
| **Demote Admin to User** | ❌ | ❌ | ❌ (Blocked) | ✅ **Sole Authority** |
| **Delete User Accounts** | ❌ | ❌ | Non-Head Accounts | All Non-Head Accounts |
| **Account Demotion Immunity** | N/A | N/A | ❌ | 🛡️ **Permanent Invariant** |
| **Spatial Anomaly Batch Repair**| ❌ | ❌ | Run Auto-Repair | Run Auto-Repair |
| **Ingestion Daemons (OSM/Nominatim)**| ❌ | ❌ | Trigger Harvest | Trigger Harvest |
| **Audit Ledger Inspection** | ❌ | ❌ | Full Access | Full Access & Oversight |
