# EasyTire operational runbook

## Queue and scheduler

- Run workers with `php artisan queue:work --tries=3 --timeout=90` under a process supervisor.
- Run `php artisan schedule:run` every minute from cron; use `schedule:work` only for local development.
- Check `php artisan easytire:health` after deploy. Exit `0` is healthy; `1` requires investigation. Its output is intentionally redacted.

## Deploy and rollback

- Enable maintenance mode, deploy the release, install locked dependencies, migrate, clear/refresh caches, then disable maintenance mode.
- Verify the health command, homepage, admin login, queue worker and scheduler.
- Roll back to the prior release and restore compatible application code if verification fails; never run destructive schema rollback without an approved recovery plan.

## Backup checklist

- Verify encrypted database backups and a recent restore test.
- Verify retention, off-host storage and restore ownership.
- Do not place supplier files, credentials, filesystem paths or raw payloads in monitoring output or runbook tickets.
