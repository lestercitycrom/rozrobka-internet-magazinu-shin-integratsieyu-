# Final staging acceptance

- Issue: #34.
- PR: #43.
- Application and deployed staging revision: `f70f358f06af384364973951eaab8d4fac2b2203`.
- Immediate rollback revision retained: `ee512d723e78a65a89bfdee4268c3a779f632b33`.
- Secondary rollback revision retained: `01a931d432b32a5a0633d1b508256b225387d429`.
- Acceptance runtime: staging only; no production action.

## Managed queue recovery

The existing managed worker was started only after a fresh queue inventory confirmed that every queued row was the internal `App\Jobs\RecordQueueHeartbeat` maintenance job, unreserved, zero-attempt and below the approved boundary. All payment, supplier-write, customer-email, Notion and Telegram gates were disabled before the worker start.

The worker drained the bounded backlog through the reviewed managed service. Two consecutive reads then confirmed an empty queue, zero reservations, a fresh heartbeat, zero failed jobs, zero pending/failed outbox messages and zero pending/failed supplier-media work. No unknown job class or external side effect appeared.

## Exact atomic deployment

The protected staging deployment workflow received the exact application commit above. Its release hook completed Composer and frontend installation/build, all Laravel tests (`424` tests, `3920` assertions), database backup, migration check, idempotent seeding and framework cache rebuild before atomically switching the current release.

The post-deployment revision read-back matched the approved SHA exactly. Both named rollback revisions remained present.

## Public live acceptance

The Issue #26 public audit was repeated against live staging at all required viewports: `1920x1080`, `1440x900`, `1366x768`, `1280x800`, `390x844` and `320x800`.

- Effective matrix: 43 passed, 41 expected route/viewport skips, zero unresolved failure.
- Bokmal coverage: all 41 public surfaces at all six viewports.
- Hidden Russian QA coverage: representative parity surfaces at desktop and mobile widths; no public language selector was introduced.
- Interaction coverage: all 24 required desktop cases and all 5 required mobile cases were present.
- Catalogue: live filter busy/count feedback, open/empty states, sort, product detail and fixed sandbox quantity were verified without external mutation.
- Header/navigation: sticky states, desktop submenu and mobile drawer passed pointer and keyboard checks.
- Forms and controls: default, hover, focus, active and disabled contracts passed; localized validation keys did not leak into the DOM.
- Geometry: no document-level horizontal overflow, clipped primary action or hover dimension shift was found.
- Hover inventory: 111 visible targets, zero width/height shift, zero transform, zero border-width change. The only newly added hover shadow was the intentional service-offer card family (13 targets); text-link buttons remained flat.
- Hero: the finder did not intersect the documented EasyTire brand or vehicle focal regions at any required viewport.
- Contact map: the live Google frame rendered after network settle and pointed to the approved EasyTire location.
- Browser signals: no unresolved console, page or failed-request problem in the accepted matrix.

The initial full run contained one formal failure because the audit helper still used an obsolete synthetic product route. The same isolated product test was immediately repeated with the actual staging-only product route and passed. This was a test-fixture route correction, not an application change or production-data claim.

## Authenticated admin live acceptance

The authenticated admin audit covered 23 surfaces in both Bokmal and Russian at the same six viewports: 276 page checks plus 12 interaction contracts.

- Every audited response returned HTTP 200.
- No document overflow, clipped control, unresolved translation key, failed image or browser error was found.
- Wide tables remained reachable through explicit focusable scroll regions.
- Keyboard focus, anchored hover, mobile drawer open/Escape behaviour and focus return passed.
- The audit restored the administrator's original Russian locale after verification.
- The admin retained the same black, white, warm-neutral and EasyTire red visual system as the storefront without copying an external template.

## Durable visual-ledger reconciliation

The 44-row ledger in `docs/management/STOREFRONT_VISUAL_FINDINGS.csv` preserves every original finding and adds the exact resolution SHA, this acceptance evidence and a final disposition.

- `AUD-26-001`, `003-018`, `021` and `042`: `resolved_live_staging`.
- Protected MATCH rows: `accepted_match_rechecked`.
- `AUD-26-002`: remains `blocked_external` under Issue #19 because the authoritative NDI feed and official product media are not current. The fail-closed catalogue behaviour remains correct.
- `AUD-26-040`: remains `not_verified_by_design`; real Dintero/NDI/customer-delivery success transitions were outside this authorization.

No historical finding was deleted or rewritten as if it had never existed.

## Final runtime read-back

The final read-only checkpoint at `2026-09-04T10:03:13Z` confirmed:

- deployed revision exactly `f70f358f06af384364973951eaab8d4fac2b2203`;
- database, database queue/cache, scheduler and managed worker healthy;
- managed worker loaded, enabled and running;
- queue depth/reservations `0/0`;
- failed jobs `0`;
- outbox pending/failed `0/0`;
- supplier media pending/failed `0/0`;
- payment, NDI inquiry/order/C1/fulfilment and every NDI sandbox gate disabled;
- customer email delivery disabled and mail transport `log`;
- Notion and Telegram disabled;
- homepage, admin login and robots HTTP 200;
- disk healthy with about 5.1 GB free and inode use 22%;
- NDI freshness still false and correctly tracked as the external Issue #19 blocker.

No payment, supplier request/order/import, customer email, Notion or Telegram action was triggered by this acceptance pass. The temporary read-only runtime scripts were removed after the final check.

## Evidence index

- `live/public-desktop-contact-sheet.png` and `live/public-mobile-contact-sheet.png`: key storefront families.
- `live/admin-desktop-contact-sheet.png` and `live/admin-mobile-contact-sheet.png`: NB/RU authenticated-admin families.
- `live/catalog-filter-open-desktop.png` and `live/catalog-loading-desktop.png`: live catalogue feedback.
- `live/product-detail-desktop.png`: product detail quantity/action composition.
- `live/cart-empty-desktop.png`: compact single-action empty cart.
- `live/sticky-header-dark-desktop.png`: readable sticky header over dark content.
- `live/hero-focal-desktop.png`: hero finder/focal composition.
- `live/button-link-hover-desktop.png`: flat text-link hover contract.
- `live/home-process-desktop.png`: revised process rhythm.
- `live/battery-cta-desktop.png`: revised battery-page CTA hierarchy.
- `live/contact-map-loaded-desktop.png`: rendered live map after network settle.

## Non-blocking observations

- At 320/390 px the authenticated-admin top bar intentionally truncates its secondary location label with an ellipsis. Identity, current section and navigation remain readable and reachable; this is optional P3 polish, not a release defect.
- Representative real NDI products and official supplier imagery cannot be visually accepted until Issue #19 receives a fresh authoritative dataset. No fabricated product photography was introduced to hide that external condition.
