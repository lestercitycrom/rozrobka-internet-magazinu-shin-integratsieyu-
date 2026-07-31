# EasyTire operational runbook

## Queue and scheduler

- Run workers with `php artisan queue:work --tries=3 --timeout=90` under a process supervisor.
- Run `php artisan schedule:run` every minute from cron; use `schedule:work` only for local development.
- Check `php artisan easytire:health` after deploy. Exit `0` is healthy; `1` requires investigation. Its output is intentionally redacted.

## Deploy and rollback

- Enable maintenance mode, deploy the release, install locked dependencies, migrate, clear/refresh caches, then disable maintenance mode.
- Verify the health command, homepage, admin login, queue worker and scheduler.
- Roll back to the prior release and restore compatible application code if verification fails; never run destructive schema rollback without an approved recovery plan.

## Test and migration safety

- Take and verify a backup before production migrations.
- Run PHPUnit only in a fresh release or temporary workspace, after removing `bootstrap/cache/config.php` from that test copy.
- Never run PHPUnit from an active cached `current` release: cached configuration can target a production database.

## Backup checklist

- Verify encrypted database backups and a recent restore test.
- Verify retention, off-host storage and restore ownership.
- Do not place supplier files, credentials, filesystem paths or raw payloads in monitoring output or runbook tickets.
