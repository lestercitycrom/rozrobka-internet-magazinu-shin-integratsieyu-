# EasyTire operational admin launch readiness

Status: implementation plan for PM review
Source date: 2026-09-03
Code baseline reviewed: `bacecd2797dfffca4c33a519872d9e6559332822`
Scope: service-request intake and handling in the existing EasyTire admin; documentation only

## 1. Decision-ready summary

The repository already contains a strong service-request foundation: public service forms, server validation, phone validation, idempotent submission, capacity-aware exact slots, a manual-window fallback when no schedule exists, one/two-vehicle seasonal requests, Dekkhotell choices, calculated seasonal quotes, oil-change requests, a persistent `service_requests` table, an admin list/detail view, four basic statuses, and optional outbox delivery.

It does **not** yet provide the operator workflow requested on 3 September. Staff cannot create a request in the admin, cannot edit client or service details, and cannot find requests by name, phone, date or operational filters. Email is still mandatory. A customer cannot choose “none of these slots fit” when a schedule exists. District and request-specific map access are absent. Existing roles grant every active non-customer role the same admin access, so `Operations` is not a server-enforced limited operator role.

The smallest independently useful release should keep `ServiceRequest` as the work item and customer snapshot, add manual admin intake/edit/search, and apply conservative operator permissions. It should **not** introduce a speculative customer registry, merge service requests into paid commerce orders, invent crew assignment, or add Notion.

## 2. Authoritative sources and interpretation

- Private package: `EasyTire_CRM_TZ_2026-09-03.zip`, especially `SPEC_FOR_CODEX.md`, `PROMPT_FOR_CODEX.md`, `REQUIREMENTS_TRACEABILITY.md`, `OPEN_QUESTIONS.md`, and `SOURCE_REVIEW.md`.
- `SPEC_FOR_CODEX.md` SHA-256: `049d3c0c50e3505526e02c2d950a322642e6f7e09ae71fdc498926178ae61ca6`.
- Recordings referenced by that package: `2026-09-03 11-51-38.mp4`, `12-25-25.mp4`, `12-25-45.mp4`, and `12-33-56.mp4`. The missing local `11-56-00` recording is represented by the package's redacted screenshots and source review; no disputed conclusion below depends only on an invented transcript.
- Requirement labels are preserved: `CONFIRMED`, `CURRENT`, `PROPOSED`, and `OPEN`. An open business rule is not silently promoted to implementation scope.

## 3. What the application does now

### Public request path

1. `routes/web.php:67-70,104-107` exposes Bokmål and private Russian-QA request, availability, submission and thank-you routes. Submission is rate-limited.
2. `StoreServiceRequest.php:14-59` validates the service-specific payload. The service is taken from the route/read model, not trusted from a customer-supplied arbitrary type. `CustomerPhone.php:10-49` validates Norwegian and international phone formats.
3. `ServiceRequestController.php:39-122` validates publication, normalises vehicles, calculates a seasonal quote, and runs persistence in one database transaction.
4. `ServiceRequestController.php:58-70` plus `2026_08_12_011000_create_booking_calendar_tables.php:45-49` make a repeated submission token idempotent.
5. `BookingCalendar.php:56-104` locks the schedule interval and checks capacity before an exact slot is stored. `BookingCalendar.php:62-75` accepts a preferred window only when the location has no configured schedule.
6. `ServiceRequestController.php:82-103` stores one `ServiceRequest` with reference, contact snapshot, service, fulfilment, booking values, vehicle data, Dekkhotell state and quote.
7. `ServiceRequestController.php:104-119` writes optional Notion and Telegram outbox messages in the same transaction. Delivery is disabled by default in `config/outbox.php:3-18`; disabled messages are deferred by `ProcessOutboxMessage.php:59-68`.
8. `AdminDashboardController.php:14-19` counts new requests and `ServiceRequestController.php:12-27` lists/opens them in the admin.

### Current admin path

- `routes/admin.php:71-73` exposes list, detail and one update action.
- `Admin/ServiceRequestController.php:12-35` only paginates newest requests and updates `status` plus `admin_note`.
- `admin/requests/index.blade.php:20-26` has no search or operational filters.
- `admin/requests/show.blade.php:5-12` displays the stored request, but only status/note are editable and no request-specific map link is rendered.
- `ServiceRequestManagementTest.php:12-25` covers list/detail/status/note; it does not cover manual creation, client/service editing, filtering or permission separation.

### Service requests are not commerce orders

`ServiceRequest` is a non-binding service enquiry/work item. `Modules/Orders/Models/Order.php:18-57` is a separate paid-commerce aggregate with totals, payment state, NDI state, legal snapshots and fulfilment. The commerce admin already has normalized filters (`AdminOrderFilters.php:9-59`, `Admin/OrderController.php:14-24`). The two aggregates may later appear in one dashboard, but must not be physically collapsed merely to obtain a “single list”.

## 4. Requirement-to-code trace

| ID | Result | Current evidence and exact gap |
|---|---|---|
| ARCH-001 | Partially implemented | New public service requests live in the main DB and admin (`ServiceRequestController.php:82-103`; admin routes `71-73`). Paid commerce orders remain correctly separate. Manual/admin-origin requests and a complete operational work view are missing. |
| ARCH-002 | Conflict, non-blocking while disabled | Daily work does not require Notion, and delivery defaults off. However every new request still creates `notion.service_request_created` outbox work (`ServiceRequestController.php:118`; `config/outbox.php:4`). The first admin release must not enable or depend on it; existing pending history must be preserved. Removal of future Notion publication should be a deliberate reviewed change, not a purge. |
| ARCH-003 | Partially implemented | Telegram has a fail-closed optional adapter and outbox event. Current payload/message includes reference/service/date but no admin URL, district or customer name (`HttpTelegramOrderDestination.php:20-32`). Chat, recipients and exact text remain open; Telegram is not a launch blocker. |
| MVP-001 | Partially implemented | Public intake, persistence, basic list/detail, statuses, validation and role names exist. Manual creation/edit, assignment, filtering, map and enforceable operator boundaries are missing. Assignment semantics remain open and are excluded from the first slice. |
| MVP-002 | Implemented foundation | Generic and seasonal forms already work without Notion. Oil change has a dedicated published service/form and add-ons (`StoreServiceRequest.php:26,50-51`; `ServiceRequestTest.php:52-79`; E2E `service-requests.spec.ts:142-154`). Existing smart slots do not have to be removed. |
| MVP-003 | Partially implemented | Oil change, battery, diagnostics, bulbs/wipers and tyre-related services exist. The mandatory launch service/add-on set is still open, so no new service list should be invented in this task. |
| FLOW-001 | Mostly implemented | Form -> validation -> idempotency/capacity transaction -> one DB row -> `new` status -> admin visibility is implemented. Optional Telegram lacks the requested admin link. Notion outbox creation is legacy dormant behavior, not a required second CRM. |
| FLOW-002 | Partially implemented | Exact, capacity-aware slots exist for configured seasonal schedules and are stored as concrete start/end times. Generic workshop forms such as oil change do not use the same exact-slot flow. Scope by service must remain governed by existing service contracts until customer decisions expand it. |
| FLOW-003 | Conflicts with current behavior | When a schedule exists, the public UI requires a slot and disables `preferred_window` (`create.blade.php:194-217`); the server rejects omission (`BookingCalendar.php:62-67`). The confirmed “none of these times fit” path is therefore absent. |
| FLOW-004 | Partially implemented | Mobile fulfilment requires an address (`StoreServiceRequest.php:55`; `create.blade.php:97-109,338-346`). District and request-level map/coordinates are missing. |
| CRM-001 | Partially implemented | All service requests appear in one paginated module with reference/client/service/status/date. Phone, booking mode/time, district, Dekkhotell and assignment are not presented as an operational table; commerce orders are a separate valid aggregate. |
| CRM-002 | Missing for service requests | There are no request filters or search. Reuse the proven normalization/query pattern from `AdminOrderFilters`; do not copy unconfirmed Notion views. |
| CRM-003 | Missing, later enhancement | No saved seasonal/additional-service views exist. Query-backed presets can be added later without splitting the underlying request table. |
| RBAC-001 | Conflicts with required boundary | `UserRole` defines Owner, Editor, Operations and Customer, but `EnsureActiveAdmin.php:13-20` grants identical access to every active non-customer role. `routes/admin.php:35-119` has no capability separation, so Operations can reach content, settings, integrations and payment actions. |

## 5. Field and validation trace

| Required field/rule | Result | Current representation / correction |
|---|---|---|
| Stable request number | Implemented | Unique `reference` (`2026_07_31_180000_create_service_requests_table.php:13`), generated at `ServiceRequestController.php:135-141`. |
| Client name | Implemented | Required server-side at `StoreServiceRequest.php:52`; stored as snapshot. |
| Phone | Implemented | Required plus `CustomerPhone` at `StoreServiceRequest.php:54`; browser hint/pattern and server test coverage exist. |
| Optional email | Conflict | Required in validation/UI (`StoreServiceRequest.php:53`; `create.blade.php:236-241,382-384`) and non-null DB column (`create_service_requests_table.php:24`). Must become nullable while preserving public validation when present. |
| Registration conditional on Dekkhotell | Conflict | Battery currently requires registration, but seasonal Dekkhotell/storage does not (`StoreServiceRequest.php:37,41-47`). Add server-side per-vehicle conditional validation when storage is `new` or `renew`; retain battery rule. |
| Wheel diameter/size | Implemented for existing contracts | `tire_size` and `rim_band` are stored; mounting/seasonal rules remain service-specific. Do not make them globally mandatory. |
| Service/form type | Implemented | `service_slug`, `service_variant` and `fulfilment`; route/read model prevents arbitrary public service selection. Manual admin form must select an existing enabled service. |
| Wants Dekkhotell | Implemented | Boolean `dekkhotell`; seasonal `storage_plan` supports `none/new/renew`. |
| Already stored at EasyTire | Partially implemented | `storage_plan=renew` is the closest current meaning. Preserve the code, but do not assert a customer-history link until the Dekkhotell key is defined. |
| Booking mode | Partially implemented | Inferable from `booking_slot_key` versus `preferred_window`, but not explicit and current scheduling makes the two modes unavailable as a choice. Add an explicit validated mode only with the booking-contract slice. |
| Preferred date | Partially implemented | Required for seasonal, mounting and battery, optional for other generic services (`StoreServiceRequest.php:32`). Keep existing service contracts; admin intake may require an operational date for selected launch services without changing every public form. |
| Exact time | Implemented for exact slots | Persisted `booking_starts_at`/`booking_ends_at` and capacity key (`booking migration:37-42`). |
| Preferred from/to period | Partially implemented | One free-text `preferred_window` exists. First release may keep it as bounded text; structured from/to remains a later scheduling decision. |
| Mobile address | Implemented | Required when `fulfilment=mobile`; server enforced. |
| District | Missing | Add optional bounded text in the first field-completeness slice. Do not infer it automatically from address. |
| Customer comment | Implemented | `note`, max 2000; some diagnostic contracts correctly require it. |
| Vehicle count >= 1 | Partially implemented | Seasonal flow supports exactly one or two and stores per-vehicle JSON. This satisfies the known screen flow but is not an unlimited vehicle model. Preserve max two until the open multi-vehicle rule is resolved. |
| Per-vehicle structure | Implemented for max two | `vehicles` JSON contains vehicle, registration, size, type, rim band and storage plan (`ServiceRequestController.php:154-181`). |
| Additional services list | Implemented, catalogue is narrow | Validated list for supported services; exact mandatory launch list remains open. |
| Operator/resource assignment | Missing and open | Do not add `Car 1`/`Car 2`, crew or employee assignment in the first slice. |
| Map link / coordinates | Missing for requests | Contact page map is unrelated. First release can safely render an address-search URL to the configured map provider without geocoding; an exact coordinate pin requires the open provider/geocoding decision. |
| Status | Implemented minimum, lifecycle open | `new`, `contacted`, `closed`, `cancelled` (`ServiceRequestStatus.php:5-10`). Keep this minimum; do not invent more states. |
| Source | Partially implemented | Form source is inferable from service slug/locale, but manual versus public is not stored. Add a system-owned source code, never a free client input. |
| Created/updated timestamps | Implemented | Standard timestamps exist. Actor/audit history does not. |
| Consent/manual phone intake | Conflict exposed by operator scenario | `consent_at` is non-null and public consent is required. Staff cannot truthfully tick the client's web checkbox. Manual records need nullable public-consent evidence plus a system source/creator; this is not permission to bypass privacy duties. |

## 6. Acceptance checklist status

| Source acceptance item | Current status |
|---|---|
| Every enabled public form creates one main-DB request | Mostly covered by feature/E2E tests; should receive a parameterized launch-service contract test. |
| Retry does not create duplicates | Implemented and tested (`BookingCalendarTest.php:153-165`). |
| Request appears as New and opens in admin | Implemented; dashboard counts New. There is no dedicated filtered “New” view. |
| All submitted data and form source retained | Data mostly retained; explicit source/district missing. |
| Email may be empty | Not implemented. |
| Dekkhotell conditional fields | Partially implemented; registration conditional is missing. |
| Workshop exact slot | Implemented for configured seasonal flows. |
| Preferred window when offered slots do not fit | Not implemented. |
| Mobile address and working map link | Address yes; request map link no. |
| Find by date/service/district/assignment | Not implemented; assignment/district semantics partly open. |
| Operator cannot manage users/settings/secrets | Not implemented. |
| Notion not required | Operationally true while disabled, but dormant outbox production remains. |
| Telegram contains admin link when enabled | Not implemented; integration decision remains open. |
| Automated coverage | Strong public baseline; missing new admin/RBAC scenarios. |

## 7. Minimum data-model decision

### First release: no independent client registry

Keep name, phone, optional email, address and vehicles as a snapshot on each `ServiceRequest`. This directly supports the confirmed scenario: a caller becomes one work request that staff can save, reopen, search and handle. It also avoids prematurely defining deduplication, household/company relationships, a “client without a request”, cross-order privacy retention, and Dekkhotell identity.

This is **not** a decision that EasyTire never needs a full client registry. A future `customers` / `customer_vehicles` model should be proposed only after the customer agrees on identity matching, merge rules, history, retention, multi-vehicle ownership and whether a client may exist independently of a request. Paid `Order` customer snapshots must also remain historical even if a future client record changes.

## 8. Proposed implementation slices

Each slice is separately reviewable. The first useful staging result requires slices A-C together; D and E complete confirmed scheduling/field gaps without blocking initial manual intake.

### Slice A — compatible request schema and shared creation action

Outcome: public and staff-created requests can use one safe persistence path without false consent or mandatory email.

- Add an additive migration for nullable `email` and `consent_at`; system-owned `source` (`public` / `admin_manual`); optional `created_by_user_id`; and indexes needed by the request list. Do not add assignment tables.
- Extract the transaction in `Storefront/ServiceRequestController::store` into a domain action (for example `CreateServiceRequest`) used by both public and admin controllers. Preserve UUID idempotency, interval locking/capacity, quote calculation, status `new`, and historical snapshots.
- Public validation continues to require consent, but email becomes `nullable|email`. Manual admin intake stores `source=admin_manual`, actor, and no fabricated web consent.
- Stop creating new Notion service-request outbox messages as part of this deliberately Notion-free operational path; preserve all existing outbox rows. Telegram publication remains feature-gated and must not block DB commit.
- Files: new migration; `ServiceRequest` model; `StoreServiceRequest`; storefront controller; new action/request DTO; outbox-focused tests.
- Tests: null email; public consent still required; manual source cannot be spoofed; idempotent double submit; capacity race; quote/vehicles unchanged; disabled external destinations make zero HTTP calls.
- Retained behavior: public references, existing snapshots, quote calculation, max-two vehicles, slot capacity and all paid-order/payment boundaries remain unchanged.
- Prerequisites: PM acceptance of nullable manual-consent semantics and of stopping future service-request Notion publication while retaining history.
- Effort: 4-6 engineering hours.

Rollback: code may be reverted while the additive columns remain. Do not run a destructive down migration after nullable-email/manual rows exist; export/reconcile them first.

### Slice B — server-enforced operator boundary

Outcome: an Operations user can handle client requests but cannot use editor/settings/integration/payment-admin surfaces.

- Add an explicit role/capability middleware or policies. Proposed conservative matrix for PM acceptance: Owner all admin routes; Operations dashboard, service requests, booking calendar and non-secret operational order handling; Editor content/media only; Customer none.
- Owner-only: storefront/system settings, pricing/integration-sensitive operations, imports/publication controls, and payment capture/refund/reconcile/void. Do not add user management because none currently exists.
- Explicitly deny Editor access to request/contact details. Add direct HTTP authorization tests for every route family; hiding navigation alone is insufficient.
- Files: identity enum/capability service or middleware, bootstrap alias, grouped admin routes, admin navigation, authorization tests.
- Retained behavior: the existing four role values and customer denial remain; no user provisioning or secret-management feature is added.
- Prerequisites: PM acceptance of the conservative route matrix; any wider financial permission for Operations requires a separate business decision.
- Effort: 3-5 hours.

Rollback: revert route policy code; no data change.

### Slice C — manual admin intake, edit and essential search

Outcome: staff can receive a call, enter name/phone/service details, save, reopen, edit, find by name/phone/reference/date, and handle the same work item as a public request.

- Add `GET/POST /admin/service-requests/create`, a full edit action, and dedicated admin form requests. Reuse current service contracts and the shared creation action; do not route staff through the public form.
- First form supports name, phone, optional email, enabled service, workshop/mobile, preferred date/window or existing exact-slot selection where currently supported, address, note, Dekkhotell/storage, max-two structured vehicles and existing add-ons.
- Add safe normalized query filters: `q` (reference/name/email/phone), from/to date, status, service and fulfilment. Preserve query strings through pagination. “New” is a normal status filter, not a second data store.
- Edit contact/service fields and status/note with server validation. Do not silently reassign an exact slot; until Slice D, a booked exact time is read-only and must be changed through the controlled booking action.
- Add an external map search link for non-empty mobile addresses using the configured/approved map host; label it as address lookup, not verified coordinates.
- Files: admin routes/controller/form requests; filter value object; shared admin form partial; request index/show/edit views; NB/RU translations; feature and Playwright tests.
- Retained behavior: public requests and manual requests share one table and handling UI; commerce orders and their payment/NDI histories remain separate.
- Prerequisites: Slices A-B; a confirmed enabled launch-service list; no assignment or expanded status decision is required.
- Effort: 6-9 hours.

Rollback: revert code while preserving rows tagged `admin_manual`; the accepted old admin can still display them. Do not delete manually entered requests.

### Slice D — exact-slot versus preferred-window contract

Outcome: customers and staff can explicitly choose a suitable exact slot or “these times do not fit” without double-booking.

- Add a validated booking mode (`exact_slot`, `preferred_window`; `manual` only if PM explicitly accepts it). Backfill/infer historical rows without changing stored times.
- Update seasonal UI so a configured schedule does not hide the preferred-window alternative.
- Update `BookingCalendar` to enforce mutual exclusivity and allow preferred-window mode even when intervals exist. Exact mode still locks/checks capacity.
- Add an atomic reschedule action that excludes the current request while claiming a replacement slot. Preserve capacity if validation fails.
- Tests: both modes, forged slot, full slot, repeated token, concurrent capacity, changing/cancelling a slot, and preferred-window choice with a configured calendar.
- Retained behavior: existing slot keys/times, interval capacity, blackouts and cancellation release semantics remain valid for historical requests.
- Prerequisites: PM acceptance of the explicit mode names and whether a distinct staff-only `manual` mode is needed.
- Effort: 5-8 hours.

Rollback: keep additive mode column; revert behavior only after confirming new preferred-window rows remain readable by the old admin.

### Slice E — remaining confirmed request fields

Outcome: operational rows expose district and correct Dekkhotell registration rules without inventing route automation or a storage registry.

- Add optional bounded `district` text to public/admin mobile flow and list filters. No geocoding or automatic district derivation.
- Require a registration number for every vehicle whose storage plan is `new` or `renew`; keep existing battery contract. Keep max two vehicles.
- Improve list columns for phone, booking time/window, district and Dekkhotell. Assignment filter remains absent until assignment semantics are approved.
- Retained behavior: existing service-specific size/type rules and stored vehicle JSON are not globally tightened or rewritten.
- Prerequisites: confirm that `storage_plan=renew` is the intended “already stored” meaning; district remains manually entered until a later provider decision.
- Effort: 3-5 hours.

Rollback: additive field may remain; validation/UI code can be reverted without deleting data.

### Later, decision-dependent work

- Telegram message with an admin link: only after chat/recipient/event/text approval; 2-4 hours plus a separate staging integration gate.
- Assignment/crew/resources and Car 1/Car 2: blocked on meaning and scheduling resources.
- Structured preferred time ranges, route optimization, geocoding/exact coordinates, Dekkhotell inventory, full customer registry, attachments and expanded statuses: separate requirements.
- Saved seasonal/additional-service views and a unified dashboard across `ServiceRequest` and `Order`: presentation/read-model work after the operational slice; do not merge the aggregates.

## 9. Test and evidence plan

### Laravel feature/domain coverage

- Public forms: one table-driven test for every enabled launch service; optional email; phone; mobile address; Dekkhotell registration; service-owned source; max-two vehicles; add-ons; idempotency.
- Booking: exact/preferred mutual exclusion, full/forged slots, transactional reschedule, cancellation capacity, manual-window behavior.
- Admin: create, validation, reopen, edit, status/note, search by name/normalized phone/reference, date/service/fulfilment/status/district filters, pagination retention and localized NB/RU labels.
- RBAC: Owner/Operations/Editor/Customer allow/deny matrix against actual endpoints, including settings, integrations and payment mutations.
- External safety: Notion/Telegram disabled produces no HTTP; enabled Telegram remains fake-only in CI.

### Playwright

- Admin Operations user: create a synthetic caller request, reopen it, edit it, filter by phone/date, and verify the map link target without navigating to the provider.
- Public buyer: submit an enabled request and verify it appears in the same admin list; repeat with the same submission token and confirm one row.
- Exact-slot and preferred-window alternatives at 1440 and 390; one 320 overflow/accessibility smoke.
- Permission smoke: Editor cannot see or open request/contact pages; Operations cannot open owner-only settings/payment mutations.
- No real customer data, payment, NDI order, Telegram, Notion or mail in local/CI tests.

## 10. Launch gates and real blockers

### Does not block first manual intake

- final expanded status lifecycle;
- meaning of Car 1/Car 2 and crew assignment;
- automatic district derivation;
- geocoding/exact coordinates (an honest address-search link is sufficient initially);
- full client registry;
- Telegram activation;
- Notion history migration;
- attachments;
- expanded service/add-on catalogue, provided the customer selects the enabled launch services.

### Must be resolved before real staff/customer entry

1. PM exact-head acceptance of the implementation PR(s) and explicit staging/customer-entry release authorization.
2. Server-enforced operator boundary and separate staff accounts; a shared password is not an engineering substitute for accountability.
3. Backup/recovery verified for the target database; no test resets or destructive seed on customer data.
4. Optional email, manual-consent semantics and source field deployed before phone intake.
5. Confirmed list of enabled launch services. Existing oil-change flow is usable and must be included if it remains published.
6. Queue/scheduler runtime state resolved under Issue #44. Request persistence itself must not depend on optional media, Notion or Telegram processing.
7. NB copy/customer privacy notice reviewed for the manual intake process. This plan records technical handling, not a claim of independent Norwegian legal certification.

## 11. By-morning feasibility

- **A demonstrable staging operator workflow (Slices A-C): conditionally feasible in one focused 13-20 hour engineering/review cycle**, assuming no migration incompatibility and prompt PM review. It is not credible to promise implementation, full RBAC regression, CI, independent review, deployment and customer-entry acceptance in only a few remaining overnight hours.
- **A production-ready fulfilment of all confirmed CRM acceptance items: not a by-morning task.** Slices D-E add approximately 8-13 hours before review/deployment, and external Telegram/assignment/geocoding decisions remain separate.
- The safest morning target is a PM-reviewed staging build where an Operations user can create/edit/search real-shaped synthetic requests with external integrations disabled. Real client entry begins only after the gates above, not merely because the form renders.

## 12. Recommended PM sequence

1. Accept or amend the minimum data-model decision and conservative role matrix.
2. Authorise one implementation Issue for Slices A-C, with one branch/PR and no external runtime work.
3. Review exact-head tests and migration rollback before any staging deployment.
4. Run a staging synthetic admin/public walkthrough; then explicitly authorise customer entry if access, backup and runtime health are accepted.
5. Schedule Slice D, then E. Create Telegram/assignment/geocoding tasks only after their open business decisions are recorded.

Planning completion does not claim that the CRM is delivered. Runtime authorization under this document is **none**.
