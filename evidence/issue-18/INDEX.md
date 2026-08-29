# Issue #18 — staging disk headroom evidence

Inventory date: 2026-08-29

Inventory mode: read-only. No file, release, backup, log, database row, queue item or supplier artifact was deleted or changed while producing this record.

The figures below are deliberately aggregated. No credentials, customer PII, supplier filenames, payload contents or secret-bearing paths are included.

## Filesystem and health baseline

| Check | Read-back |
|---|---:|
| Root filesystem | About 40 GB total; less than 2 GB available; 96% used |
| Inodes used | 41% |
| Application warning threshold | Default 5 GiB; no runtime override |
| Application health | database, queue, cache, scheduler and worker healthy; failed jobs 0; disk warning; NDI freshness remains a separate Issue #19 blocker |

This is a capacity problem, not an inode problem. The same root filesystem is shared by EasyTire and other staging applications.

## Inventory and protection decisions

| Category | Read-only evidence | Maintenance disposition |
|---|---|---|
| EasyTire releases | 5 releases, about 1.23 GB total | Retain the active release plus one immediate rollback; the three remaining releases are candidates (about 0.74 GB). The exact retained and candidate sets are locked by the reviewed plan seal, name manifest and direct-child metadata manifest. |
| Shared test-site releases | 87 releases, about 8.31 GB total | Retain the active release plus the two newest rollbacks; the remaining 84 direct-child releases are candidates (about 8.02 GB). The exact retained and candidate sets are locked by the reviewed plan seal, name manifest and direct-child metadata manifest. |
| EasyTire temporary audit copies | Two isolated extracted audit directories below the operating-system temporary directory, about 0.53 GB | Candidates. Their two literal basenames and metadata are included in the reviewed plan seal; either candidate changing or disappearing stops preflight. |
| Release-local dependencies | Each EasyTire release contains about 104–105 MB `vendor` and 130–131 MB `node_modules`, plus build/cache/test files | Reclaimed only as part of deletion of the three obsolete whole releases. Active and rollback releases are not modified. |
| EasyTire managed database backups | Configured 14-file retention is present; latest completed backup corresponds to the accepted staging release | Protected in full. No backup pruning is proposed. |
| Other-project backup set | Multi-gigabyte protected set | Protected in full. Its restore ownership/retention is outside this task and no file is a candidate. |
| Restore evidence | Last repository-backed staging restore evidence is the isolated 2026-07-31 incident recovery; no newer independent restore rehearsal was found | Protect all backups. Do not use database restore as the cleanup rollback path. Release rollback remains available through retained releases. |
| EasyTire logs | Application logs are under 2 MB; the system journal is under 1 GB | Protected. No log truncation or journal vacuum is proposed. |
| Package/browser/OS caches | About 1.24 GB in aggregate | Not required to reach the target and left untouched. |
| NDI inbox | Current input is sub-megabyte | Protected as current supplier input. No filenames or contents recorded. |
| NDI raw artifact store | Current provenance store is sub-megabyte | Protected as import provenance. No archive/quarantine directories exist in the inspected deployment. |
| EasyTire database | Under 100 MB | Protected; read-only size evidence only. |
| Server MySQL / Redis | Multi-gigabyte database storage plus a small Redis store | Protected; never cleanup candidates. |
| Database queue/cache state | No queued or failed jobs; cache is operational | Read-only evidence only; no row deletion proposed. |

## Proposed bounded maintenance

The reviewed script [`../../scripts/issue-18-staging-headroom.sh`](../../scripts/issue-18-staging-headroom.sh) is the exact command surface. Exact server roots, retained release identifiers and temporary basenames remain in an ignored local execution sheet and are supplied as environment values; they are never committed or echoed in public evidence. A committed SHA-256 seal binds that private tuple to the inspected staging host and to the reviewed manifests, so substituting another host, root, release identifier, temporary basename, owner, group or mode stops preflight.

It has two modes:

```bash
# Required ISSUE18_* values come from the ignored execution sheet.
bash scripts/issue-18-staging-headroom.sh --inspect
bash scripts/issue-18-staging-headroom.sh --execute STAGING_MAINTENANCE_APPROVED ISSUE-18
```

`--inspect` was repeated through standard input on the staging host after the fail-closed hardening. It exited successfully and confirmed three obsolete EasyTire releases, 84 obsolete shared test-site releases, two temporary audit directories and 9,287,729,152 bytes expected reclaim while preserving the required five active/rollback releases. A separate read-only negative test changed one supplied basename and was rejected at the plan-seal check before candidate inventory or any mutation.

The execute mode is blocked until the PM approves the exact reviewed branch SHA and all three candidate categories. Before deleting anything, the script:

1. verifies the staging host marker and the deterministic seal over both roots, all active/rollback identifiers, the temporary root and two basenames, expected ownership/modes, and all reviewed manifests;
2. validates every direct child by exact count and name manifest, then validates metadata manifests covering name, allocated size, modification time, UID, GID and mode;
3. validates both active symlink targets and verifies every retained or candidate release is a real direct-child directory on the expected filesystem, not a symlink or mount;
4. requires the exact expected UID, GID and mode on release roots, retained releases, deletion candidates and temporary candidates; release trees may not be group/world writable, and the temporary root must retain its sticky bit;
5. derives the deletion set only as the exact reviewed direct-child manifest minus the sealed active/rollback identifiers;
6. requires two explicit approval tokens.

Any mismatch in the sealed private tuple, host marker, direct-child names, covered metadata, symlink target, path boundary, filesystem, owner, group, mode or derived candidate count stops the whole preflight before deletion. Removal is then limited to individually validated allowlisted directories. There are no broad-root or unreviewed-glob deletion commands.

Expected post-maintenance available space is approximately 11 GB, comfortably above the 5 GiB warning threshold. Actual before/after values must be recorded at execution time.

## Recovery and post-maintenance read-back

- EasyTire rollback target: the immediate pre-current release recorded in the ignored execution sheet.
- Shared test-site rollback targets: the two newest pre-current releases recorded in the ignored execution sheet.
- The active symlinks are never changed by the script. If either target differs before execution, the script stops.
- Deleted obsolete releases and temporary audit copies are reproducible artifacts; they are not restored from the protected database backups.
- After execution, verify filesystem bytes/inodes, both active symlinks and retained rollbacks, EasyTire health, homepage, admin login, queue worker, scheduler and failed jobs. The expected NDI freshness warning remains tracked separately in Issue #19 and must not be hidden.

## Approval boundary

No maintenance has been performed. Execution requires the exact `STAGING_MAINTENANCE_APPROVED` Issue comment described in Issue #18, tied to the current reviewed head SHA and explicitly approving:

1. three obsolete EasyTire releases;
2. 84 obsolete history-test releases from the locked 87-entry manifest while retaining the named three;
3. the two named EasyTire temporary audit directories.
