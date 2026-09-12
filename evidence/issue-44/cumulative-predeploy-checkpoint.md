# CUMULATIVE PREDEPLOY CHECKPOINT — Issue #44

Date: 2026-09-13
Runtime authorisation: none
Deployment decision: not requested by this checkpoint

## Exact revisions and review boundary

- Accepted cumulative application base: `7d7c6c4ad58dca8cd651e7b88bcee2bcaa7eed92`.
- QA/correction branch: `codex/issue-44-cumulative-release-acceptance`.
- Draft review: PR #51.
- PM-reviewed head superseded by these corrections: `93dfa12a7eb50217c11719b69ef2746d69415e46`.
- Current staging application revision: `f70f358f06af384364973951eaab8d4fac2b2203`.
- First and second retained fallback revisions: `ee512d723e78a65a89bfdee4268c3a779f632b33` and `01a931d432b32a5a0633d1b508256b225387d429`.
- The proposed corrected head is the exact commit named in the next PR `ENGINEER UPDATE`; this committed file cannot contain its own resulting hash.

PR metadata must distinguish the accepted base, the superseded reviewed head, the proposed corrected head, the later squash/merge SHA, and the independently read-back runtime SHA. None of these values is interchangeable.

## PM correction 1 — one admin-ownership boundary

The older Issue #45 navigation seeder previously ran before the Issue #47 field journal. An administrator could deliberately return one hero language to the exact pre-release copy and a later general seed would treat it as untouched legacy data. Its independent rollback had the symmetrical problem.

The corrected cumulative path is now:

1. `CustomerHomepageCollectionsSeeder`;
2. `CustomerHomepageCompositionSeeder`, which applies and journals recognized NB/RU hero values;
3. `CustomerNavigationRevisionSeeder`, which defers to that journal and only adopts an exact legacy field missing from an early applied #47 journal;
4. reverse rollback through `RollbackCustomerHomepageReleaseSeeder`, with the #45 rollback explicitly deferring whenever the #47 journal exists.

`DatabaseSeeder` delegates #45–#47 to `CustomerHomepageReleaseSeeder`, so its order is identical to the protected deployment path. A `rolled_back` journal is never silently re-applied. No second ownership column, manifest or destructive data reset was introduced.

The ordered application tests cover:

- full seed, authenticated one-language admin edit to the exact old literal, and repeated full seed;
- apply, authenticated edit away and back to the applied value, complete reverse-order rollback twice, and attempted re-apply;
- absent-row and exact-legacy-row apply/rollback behaviour;
- compatibility adoption into an existing applied journal that lacks only the hero entry;
- preservation of the other language and an unrelated field.

Final local verification after the corrections: Pint passed; both deployment shell files passed `bash -n`; Pest passed `474` tests with `1` expected platform skip and `4,439` assertions. The earlier `artisan test` attempt after the workstation reboot is not counted because its child process ignored the temporary-directory override; the same complete suite was rerun directly through Pest with the intended writable temp directory and passed.

## PM correction 2 — protected, scoped deployment

The repository hook no longer calls the general `DatabaseSeeder`. It invokes only:

```text
Database\Seeders\CustomerHomepageReleaseSeeder
```

The reverse operation is one equally narrow command:

```text
Database\Seeders\RollbackCustomerHomepageReleaseSeeder
```

If a hook fails before the backup/migration boundary, its incomplete release directory is removed. Once database mutation begins, the failed release is retained so the reviewed rollback seeder remains available for scoped recovery. A full database restore is exceptional and requires separate recovery approval.

Four release directories exist before deployment. The hook retains five, so one candidate does not prune any current fallback. Fourteen completed database snapshots exist before deployment; the hook now retains twenty so the protected pre-migration snapshot and bounded retry snapshots do not prune the current set. Cleanup is a later reviewed operation, not part of acceptance.

Installed and candidate hook state from the read-only comparison:

- installed hook SHA-256: `5a397815006c354269e5d83d9f23712b33391ba947840cef5f5761143b8c3477`;
- installed hook still contains the general `db:seed --force` command;
- candidate hook SHA-256: `06d00dd3d5d5f802aa819b2b57014973b5b3d3c79b1db295b361cf6965c1c178`;
- guard installer SHA-256: `2f2186d10afc7d982b03421ae3562ad30aef36aa1c6cfa11c0d3da950ec730bf`;
- the candidate changes only the scoped seeder, failed-release recovery retention and bounded snapshot retention; both candidate hashes must be re-read from the exact accepted merge artifact before transfer.

The installed hook therefore must not be assumed to update itself when `main` is pushed. After exact-head acceptance and merge, but before the protected push, the accepted deploy artifacts must be transferred separately and installed only under the runtime gate described below.

## Staging robots protection, including rollback

Removing the candidate application's static `public/robots.txt` fixes the new release, but the primary rollback revision still contains the permissive static file. A plain symlink rollback followed by an HTTP 200 check would therefore be insufficient.

Two tracked safeguards are included:

- the fresh-bootstrap nginx configuration owns an exact `/robots.txt` response with `Disallow: /`;
- `deploy/install-accepted-staging-guards.sh` derives a candidate from the installed Certbot-managed vhost, requires exact pre-change hook and vhost hashes, inserts exactly one robots location, validates with `nginx -t`, reloads nginx, and restores both files if validation or reload fails.

Installed vhost SHA-256 at read-back: `425b07ce47c2934e7ce42c2b9d9cd0ea2fd72db372abece537affe64c8569b26`. The tracked fresh-bootstrap file is not copied wholesale over the Certbot-managed vhost.
The tracked fresh-bootstrap vhost SHA-256 is `3a35967c66ab0d331e564d4e63a4c1260a6e01f1d2a4179b72c1c8198ef60851`.

No hook, nginx or runtime file was changed during this checkpoint.

## Timestamped worker and scheduler diagnosis

Read-only capture time: `2026-09-12T23:23:57Z`.

| Unit | Effective state and transition | Last/next trigger | Result |
|---|---|---|---|
| Queue worker | enabled, inactive/dead since `2026-09-06 10:49:52 UTC`; last active from `10:40:06` to `10:49:52`; `NRestarts=50` | not timer-driven | last main process exited `0`; unit result `success` |
| Scheduler timer | enabled, failed since `2026-09-06 10:50:00 UTC` | last trigger `2026-09-06 10:49:00 UTC`; next trigger `n/a` | `resources` |
| Scheduler service | static, inactive/dead; last ran `2026-09-06 10:49:00–10:49:01 UTC` | invoked by the timer | last main process exited `0`; unit result `success` |

The effective installed units match the tracked command lines and restrictions. Unit-specific and system journal queries for the transition window return no retained entries. Consequently, the old queue restart chain and timer `resources` result are facts, but their root cause is **not recoverable from retained evidence**. No OOM, application exception or systemd rate-limit cause is claimed without a log.

## Effective runtime gates and scheduler consequences

The same read-only application capture reports:

- database queue and database cache; heartbeats required;
- jobs `0`, failed jobs `0`, outbox pending/failed `0/0`, media pending/failed `0/0`;
- payments disabled; Dintero selected, configured for test, callback signature protection configured;
- NDI live inquiry/order/fulfilment and C1 test mode disabled;
- NDI sandbox catalogue/inquiry/order disabled;
- NDI inbox configured and readable; three visible XLSX files, each already represented by a `succeeded` receipt;
- Notion, Telegram and customer-email delivery disabled; mail transport `log`;
- scheduler and queue heartbeat timestamps both stopped at `2026-09-06T10:49:01+00:00`.

The installed timer has `Persistent=true`. Clearing and starting it may immediately run one missed scheduler activation; it does not replay every missed minute. At the current application revision that activation evaluates:

- outbox every minute (currently empty; external destinations disabled);
- media sync hourly (currently no pending media);
- inbox sync every ten minutes (configured; the three current XLSX fingerprints are already succeeded, but any newly arrived stable file would be imported);
- sandbox catalogue hourly (condition false);
- scheduler heartbeat and queued worker heartbeat every minute.

Therefore a fresh pre-start read-back of gates, queue counts and inbox receipts is mandatory. Any new stable unreceipted inbox file, queued customer work, enabled external destination, or changed revision is a stop condition.

## Controlled service recovery — not executed

Preconditions:

1. no deployment in progress and runtime revision still equals `f70f358f06af384364973951eaab8d4fac2b2203`;
2. payments, NDI API/sandbox writes, Notion, Telegram and customer delivery remain disabled;
3. jobs, failed jobs, outbox pending/failed and media pending/failed remain zero;
4. all visible inbox XLSX files still have terminal successful receipts;
5. installed unit definitions still match the accepted definitions;
6. a separate runtime gate explicitly authorises service recovery.

Managed-unit actions, performed once under that gate:

```bash
sudo systemctl reset-failed easytire-queue.service easytire-schedule.timer
sudo systemctl start easytire-queue.service
sudo systemctl start easytire-schedule.timer
```

At approximately T+0, T+70 seconds and T+140 seconds, record:

```bash
date -u
systemctl show easytire-queue.service easytire-schedule.timer easytire-schedule.service \
  --property=Id,ActiveState,SubState,Result,NRestarts,ExecMainStatus,LastTriggerUSec,NextElapseUSecRealtime
sudo -u <DEPLOY_USER> /usr/bin/php <APP_ROOT>/current/application/artisan easytire:health --no-ansi
journalctl -u easytire-queue.service -u easytire-schedule.timer -u easytire-schedule.service \
  --since '<RECOVERY_START_UTC>' --no-pager -o short-iso-precise
```

Acceptance requires three healthy observations, at least two new scheduler ticks, fresh queue and scheduler heartbeats, zero failed work, and no unexpected integration activity. On any failure or unexpected queue/inbox change, stop immediately and preserve the new evidence:

```bash
sudo systemctl stop easytire-schedule.timer easytire-queue.service
systemctl status easytire-queue.service easytire-schedule.timer easytire-schedule.service --no-pager
journalctl -u easytire-queue.service -u easytire-schedule.timer -u easytire-schedule.service \
  --since '<RECOVERY_START_UTC>' --no-pager -o short-iso-precise
```

Do not use `enable`, `restart`, queue retry, queue flush, inbox import, sandbox refresh or reset commands in this recovery pass.

## Ordered release recipe

1. **Read-only preparation:** repeat revision, unit, queue, inbox, gate, disk, release and backup counts. Stop on drift from the preconditions above.
2. **Code acceptance:** PM reviews the exact proposed PR head and final green CI. Only the PM-approved exact head may be merged. The engineer does not merge midway.
3. **Merge identity:** record the resulting `origin/main` merge SHA and prove its reachable history contains the accepted head/content.
4. **Separate runtime gate:** obtain one explicit approval covering guard installation, controlled service recovery and one deployment of that exact merged SHA. Code acceptance alone grants no runtime write.
5. **Install accepted guards:** transfer only `deploy/post-receive` and `deploy/install-accepted-staging-guards.sh` from the exact merge artifact, verify their hashes, then run:

   ```bash
   sudo bash <ACCEPTED_DEPLOY_DIR>/install-accepted-staging-guards.sh \
     <HOOK_TARGET> <NGINX_VHOST> \
     5a397815006c354269e5d83d9f23712b33391ba947840cef5f5761143b8c3477 \
     425b07ce47c2934e7ce42c2b9d9cd0ea2fd72db372abece537affe64c8569b26
   ```

   Read back the installed hook hash, `nginx -t`, the active vhost hash and the HTTPS robots body. Stop if it is not exactly `User-agent: *` plus `Disallow: /`.
6. **Recover managed services:** execute the controlled recovery above and require the complete repeated-tick window.
7. **Protected deploy:** push the one exact merged `main` SHA once. The accepted hook performs locked build/tests, encrypted backup, additive migrations, only `CustomerHomepageReleaseSeeder`, cache compilation and atomic activation.
8. **Live read-back:** verify active SHA, `/`, `/admin/login`, exact robots body, NB/RU homepage, poster-only Dekkhotell, four offers, twelve managed service cards, four partners, 700 NOK base hotel price, and preservation of the existing workshop offer.
9. **Operational read-back:** repeat DB/cache, three heartbeat observations, queue/outbox/media counts, NDI freshness, inbox receipts, disk/inodes and every disabled integration gate.

## Scoped recovery and rollback

Primary rollback target: `f70f358f06af384364973951eaab8d4fac2b2203`, but only if its revision, directory and basic HTTP/application checks remain fresh at the runtime gate. Retain `ee512d723e78a65a89bfdee4268c3a779f632b33`, `01a931d432b32a5a0633d1b508256b225387d429`, all fourteen existing snapshots and the new pre-migration snapshot through acceptance.

**Failure before database mutation:** the hook removes the incomplete candidate; current remains unchanged.

**Failure after migration/seed begins but before activation:** current still serves the previous revision and the failed candidate is retained. Keep integrations and managed services stopped, inspect the recorded release marker and run the candidate's guarded `RollbackCustomerHomepageReleaseSeeder`. Verify field journals, row publication states and the 700 -> 750 owned price reversal. Leave additive schema in place. Use the verified pre-migration database snapshot only if scoped recovery cannot restore consistency and a separate destructive recovery approval is granted.

**Failure after activation or live acceptance:** stop the timer and worker, keep integrations disabled, run the active candidate's guarded `RollbackCustomerHomepageReleaseSeeder`, verify operator-owned values are preserved, atomically return the application link to the verified primary rollback release, recycle the queue worker onto the old code, and repeat HTTP/health read-back. The nginx-owned robots response remains `Disallow: /` even though the rollback release contains an old static file.

Do not delete a failed candidate, fallback release, database snapshot or installer-created configuration backup until PM live acceptance is complete.

## Visual and external-material boundary

The native 1440 poster crop was reviewed directly, rather than only as a scaled full-page thumbnail. Its NB display face is present in the bundled build manifest and visibly rendered; the inspected headline has no clipping or glyph fallback. The compact line breaks are intentional to the card width. This does **not** claim final aesthetic approval of the entire live site: staging still runs the old revision, and final visual acceptance remains part of post-deploy read-back.

- Exact `003 muzik.mp4`: not supplied; the accepted poster remains the staging fallback and the original media requirement stays open.
- Snapchat: no confirmed username/URL; no link is published.
- September offer dates still need owner confirmation of year/recurrence before production.

## Gate recommendation

The corrected head may return to exact-SHA PM review after formatting, one final local test pass, commit, push and one final green CI run. No staging deployment, merge, unit start, nginx edit, queue action, integration call or real submission is authorised by this checkpoint.
