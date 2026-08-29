# Issue #19 — redacted NDI freshness diagnosis

Checked on staging on 2026-08-29 under the read-only authorisation in Issue #19. This record deliberately excludes credentials, supplier filenames, workbook contents, filesystem paths, customer data and provider payloads.

## Result

`EXTERNAL_BLOCKER`: no new NDI supplier dataset has reached the configured staging inbox since 2026-08-12. The application, scheduler and import path are ready, but there is no current supplier input to validate or publish.

The real NDI catalogue remains correctly hidden by the existing freshness gate. No timestamp, stock, price, publication flag or supplier file was changed during diagnosis.

## Reproducible redacted facts

| Boundary | Read-only result |
|---|---|
| Expected inputs | One stable workbook for each supported role: `stock`, `tires`, `wheels`. |
| Inbox state | All three roles exist, are readable, stable and schema-recognised, but all three are the unchanged 2026-08-12 inputs. Their exact receipt fingerprints remain `succeeded`. |
| Supplier delivery | The restricted transfer account is active, chrooted and able to write the inbox. No transfer-account log event was recorded after the last dataset date. |
| Application access | The scheduler/application user can read the inbox. It does not require write access to import files. |
| Scheduler | The systemd timer is active and invokes Laravel every minute. `ndi:inbox:sync` is registered every ten minutes; a sampled due tick completed successfully. |
| Queue | The supervised worker is active; heartbeat is current; failed jobs are `0`. |
| Imports | The last `stock`, `tires` and `wheels` runs all succeeded on 2026-08-12 with parser `1.1.0`, no row rejections and no retryable/failed inbox receipt. No later import exists. |
| Live NDI data | 2,304 live supplier SKUs and 1,573 published products remain in normalized storage. 918 live SKUs have positive historical stock, but `0` have stock inside the configured storefront freshness window. |
| Sandbox isolation | One clearly marked test-fixture SKU is separately refreshed and remains the only SKU with fresh positive stock. It does not make the live NDI feed fresh. |
| Health | Database, cache, scheduler, queue, disk and media failure checks are healthy. NDI freshness is red: the last successful supplier import is about 17.5 days old against a 24-hour health window. |
| Media | 967 known supplier media sources are downloaded with no pending or failed rows. Representative real-media acceptance must wait for a current supplier dataset. |
| Capacity | Disk health is green after Issue #18. Capacity did not interrupt the last transfer or import and is not the current cause. |
| Staging code | The accepted staging application tree matches the current accepted `main` application tree; no parser/configuration code drift was found. |
| Partial upload safety | The importer ignores hidden, symlinked, non-XLSX, empty and not-yet-stable files, then re-checks inode/size/timestamps after hashing. No current supplier upload exists from which to observe the provider's temporary-name/atomic-rename convention. |
| Archive/quarantine | No dedicated runtime archive/quarantine directory exists. The runbook requires future rotation, but the inbox currently contains only the three unchanged successful files; this is not the cause of stale data or disk pressure. |

## Root-cause classification

| Candidate | Classification |
|---|---|
| Supplier file not delivered | **Confirmed root cause.** No current `stock`, `tires` or `wheels` file has arrived since 2026-08-12. |
| SFTP/drop permission | Ruled out by current account, chroot, transfer-write and application-read checks. |
| Atomic upload | Not observable without a new provider delivery; local partial-file protections are present. |
| Scheduler/cron | Ruled out. Timer and due NDI tick are healthy. |
| Worker/queue | Ruled out. Worker and heartbeat are healthy; failed jobs are zero. |
| Validation/parser drift | Ruled out for the last delivered set: all roles match parser `1.1.0`, all runs succeeded and no rejections exist. A future file can only be judged after delivery. |
| Freshness timestamp bug | Ruled out. Health uses the last successful import, while catalogue publication independently rejects stale live stock. Hourly sandbox timestamps remain isolated. |
| Archive/quarantine/rotation | Operational follow-up, not the current cause. There is no growing inbox and no rejected file. |
| Staging/main configuration mismatch | Ruled out for the accepted application code and required enabled/disabled boundaries. |
| Infrastructure capacity | Ruled out by green disk health and healthy runtime services. |

## Provider action required

NDI must deliver a new complete daily dataset containing all three supported roles to the already supplied restricted staging SFTP inbox, using stable final `.xlsx` names and preferably a temporary upload name followed by an atomic rename. The delivery window/cadence should be confirmed if daily delivery is not yet enabled.

After the three new stable files exist, the engineer will stop again for an exact-head PM review and the separately required `STAGING_INTEGRATION_APPROVED` before manually initiating any bounded sync/import/publication/media verification.

## Stop record

Diagnosis stopped at the Issue #19 external-blocker condition. No inbox sync, import, publication, media sync, NDI commercial C1, payment, notification, Notion, Telegram or email mutation was manually initiated.
