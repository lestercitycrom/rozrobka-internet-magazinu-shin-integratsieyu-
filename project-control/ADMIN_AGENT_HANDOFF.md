# Admin agent handoff

Updated: 2026-09-18. This is a coordination and safety handoff. It does not
authorize a production release, a database change, or a staging mutation.

Owner update, 2026-09-23: Modern is the canonical storefront; Classic remains a
retained comparison profile, not a parallel development line. See
[`STOREFRONT_MAINLINE.md`](STOREFRONT_MAINLINE.md). The design gate in Issue #81
is lifted. Admin work may begin from the canonical integration branch below.

## Starting point

- The deployed application baseline is exact commit
  `11d799190a2e1863746f192983371e97f23526a7` on
  `codex/customer-shared-followups`. PR #84 is the cumulative integration PR to
  `main`; use its latest remote head so this handoff/decision is included.
- Both staging storefronts currently run that same SHA:
  `https://staging-easytire.bethub.fun` (Modern) and
  `https://staging-classic-easytire.bethub.fun` (Classic).
- `origin/main` (`3b8ae19...` at this handoff),
  `codex/autonomous-design-finish`, and `codex/classic-storefront` are historical
  ancestors. Starting new admin work from any of them would omit accepted
  customer and two-profile changes.
- `codex/classic-storefront` is not a second maintenance line. The later
  `codex/customer-shared-followups` branch contains it and is the shared source
  for both profiles.

Create a fresh clone or Codex worktree in a third directory and create a new
`codex/admin-<task>` branch from the current remote tip of
`codex/customer-shared-followups` while PR #84 is pending. After PR #84 is
merged, use the updated `origin/main`. Before editing, prove that the application
baseline is present:

```powershell
git fetch origin
git merge-base --is-ancestor 11d799190a2e1863746f192983371e97f23526a7 origin/codex/customer-shared-followups
git switch -c codex/admin-<task> origin/codex/customer-shared-followups
git status --short
```

The ancestor command must exit zero and the worktree must be clean. Do not work
in or copy files from the old `D:\The works\shop-ndi` directory. Do not share
`D:\The works\shop-ndi-design` with another running agent. Do not reset,
clean, force-push, or overwrite another worktree.

## One application, two storefront profiles, one admin

- Modern and Classic are two releases of the same application source, selected
  server-side by `STOREFRONT_DESIGN_PROFILE`. The design is not selected through
  a URL parameter or an administrator setting.
- Modern is the default profile. Classic has
  `STOREFRONT_DESIGN_PROFILE=classic`, a scoped Classic stylesheet, a separate
  host/release root/session/cache/view/log runtime, and the Modern site as its
  canonical origin.
- Both profiles share the database, catalogue, prices, content and public media.
  Any persisted admin edit is therefore visible to both storefronts where that
  content is used.
- There is exactly one CRM/admin: `https://staging-easytire.bethub.fun/admin`.
  Classic `/admin` redirects to the canonical Modern host. Do not build or
  deploy a second admin, duplicate workers/schedulers, or add profile-specific
  admin data.
- Admin roles are enforced by routes and middleware: Owner, Editor and
  Operations have different capabilities. A UI change must not expose a route
  or action to a role that the server does not authorize.

Admin presentation is owned by `resources/css/admin-system.css`,
`resources/views/components/layouts/admin.blade.php`, the admin views, and the
guarded admin JavaScript modules. Keep new selectors under the existing admin
shell/classes. Do not alter Modern/Classic storefront presentation as a side
effect. If public UI must change, scope it explicitly to
`[data-storefront-design='modern']` or
`[data-storefront-design='classic']` and verify both profiles.

## Data and security boundary

- Staging has the real API-backed catalogue and shared persistent application
  data. It is not disposable even though it is not production.
- Never run `migrate:fresh`, seeders, catalogue imports, reset/cleanup commands,
  ad-hoc SQL, payment/order transitions, NDI writes, mailings, Telegram/Notion
  delivery, or test suites against staging.
- Never log, copy, commit or screenshot `.env` values, credentials, customer
  PII, session cookies or provider payloads. Use synthetic records in isolated
  local tests and redact evidence.
- Do not weaken authentication, CSRF, role middleware, validation, the dirty
  form guard, audit/history behavior, or disabled integration gates to make a
  UI test pass.
- A schema, dependency, queue/worker, authorization, integration or destructive
  data change is a separate risk class. Stop and obtain an explicit scope and
  release plan before implementing or deploying it.

The Hetzner firewall intentionally limits inbound SSH/22 to the operator's
current public `/32` address. Keep that restriction. If the ISP changes the
address, update only that source entry through the Hetzner console; do not open
SSH to `Any IPv4`/`Any IPv6`. Do not commit the operator's current address.

## Safe implementation and verification

1. Read the actual admin task and map every requested behavior to routes,
   controller/request validation, role, NB/RU copy, desktop and 390/320 layouts.
2. Work locally with a unique SQLite database and isolated cache/storage paths.
   All integration gates remain off. Never point test variables at the staging
   database or reuse the application's runtime `.env`.
3. Run focused `tests/Feature/Admin` and adjacent domain tests for behavior.
   Run the production Vite build when CSS, JavaScript or Blade asset ownership
   changes.
4. Run the relevant Playwright admin projects on their local seeded fixture at
   desktop, 390 and 320 px, in NB and RU where the surface is localized. The
   default Playwright config performs `migrate:fresh`; therefore its
   `E2E_DB_DATABASE` must be an explicit disposable local path and its port must
   not be a live or already-running application.
5. Cover real keyboard focus, mobile drawer/focus return, field errors,
   unsaved-change protection, table overflow/labels, long content and each
   affected role. Do not reduce existing assertions to make a redesign pass.
6. Before handoff, run formatting/diff checks and the broad regression set
   proportionate to the change. GitHub Actions is currently prevented from
   starting jobs by the account billing/spending condition; report local facts
   and do not claim cloud CI is green or change workflow/branch protections.

Useful existing coverage:

- `tests/Feature/Admin/`
- `tests/E2E/admin.smoke.spec.ts`
- `tests/E2E/admin.p0.spec.ts`
- `tests/E2E/admin.clients.spec.ts`
- `tests/E2E/admin.operations-day.spec.ts`
- `tests/E2E/admin.service-requests.spec.ts`
- `tests/E2E/admin-homepage-content.spec.ts`
- `tests/Audit/issue-41-admin-visual.spec.ts`
- `playwright.config.ts` and `playwright.issue-41.audit.config.ts`

## Git, review and deployment

- Commit and push only the new `codex/admin-<task>` branch. Do not merge open
  stacked PRs yourself, push `main`, rewrite history, or force-push. Target the
  canonical integration branch while PR #84 is pending; after its merge, target
  `main`.
- State the exact base and head SHA, changed files, roles/surfaces affected,
  migrations/config/dependencies (normally none), and exact local test counts.
- Coding and local testing may begin immediately. A live deployment is a later
  action and requires the actual admin scope, focused verification and explicit
  release authorization; this handoff alone is not that authorization.
- Never use `deploy/push-staging.ps1` or push `staging/main` for unmerged admin
  work: the legacy main hook can back up, migrate and seed.
- Do not blindly reuse `deploy/customer-shared-staging.sh`: it is pinned to the
  completed customer-follow-up scope and predecessor. Prepare a separately
  reviewed, fail-closed helper for the exact admin task if a preview is later
  authorized.
- A no-schema admin preview should normally publish the same reviewed source SHA
  to both release roots, preserve each host environment/profile/isolation, retain
  both current `11d7991...` releases for rollback, and make no database,
  integration, nginx or service changes. If a migration is genuinely required,
  use a separate backward-compatible migration/backup/rollback plan instead of
  treating it as a visual preview.
- Live verification is bounded and non-destructive: revision/health/assets,
  public GETs and authorized read-only admin inspection. Do not submit real
  forms, orders, payment actions, imports or settings merely as a smoke test.

Current retained runtime facts at this handoff:

- active Modern and Classic SHA: `11d799190a2e1863746f192983371e97f23526a7`;
- active release on both hosts:
  `20260917024511-11d799190a2e-customer-shared`;
- retained direct predecessor on both hosts:
  `20260917021110-b25bc47621c0-customer-shared`;
- post-release runtime/isolation read-back: 18/18 checks true;
- PR #84 remains open and unmerged.

## Minimum first status from the admin agent

Before implementation, report: fresh directory, new branch, exact base SHA,
clean status, affected admin surfaces and roles, whether the task changes schema,
configuration, dependencies or integrations, and the planned isolated tests.
