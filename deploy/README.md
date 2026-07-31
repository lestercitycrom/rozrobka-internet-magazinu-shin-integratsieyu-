# EasyTire staging deployment

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

Only pushes to `main` run the hook. Each deployment checks out an isolated release, enters `release/application`, uses the shared `.env`, symlinks `application/storage` to the persistent shared storage, then runs Composer, `npm ci`, the production build, tests, an atomic MySQL backup, and migrations before atomically updating `current`. The hook keeps five releases and, after successful activation, retains the 14 most recent completed database backups in shared `storage/app/backups`. Redis is deliberately not installed; `.env` selects database-backed cache, queues, and sessions. Nginx serves `current/application/public`.

The backup command reads credentials only through Laravel configuration. It invokes `mysqldump` as an argument array, passes the password only as `MYSQL_PWD` in the child process environment, writes a `*.partial` gzip before atomically renaming it, and aborts before migrations when the active connection is not MySQL or a non-empty backup cannot be produced.

The hook records the activated Git hash in `current/REVISION`. After push, the local script reads that marker over SSH and fails explicitly if Git accepted the commit but the post-receive build did not activate it. This distinction matters because Git cannot roll back a received ref when a post-receive hook fails.

Composer and the Laravel test command require `proc_open` in PHP CLI. The bootstrap verifies this and stops with a clear error when a server hardening policy disables it. PHP-FPM remains independent and may keep a stricter `disable_functions` policy.

## Rollback

A failed build never changes `current`. To roll back an already activated release, first list the five retained directories and resolve the current target. Select one exact directory below `releases/`, create a temporary symlink inside the same application root, and atomically replace `current` with `mv -T`. Never point `current` outside `/var/www/staging-easytire.bethub.fun/releases/`.

Database migrations must remain backward-compatible with at least the previous release. A code rollback does not automatically reverse migrations or delete data.
