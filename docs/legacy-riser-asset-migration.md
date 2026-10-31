# Legacy riser to asset migration

This compatibility milestone copies legacy `riser` records into the platform Asset Registry. It does not modify `riser`, and existing controllers, APIs, reports, operations, workflows, and routes continue to use the legacy model.

## Mapping

- The command resolves or creates the global/default `AssetType` whose key is `riser`.
- New assets remain globally scoped (`organization_id` is null) because legacy risers do not currently carry organization ownership.
- `legacy_source` is `riser`; `legacy_id` is the original riser primary key.
- Code preference is `r_giscode`, `giscode`, then `code`. Status preference is `status`, then `state`, with `active` as the fallback.
- All non-geometry source fields except the primary key and timestamps are retained in `properties`.
- Geometry preference is `geom`, then `geom_3857`. The selected geometry is forced to 2D and transformed to SRID 4326. A geometry with unknown SRID uses the column's documented fallback (4326 for `geom`, 3857 for `geom_3857`).

## Procedure

Preview and validate first:

```bash
php artisan platform:migrate-risers --dry-run
```

Apply after reviewing the created, updated, skipped, and failed counts:

```bash
php artisan platform:migrate-risers
```

The command is idempotent. It matches on the global riser asset type plus `legacy_source` and `legacy_id`, updates a match only when mapped data differs, and otherwise reports it as skipped. A per-record transaction prevents a failed row from partially writing its asset.

## Rollback strategy

Rollback is deliberately manual and non-destructive by default. Take a database backup, then verify that no new platform records reference the copied assets. In one transaction, remove only assets that have `organization_id IS NULL`, `legacy_source = 'riser'`, and the global asset type with key `riser`. Remove that asset type only if it has no remaining assets. Never delete or change `riser`: it remains the compatibility source and can reconstruct the registry copy.
