# EasyTire operational admin launch readiness

Status: Issue #59 implementation candidate; local acceptance in progress, runtime authorization **none**

Updated: 2026-09-14

Base reviewed: `2bc5d6321406b3729c43dedb6227f3cf77e3accc`

Candidate branch: `codex/issue-59-client-crm-forms`

## 1. Current decision-ready summary

The Issue #59 candidate closes the confirmed 3 September CRM gaps without merging service requests into paid commerce orders and without depending on Notion, Telegram, Dintero or NDI.

It adds an independent client registry, explicit snapshot-safe links between clients and service requests, manual client-to-request and request-to-client workflows, district and structured preferred-period data, per-vehicle Dekkhotell registration rules, and one filterable operational request list. Owner and Operations are the only roles allowed to use these CRM screens.

No customer login, automatic client merge, Dekkhotell inventory, geocoding, route optimization or automatic van allocation is introduced. Existing exact slots retain their capacity-safe claim path. A preferred period never claims an exact slot.

The only open business decision inside this scope is the meaning of Car 1 / Car 2. Two mobile vans are confirmed, but names, zones, shifts and allocation rules are not. The candidate therefore does not claim or infer an assigned van while the PM question is unanswered.

## 2. Authoritative sources and interpretation

- Private customer package `EasyTire_CRM_TZ_2026-09-03.zip`, including `SPEC_FOR_CODEX.md`, `PROMPT_FOR_CODEX.md`, `REQUIREMENTS_TRACEABILITY.md`, `OPEN_QUESTIONS.md` and `SOURCE_REVIEW.md`.
- `docs/management/CURRENT_TZ_2026-08-22.md`, especially the two-mobile-van section.
- `docs/management/reports/TZ_REQUIREMENTS_AUDIT_2026-08-12.md`, especially the open choice between district allocation and filling the first van before the second.
- Existing public and admin service-request contracts, pricing rules, exact-slot capacity behavior and Owner/Operations capability boundary.
- GitHub Issue #59 is the bounded implementation decision for the 3 September client/CRM correction.

Confirmed requirements are implemented. Open business semantics remain explicit and are not converted into invented automation.

## 3. Candidate implementation

### Client registry

- `clients` stores required name and phone, normalized phone, optional email, address, district and internal note, creator and timestamps.
- Owner and Operations can list, search, create, open and edit clients. There is deliberately no client deletion, login or messaging surface in this slice.
- Search covers name, email and normalized phone.
- A client can exist without a request.

### Snapshot-safe request links

- `service_requests.client_id` is an optional explicit foreign key with `nullOnDelete` behavior.
- Creating a request from a client prefills a new request snapshot and links the request.
- Creating a client from an existing request prefills the client and links only when the operator explicitly submits that action.
- Editing a client never rewrites historical request contact data. Editing a request never rewrites the client. No automatic matching or merge by phone/email occurs.

### District, Dekkhotell and booking period

- Optional bounded `district` is available in relevant public/admin mobile forms, request cards and combined filters. It is entered manually; no coordinate or geocoding claim is made.
- Seasonal storage keeps the existing `none`, `new`, `renew` contract. Registration is required separately for every vehicle whose storage selection is `new` or `renew`.
- Clearing every vehicle's storage selection clears the derived Dekkhotell flag. No storage inventory or customer-history assertion is created.
- Customers and operators can choose a structured preferred period: date, start and end, with end strictly after start.
- A preferred period stores UTC start/end plus a readable local-time label and does not reserve capacity. Existing exact slots still use the locked capacity claim. Historical free-text windows remain readable and are not guessed into structured timestamps.

### Operational request list

- One paginated request table exposes client/contact, phone, service, fulfilment, exact time or preferred period, district, Dekkhotell, status, source, creator and creation time.
- Search and filters combine and persist through pagination: normalized phone/name/reference/email, date or range, service, fulfilment, status, district and Dekkhotell.
- Query-backed views are available for all, new, workshop, mobile and additional-service work. The additional view includes oil-change requests and requests with stored add-ons.
- Commerce orders remain a separate paid aggregate with their own payment and NDI lifecycle.

### Permissions and external safety

- Client and service-request CRM routes are restricted server-side to Owner and Operations. Hiding navigation is not the security boundary.
- The slice makes no Dintero authorization/capture, NDI order, Telegram, Notion or mail call.
- Test data is synthetic. Staging and server runtime are unchanged until exact-head PM acceptance.

## 4. Requirement status

| Requirement | Candidate status | Evidence |
|---|---|---|
| Independent client CRUD without a request | Implemented | `Client`, `ClientController`, admin client views, `ClientManagementTest` |
| Required name/phone; optional email/address/district/note | Implemented | `UpsertClient`, additive client schema |
| Explicit client/request linking | Implemented | client and request create flows; link tests |
| Client/request snapshot independence | Implemented | transaction behavior and regression tests |
| District in relevant public/admin flows | Implemented | request validation, forms, list/filter/card |
| Dekkhotell new/renew and per-vehicle registration | Implemented | public/admin validation, dynamic browser requirement, feature tests |
| Exact slot remains capacity safe | Retained | existing `BookingCalendar::claim` path and regressions |
| Preferred date/start/end period | Implemented | additive timestamps, atomic validation and UI flow |
| Historical free text is not guessed | Implemented | nullable migration plus real-schema upgrade regression |
| Combined operational filters and views | Implemented | admin controller/list and CRM filter tests |
| Owner/Operations only | Implemented | route middleware, form authorization and forged-ID/RBAC tests |
| Car 1 / Car 2 assignment | Decision required | two vans confirmed; resource semantics and allocation remain open |
| Automatic routing, storage inventory, client merge | Out of scope | deliberately absent |

## 5. Data and migration safety

- The migration is additive: it creates `clients` and adds nullable link, district and period columns to `service_requests`.
- Legacy request rows keep `client_id`, district and structured-period fields null. Existing `preferred_window` values remain unchanged.
- Code rollback must leave the additive schema and collected data in place. The migration's `down()` deliberately does not destroy CRM data.
- A real isolated MySQL upgrade regression builds the pre-Issue-59 schema, inserts a legacy request, applies the candidate migration and verifies both legacy preservation and new linked-period behavior.
- Before staging use: take the normal database backup, run migrations once, verify migration status, then perform a synthetic Owner/Operations walkthrough.

## 6. Verification matrix

### Automated server coverage

- Client create/search/edit without a request.
- Client-to-request and request-to-client explicit links.
- Snapshot independence and forged client ID rejection.
- Structured period validation and contact-only legacy edit preservation.
- Storage registration per vehicle and explicit storage removal.
- Combined views/filters with pagination query retention.
- Existing service-request intake, slot capacity, quote, status and booking regressions.
- Real MySQL additive-upgrade regression in CI.

### Playwright coverage

- Owner/Operations client workflow at 1440, 390 and 320 widths.
- NB and RU admin labels across the viewport matrix.
- Public seasonal preferred-period, district and Dekkhotell flow in NB and RU.
- Dynamic per-vehicle registration requirement, successful submit and global overflow checks.
- No real customer data or external side effects.

## 7. Open decision: mobile van assignment

Exact PM question:

> Should the first implementation add only a nullable manual resource assignment with stable values `van_1` / `van_2` and localized operator labels, with no automatic routing and no implication that these are the customer's first/second vehicles; or must assignment remain open until the customer supplies resource names, zones and shifts?

Until answered, request rows remain unassigned. This does not block the confirmed client, period, district, Dekkhotell and worklist features.

## 8. Staging launch gates

1. Exact-head CI must pass, including isolated MySQL migration and Playwright viewport/language evidence.
2. PM must review the exact PR head and decide merge and staging deployment.
3. Normal database backup/recovery and queue health must be confirmed before staging migration.
4. Use only synthetic contacts until the new Owner/Operations access and privacy handling are accepted.
5. Run one public preferred-period request and one Operations-created linked request on staging; verify list filters, snapshots and no unexpected external delivery.
6. Do not enable Notion/Telegram or make Dintero/NDI side effects part of this CRM acceptance.

## 9. Rollback

- Revert the application release to the preceding reviewed commit.
- Keep `clients` and the new nullable service-request columns in place; they are backwards-compatible and contain potentially valuable records.
- Do not drop the table or columns as part of a routine code rollback.
- If migration application itself fails before release switching, leave the previous application active and inspect the migration error on the isolated/staging copy before any retry.

This document describes the Issue #59 candidate. It does not claim staging deployment or customer-data readiness before PM acceptance and the launch gates above.
