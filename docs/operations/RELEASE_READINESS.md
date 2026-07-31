# Release readiness checklist

This checklist reflects the current `deploy/post-receive`, `deploy/README.md`, `.env.example`, and operations runbook. It does not add deployment commands beyond those artifacts.

## Before release

- Confirm the release can pass the hook's existing Composer install, `npm ci`, production build, `php artisan test`, migration, and cache steps.
- Review new migrations for backup/restore compatibility. The hook runs `php artisan migrate --force --no-interaction` before the atomic `current` switch.
- Keep all NDI mutation flags disabled: `NDI_INQUIRY_ENABLED=false`, `NDI_ORDER_ENABLED=false`, `NDI_C1_TEST_MODE=false`, and `NDI_FULFILMENT_READ_ENABLED=false` unless separately approved.
- Keep `OUTBOX_NOTION_ENABLED=false` and `OUTBOX_TELEGRAM_ENABLED=false` until each destination contract is approved.
- Confirm no NDI credentials, payloads, or supplier files are committed or placed in release notes.

## C1 gate

**C1 notice must be sent and acknowledged before enabling any NDI order/C1-related capability.** This release does not authorize C1 order notice, reserve, cancellation, or production order traffic.

## After deployment

- Run the existing redacted health check: `php artisan easytire:health`.
- Confirm the existing database queue worker and minute scheduler/cron are running; the scheduler invokes the outbox cadence without overlap.
- Smoke-test `/`, `/dekk`, `/tjenester`, `/admin/login`, an authorized `/admin`, `/admin/orders`, and `/admin/health`.
- Confirm `/robots.txt` and `/sitemap.xml` respond as intended for the environment.

## Rollback and backup verification

- Before switching a release, confirm an encrypted database backup and a tested restore path exist.
- If post-deploy verification fails, restore the previous atomic release as described by the deployment mechanism; do not run destructive schema rollback without an approved recovery plan.
- Recheck health, public routes, admin authentication, queue worker, scheduler, and pending/failed outbox counts after rollback.
