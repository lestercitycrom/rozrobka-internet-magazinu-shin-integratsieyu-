# Runtime precondition checkpoint B

- Issue: #34.
- Draft PR: #43.
- Reviewed PR head before this evidence update: `9772eb8bd7e45f49fcd23c397ac5f410639d31c0`.
- Application candidate remains: `f70f358f06af384364973951eaab8d4fac2b2203`.
- Deployment performed: no.
- Worker started or restarted: no.
- Queue row consumed or mutated: no.

## B1 — effective external gates

Before hardening, staging had test Dintero payments, NDI inquiry, NDI C1 test mode, and the NDI sandbox catalogue/inquiry/order gates enabled. Live NDI order mutation and fulfilment reads were disabled. Customer email delivery, Notion and Telegram were disabled, and mail used the non-delivery `log` transport.

The authorised protected-environment mechanism set these eight gates to false:

- Dintero payment enablement;
- NDI inquiry;
- live NDI order mutation;
- NDI C1 test mode;
- NDI fulfilment reads;
- NDI sandbox catalogue;
- NDI sandbox inquiry;
- NDI sandbox order.

The helper updated both protected environment copies but could not invoke Artisan from PHP because the CLI policy disables `passthru`. The authorised cache refresh was therefore completed with separate `config:clear` and `config:cache` Artisan commands. Effective Laravel configuration was then read back:

- every payment/NDI gate listed above: disabled;
- customer email delivery: disabled;
- mail transport: `log`;
- Notion: disabled;
- Telegram: disabled.

No payment, NDI request/import/order, email, Notion or Telegram operation was performed.

## B2 — bounded queue snapshot

- Boundary: `2026-09-04T08:53:31Z`, maximum queue row ID `46179`.
- Rows inside the boundary: `301` (below the `1000`-row stop limit).
- Queue: `default` — 301.
- Resolved class: `App\Jobs\RecordQueueHeartbeat` — 301.
- Attempts: zero — 301.
- Reservation state: 301 unreserved, 0 reserved.
- Distinct classes: 1; unresolved envelopes: 0.
- Age buckets: `<1h` 6; `1–6h` 30; `6–24h` 108; `1–7d` 157; `>7d` 0.

Repository inspection classifies `RecordQueueHeartbeat` as `internal/maintenance`: its handler writes only the queue-heartbeat timestamp to the application cache. It contains no customer/order/payment/notification action and no external-network call.

The bounded representative sample was the oldest and newest row for that single class. Both used the `default` queue, had zero attempts, were unreserved and immediately available; their creation times were `2026-09-02T06:38:01Z` and `2026-09-04T08:48:01Z`. Raw payloads, payload arguments, row-level business identifiers, credentials and PII were neither printed nor committed.

Final queue read-back at `2026-09-04T08:57:27Z` found all 301 boundary rows still present and none reserved. Total remained 301, so ambient growth after this snapshot was 0 at the final boundary. Failed jobs remained 0.

## B3 — supervised-worker diagnosis

- The managed systemd unit is loaded and enabled, but `inactive/dead`; main PID is zero.
- The installed unit checksum exactly matches the reviewed repository unit.
- The journal shows normal heartbeat completion followed by an explicit orderly service stop at `2026-09-02T06:37:39Z`. It shows no crash or failing job immediately before the stop.
- `Restart=always` does not override an explicit systemd stop, so the enabled unit remained inactive.
- The managed worker consumes the database-backed default queue with sleep 3 seconds, 3 tries, 10-second backoff, 90-second per-job timeout and a 3600-second process lifetime. Systemd restarts it after normal lifetime exit or failure.
- Starting the unit now would immediately consume the whole existing heartbeat backlog; that was not authorised and did not occur.

Bounded recovery plan, requiring a separate PM runtime gate:

1. keep all external gates in the verified disabled state;
2. record a fresh queue count/classification and failed-job count;
3. start the existing managed systemd unit, never an ad-hoc detached worker;
4. watch the supervised service, queue depth, failed jobs and heartbeat until the internal backlog drains;
5. stop and report on any class drift, failed job or unexpected external gate; otherwise repeat health and HTTP checks.

## B4 — repeated health and HTTP checks

- Database: healthy.
- Queue/cache connections: database-backed and reachable.
- Scheduler timer: loaded, enabled, active/waiting; heartbeat age 0 minutes.
- Worker heartbeat: stale as expected while the worker is intentionally not started.
- Failed jobs: 0.
- Outbox pending/failed: 0/0.
- Supplier media pending/failed: 0/0.
- Disk: healthy with about 7.27 GB free; inode use 21%.
- Homepage, admin login and robots: HTTP 200.
- NDI data freshness remains the known external Issue #19 condition and continues to fail closed.

Only protected gate values and the application configuration cache were changed. No deployment, release/symlink switch, scheduler/service mutation, queue reservation/consumption/update/delete/retry, form submission, customer/order/content/settings mutation or external integration action was executed.

Deployment remains blocked pending exact-head PM review and a separate `STAGING_DEPLOY_APPROVED` decision.
