# Zone Shapefile import

The web wizard accepts one ZIP containing same-basename `.shp`, `.shx`, `.dbf`, and `.prj` files, with an optional `.cpg`. The current `zone` table has `id`, `name`, `geom geometry(Geometry, 4326)`, `organization_id`, `creator_id`, and timestamps. It has no zone code or description column, so the only source field mapping is the required zone name; the destination organization is selected from the caller's authorized organizations.

## Runtime requirements

- PHP extensions `zip`, `mbstring`, and `pgsql`/`pdo_pgsql` with PostGIS available in the application database.
- GDAL command line tools `ogrinfo` and `ogr2ogr`, with matching GDAL data and PROJ database files. A mismatched executable/data pairing can cause errors such as `Unable to open EPSG support file gcs.csv` or a missing `proj.db`.
- Configure matching binaries and data for the PHP web process in `.env`. Laravel passes `GDAL_DATA`, `PROJ_DATA`, and `PROJ_LIB` explicitly to Symfony Process, so Apache's inherited environment does not need to match the interactive shell. Example values must point to one complete GDAL/PROJ installation on the target host:

  ```dotenv
  GDAL_OGRINFO="/path/to/gdal/bin/ogrinfo"
  GDAL_OGR2OGR="/path/to/gdal/bin/ogr2ogr"
  GDAL_DATA="/path/to/matching/gdal-data"
  PROJ_DATA="/path/to/matching/proj-data"
  PROJ_LIB="/path/to/matching/proj-data"
  ```

- The selected GDAL 3 executable is checked for `epsg.wkt`; GDAL 2 requires `gcs.csv`. The configured PROJ directory must include `proj.db`. Missing or mismatched data produces the Persian configuration error in the wizard and a safe diagnostic signature in Laravel logs.
- Superadmin may import, Boss users need a valid configured boundary, and other users need the existing `zone.create` permission. This reuses existing authorization and does not add or grant a permission. The authenticated routes and policy are checked on every wizard action.

## Behavior and limits

Uploads stay under private `storage/app/private/zone-imports`, are owner-bound to a random session token, and expire after two hours. An hourly Laravel scheduler task removes expired artifacts. Schedule `php artisan schedule:run` from the host scheduler (or use Laravel's supported scheduler runner) so cleanup runs regularly.

ZIP members are checked before extraction for traversal, links, unexpected extensions, mismatched basenames, entry count, expanded size, and compression ratio. GDAL receives argument arrays through Symfony Process, not shell interpolation. Import requires a readable PRJ and transforms its declared CRS to EPSG:4326; absent or unparseable CRS is rejected. Polygon and MultiPolygon inputs are accepted. Files are capped at 25 MiB uploaded, 100 MiB expanded, and 2,000 features. Imports currently run synchronously; split larger datasets into smaller ZIPs.

Mapping reads the actual DBF field names and allows the zone name field, organization, and encoding (CPG hint or explicit supported encoding). Geometry validity, geometry type, WGS84 bounds, and normalized names are checked through PostGIS. Boss geometries must be fully covered by the Boss's valid boundary; a Boss can update only a zone they created and cannot update their own boundary. The table has no business code, so duplicates are identified by normalized zone name within the selected organization. The default is to skip duplicates. Explicit update changes only name/geometry and is refused for zones spatially linked to risers/cases or assigned users. New writes and audit events are transactional. Preview writes only private temporary files and performs read-only database queries.

## Verification

The GDAL unit fixture test creates an EPSG:32639 Shapefile, inspects its PRJ-derived CRS, converts it to EPSG:4326, and checks the resulting coordinates around the original Arak-area fixture. It can run without a database:

```powershell
php artisan test --filter=ZoneShapefileImportServiceTest
```

Feature tests require a separately provisioned PostgreSQL/PostGIS test database. The suite verifies the configured test environment and database cluster before opening its write transaction; if those checks fail, the tests stop or skip. Never point the feature suite at a development or production database. After configuring the target host's `.env`, clear or rebuild Laravel config cache and restart the PHP web process. Open **مدیریت زون‌ها → ورود از Shapefile**, upload a test ZIP, map the zone-name field, select an allowed organization and encoding, inspect the preview, then confirm. To test update behavior, use an unlinked duplicate in an isolated test dataset only.
