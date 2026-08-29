# EasyTire operational runbook

## Queue and scheduler

- Run workers with `php artisan queue:work --tries=3 --timeout=90` under a process supervisor.
- Run `php artisan schedule:run` every minute from cron; use `schedule:work` only for local development.
- Check `php artisan easytire:health` after deploy. Exit `0` is healthy; `1` requires investigation. Its output is intentionally redacted.

## First administrator

- Run `php artisan easytire:bootstrap-admin` interactively only. It prompts for password and confirmation without accepting or printing a password in command arguments/output.

## Deploy and rollback

- Enable maintenance mode, deploy the release, install locked dependencies, migrate, clear/refresh caches, then disable maintenance mode.
- Verify the health command, homepage, admin login, queue worker and scheduler.
- Roll back to the prior release and restore compatible application code if verification fails; never run destructive schema rollback without an approved recovery plan.

## Test and migration safety

- Take and verify a backup before production migrations.

## NDI product media backfill

- `NDI_PARSER_VERSION` must be `1.1.0` or newer so tire image identifiers are captured from the official workbook.
- After the release containing product media sources is deployed, run one full `ndi:import` of the three official workbooks. Reusing an already imported bundle is expected because the parser version changed.
- Run `php artisan ndi:media:sync --limit=50` after that import. The hourly scheduler continues bounded retries and future downloads.
- Confirm product media source, downloaded and failed counts before considering the backfill complete. NDI currently supplies image identifiers for only part of the tire rows and none of the wheel rows; missing supplier images use the storefront fallback until an administrator uploads media.
- Run PHPUnit only in a fresh release or temporary workspace, after removing `bootstrap/cache/config.php` from that test copy.
- Never run PHPUnit from an active cached `current` release: cached configuration can target a production database.

## NDI file inbox

- Point `NDI_INBOX_PATH` at a non-public SFTP drop directory owned by the transfer account and readable by the application worker. Do not place it below `public/` or a release directory.
- NDI has stated that catalogue/price files are delivered daily and that the stock file can be delivered hourly or every two hours. Confirm the exact filenames and delivery window before enabling the scheduler.
- Keep `STOREFRONT_STOCK_MAX_AGE_MINUTES=180` only with the hourly/two-hour stock agreement. A stale feed intentionally disables cart mutation and checkout until a successfully validated stock file arrives.
- The command reads only stable direct `.xlsx` files, rejects empty or unexpectedly truncated snapshots, and never follows symlinks. Monitor non-zero exits and the import health page.
- Rotate succeeded and terminally rejected files from the inbox to restricted archive/quarantine storage. Without rotation the scheduled command must repeatedly enumerate and hash retained files and the directory grows without bound.
- Before production enablement, verify on Linux that SFTP upload is atomic (temporary name followed by rename), cron runs every minute, `ndi:inbox:sync` runs every ten minutes, and the queue worker is supervised.

## Backup checklist

- Verify encrypted database backups and a recent restore test.
- Verify retention, off-host storage and restore ownership.
- Do not place supplier files, credentials, filesystem paths or raw payloads in monitoring output or runbook tickets.

## Shared staging disk maintenance

- Treat the root filesystem as shared infrastructure: identify per-application usage before proposing cleanup and never make another application's database, backup, upload or log data an ad-hoc cleanup target.
- Inventory disk bytes and inodes, active symlink targets, retained rollbacks, backup retention, supplier storage, logs, caches and database/queue storage before deleting anything.
- Keep the active EasyTire release and at least one verified rollback release. Preserve the configured completed database-backup retention in full unless a separate backup/restore change is approved.
- Issue #18 uses `scripts/issue-18-staging-headroom.sh`. Its default `--inspect` mode is non-destructive; execute mode is valid only after the exact reviewed SHA receives `STAGING_MAINTENANCE_APPROVED` for every listed category.
- The Issue #18 script locks the inventoried release sets by count and SHA-256 manifest, validates current symlinks and direct-child paths, and stops before deletion if staging changed after review.
- After approved maintenance, record before/after free bytes and inodes; re-check active/rollback releases, homepage, admin login, database, cache, queue, scheduler, worker and failed jobs. A known NDI freshness warning must remain visible until its separate remediation is completed.
