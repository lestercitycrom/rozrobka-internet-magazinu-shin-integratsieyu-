# Two-design preview

Owner instruction, 2026-09-17: publish the earlier visual treatment on
`https://staging-classic-easytire.bethub.fun`, retaining the current modern site
at `https://staging-easytire.bethub.fun`. Share the existing catalogue/database;
do not roll business code back, migrate, reseed, activate integrations or merge.

## Historical boundary

- `8ea1a65cb34f7786764c520d6662fe86201d310d` completed the original customer
  homepage package (#48–51).
- `bf7f03e3d3265174e4e4307064769a742dd087cc` is the accepted pre-PR69 main and
  the primary visual donor. Reference branch: `codex/reference-classic-bf7f03e`.
- PR69 (`47dfdee2bb9538ba70742b0c88cf1edd4a85707c`) mixes later client fixes
  with a global redesign; there is no single commit with all of those fixes but
  none of the redesign. `3b8ae190512dd897cd0133aa3c2771d0e890196c` is only a
  secondary reference for red-card details, not a pre-redesign baseline.
- Functional/source base: `7165ca13090e3f0f122b181cc6c37eb6fc9f874f`, retained
  on `codex/autonomous-design-finish`. Work branch: `codex/classic-storefront`.
- Modern runtime remains `5472266d2fe9412867b5d7f5eb6e5d6b8e26a808`.
  Application business code, routes, database definitions, shared JS and package
  locks are identical between that runtime and the source base.

Classic is a selective visual restoration, **not** an old application rollback
or a claim of pixel-identical historical pages. It preserves current content,
catalogue filters, order rules, the fixed two-item test product, video controls,
mobile navigation and accessibility fixes. New Telegram feature requests are
outside this split.

## Isolation contract

`STOREFRONT_DESIGN_PROFILE` selects `modern` (default) or `classic`; it is not
user-switchable through query parameters. Only Classic loads the additional
scoped stylesheet and conditional decorative markup. Classic uses the primary
origin for canonicals and adds noindex/nofollow; nginx also blocks indexing.

Separate release root, current symlink, HTTPS certificate and nginx virtual
host. Primary current/env/nginx configuration remain untouched. Same database
credentials, catalogue and public media; separate host-only session cookie,
local sessions/cache/compiled views/logs. No second worker, scheduler or admin:
Classic `/admin` directs to the canonical staging CRM. Database-backed records
are shared: a real edit/order on either site is not a separate data sandbox.

Existing integration configuration is preserved, never enabled by this work.
Checks on staging must not submit orders/forms or invoke supplier/payment APIs.
Automated interactive checks use the isolated local SQLite fixture only.

## Verification / deployment

Local PHP regression: 654 passed, 6,020 assertions, one existing Windows
symlink-capability skip. Authored-source Pint and Vite production build pass.
Tests use SQLite `:memory:` and isolated cache/storage with external gates off.

Local two-profile Playwright matrix: 60 passed, 24 intentional project-scope
skips, no failures. Includes NB/RU at 1440/390/320 and additional Classic finder
capacity checks at 901/1024. The isolated deployment safety suite also passed
locally, including the real-PHP null-session-domain check and mocked activation
failure/rollback. It does not deploy to a server.

The Playwright matrix covers both profiles, NB/RU and 1440/390/320 widths using
the local-only fixture. Addressable captures are under
`application/tmp/classic-preview/evidence/`; full-page captures are overview
artifacts, not a substitute for readable section/viewport review. A local-only
freshness window may be extended for the retained fixture; live catalogue
freshness rules and data are not changed.

Initial visual review corrected dark-section lead contrast. The Classic finder
uses the client's photo 33 (right-side box, 2×2 categories): this intentionally
omits the later `d69d8d2a` desktop bottom-track override present in the donor.

Exact-head CI, final browser results and runtime read-back are recorded in the
publication handoff. A Git branch or passing build alone does not prove deployment.

## Host setup and release procedure

- Root: `/var/www/staging-classic-easytire.bethub.fun`, with regular `releases/`
  and `deploy.lock`, owned by `easytire-deploy:www-data` (directories 2750,
  lock 0640). Existing primary root and releases are not moved or copied over.
- Separate certificate at
  `/etc/letsencrypt/live/staging-classic-easytire.bethub.fun/`; use the checked-in
  bootstrap HTTP vhost for webroot issuance, then the final Classic vhost.
  Its ACME directory is `<classic-root>/shared/acme`. Validate `nginx -t`
  before reloading. Primary vhost bytes and certificate remain unchanged.
- Publish the feature branch to GitHub, then only its feature ref to the server
  bare repository. Never push its `main` ref: the existing hook deploys primary.
- Execute the published `deploy/classic-preview-staging.sh <full-sha> --check`
  and then `--deploy` as `easytire-deploy`. The helper pins the source branch,
  source base and primary runtime and refuses business-code drift. Build/cache
  commands run before the only shared public-media symlink is attached.
- Activation and failure rollback affect only the Classic `current` symlink;
  previous/failed releases are retained. No worker/scheduler restarts. No
  database rollback is appropriate: this release does not modify its schema
  or contents. A first-release failure leaves Classic without an active release.
- `/up` and `/design-revision.txt` are activation probes. Follow with read-only
  public page, asset, HTTPS, canonical, noindex and cookie checks. Verify primary
  current/revision/env/vhost checksums still match the recorded preflight.

Primary preflight, before provisioning the new host:

- Runtime `5472266d2fe9412867b5d7f5eb6e5d6b8e26a808`.
- Primary nginx SHA256 `ba1ef20c882931034287c6a95942a59929d64937ceacb65260075ca7b38ea1f3`.
- Primary release env SHA256 `f88551d1af0a0dd5cebd891c1231328679bad14a4591ed800ae7b0d1a933eda7`.
- Primary shared env SHA256 `c4122fd30913f9f5ffe8f4940fb72a7f75e8b83256a373b3df0bd9cb95212e80`.

Hashes are integrity evidence, not credentials. Environment content must not be
included in logs, commits or browser evidence.
