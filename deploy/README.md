# EasyTire staging deployment

## Design and limited admin-polish preview (Issue #44)

[PM comment 5673643971](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5673643971) permits unmerged published design candidates on the existing staging host; [PM comment 5678136657](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5678136657) extends that exact handoff with the reviewed CRM/browser verification and limited admin polish. Do **not** use the main-branch post-receive flow for this preview: it runs migrations and scoped seeding.

After committing/pushing `codex/issue-44-design-v2` to GitHub and passing the build/focused checks, push the same exact branch/SHA to the existing server bare repository (the main-only hook ignores it). Run the committed `deploy/preview-staging.sh SHA --check`, then `--deploy`, as `easytire-deploy`. The narrow script validates an explicit exact-path allowlist, requires the reviewed preview to still be active before replacement, uses the existing deploy lock, builds with isolated candidate storage/environment, preserves current runtime settings and shared storage, and atomically activates a separate release. `/design-revision.txt` exposes the opened SHA. Accepted `bf7f03e` and the previous preview are retained; there are no migrations, seeds, pruning, tests on shared storage or service restarts. A failed post-switch HTTP check restores the previous code/assets pointer, never the database.

Post the exact opened version and review pages in the design PR; do not silently replace a version while it is being reviewed. These previews are not final design acceptance.

Run the script directly from the received commit, not an uncommitted upload or an older current checkout (replace `PUBLISHED_SHA` with the verified 40-character GitHub SHA):

```bash
set -o pipefail
git --git-dir=/srv/git/shop-ndi.git show PUBLISHED_SHA:deploy/preview-staging.sh | bash -s -- PUBLISHED_SHA --check
git --git-dir=/srv/git/shop-ndi.git show PUBLISHED_SHA:deploy/preview-staging.sh | bash -s -- PUBLISHED_SHA --deploy
```

The exact same branch is first pushed to GitHub, then to `easytire-deploy@95.217.153.189:/srv/git/shop-ndi.git` as `codex/issue-44-design-v2` (never `main`). Compiled Blade templates are release-local; shared templates/media are neither replaced nor flushed. Catchable interruptions during activation restore the previous pointer; an uncatchable process kill requires inspecting `REVISION`/`PREVIOUS_RELEASE` before retrying.

The one-time bootstrap also installs two systemd units:

- `easytire-queue.service` keeps the database queue worker alive and restarts it after failure;
- `easytire-schedule.timer` runs Laravel's scheduler every minute.

Every release activated by the main-branch hook calls `queue:restart`, so the long-running worker switches to the new code without granting the deploy hook unrestricted `sudo` access. Design-only previews do not restart workers.

Verify them after bootstrap or a server change:

```bash
systemctl is-active easytire-queue.service
systemctl is-active easytire-schedule.timer
systemctl list-timers easytire-schedule.timer --no-pager
sudo -u easytire-deploy /usr/bin/php /var/www/staging-easytire.bethub.fun/current/application/artisan schedule:list
```

This directory contains the reviewed server bootstrap material only. It does not initialize, commit, or push the local repository. The Laravel project is intentionally located in repository subdirectory `application/`.

## One-time server setup

Copy this directory to the server as `/root/shop-ndi-deploy`, review it, then run:

```bash
sudo bash /root/shop-ndi-deploy/bootstrap-staging.sh
```

The script creates an isolated deploy user, a bare repository, MySQL database credentials stored only in the shared server `.env`, PHP SQLite support, a signature-verified official Composer installation, the new nginx vhost, and HTTPS.

## Remote used after review

```text
easytire-deploy@95.217.153.189:/srv/git/shop-ndi.git
```

The repository includes `push-staging.ps1`. It refuses a dirty worktree or a branch other than `main`, runs Pest, the production frontend build and Playwright locally, and only then pushes to the staging remote. On the current Windows machine pass the explicit PHP and npm executables because its global shims are broken:

```powershell
.\deploy\push-staging.ps1 `
  -PhpBinary 'C:\OSPanel\modules\PHP-8.3\php.exe' `
  -NpmBinary 'C:\Program Files\nodejs\npm.cmd'
```

Only pushes to `main` run the hook. Each deployment checks out an isolated release and runs Composer, `npm ci`, the production build, and the full Laravel suite with a temporary hermetic `.env` and the candidate's own physical `application/storage`. Only after the suite succeeds does the hook discard all test-created storage, attach the persistent shared storage and runtime `.env`, clear candidate configuration, create an atomic MySQL backup, run migrations and the scoped production seeder, and atomically update `current`. Tests therefore cannot reach the staging database, cache, integrations, or persistent media. The hook keeps five releases and, after successful activation, retains up to 20 completed database backups in shared `storage/app/backups` while the cumulative release is being accepted. Redis is deliberately not installed; the runtime `.env` selects database-backed cache, queues, and sessions. Nginx serves `current/application/public`. The approved sharp storefront is served on the primary staging domain; the former comparison hostname redirects to it and is not a second storefront.

The backup command reads credentials only through Laravel configuration. It invokes `mysqldump` as an argument array, passes the password only as `MYSQL_PWD` in the child process environment, writes a `*.partial` gzip before atomically renaming it, and aborts before migrations when the active connection is not MySQL or a non-empty backup cannot be produced.

The hook records the activated Git hash in `current/REVISION`. After push, the local script reads that marker over SSH and fails explicitly if Git accepted the commit but the post-receive build did not activate it. This distinction matters because Git cannot roll back a received ref when a post-receive hook fails.

Composer and the Laravel test command require `proc_open` in PHP CLI. Laravel's operational database reports also require PHP `intl`, which the bootstrap installs explicitly. The bootstrap verifies `proc_open` and stops with a clear error when a server hardening policy disables it. PHP-FPM remains independent and may keep a stricter `disable_functions` policy.

## Rollback

A failed build never changes `current`. To roll back an already activated release, first list the five retained directories and resolve the current target. Select one exact directory below `releases/`, create a temporary symlink inside the same application root, and atomically replace `current` with `mv -T`. Never point `current` outside `/var/www/staging-easytire.bethub.fun/releases/`.

Database migrations must remain backward-compatible with at least the previous release. A code rollback does not automatically reverse migrations or delete data.
