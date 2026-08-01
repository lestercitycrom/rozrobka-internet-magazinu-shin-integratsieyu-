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

## Backup checklist

- Verify encrypted database backups and a recent restore test.
- Verify retention, off-host storage and restore ownership.
- Do not place supplier files, credentials, filesystem paths or raw payloads in monitoring output or runbook tickets.
