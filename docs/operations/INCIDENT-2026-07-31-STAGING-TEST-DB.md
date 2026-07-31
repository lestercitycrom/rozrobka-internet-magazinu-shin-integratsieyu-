# Staging test database incident — 2026-07-31

## Summary

A PHPUnit feature test was run from a temporary copy of the active staging release. The copy retained `bootstrap/cache/config.php`, so Laravel used the cached staging MySQL connection instead of the `sqlite :memory:` values from `phpunit.xml`. `RefreshDatabase` therefore executed `migrate:fresh` against `shop_ndi_staging`.

This was a staging-only incident. The pre-incident database contained no users, products, supplier SKUs, orders, service requests, imports, media, or admin/customer data. Six published service pages were removed and were restored exactly from MySQL binary logs. Ephemeral HTTP sessions were intentionally not restored.

## Detection and scope

- The incident was detected when the last migration changed from batch `2` to batch `1` after a test run.
- MySQL binlog review found destructive test runs at `14:56:59`, `15:00:24`, and `16:12:06` UTC.
- An isolated recovery database was reconstructed to binlog position `90861`, immediately before the first destructive run.
- Recovery counts showed `service_pages=6`, `sessions=49`, and zero records in all material business tables.

## Recovery

- A post-incident database dump was stored at `/var/backups/shop_ndi_staging-postincident-20260731-1612.sql.gz`.
- The six original `service_pages` rows were separately exported to `/var/backups/shop_ndi_staging-service-pages-preincident-20260731.sql`.
- Only the six verified service rows were copied back to `shop_ndi_staging`.
- The `2026_07_31_210000_add_is_active_to_media_assets` migration was returned to its confirmed batch `2` metadata.
- Health, migration status, all six public service cards, and HTTP `200` responses were verified after recovery.

## Permanent controls

- Laravel feature/security tests fail before setup unless the active database is exactly `sqlite :memory:`.
- PHPUnit must never run from an active cached `current` release.
- A temporary test release must not retain `bootstrap/cache/config.php`.
- Deployments must create and verify a database backup before migrations.
- External NDI calls remain disabled until credentials and the approved test procedure are present.
