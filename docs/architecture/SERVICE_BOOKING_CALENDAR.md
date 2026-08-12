# Service booking calendar

## Purpose and guardrail

The seasonal request flow can expose real available time slots without claiming business hours that EasyTire has not approved. Production seeders create no schedule. Until an operations administrator adds an active interval for a location, the form asks for a preferred date and free-text window and explicitly says that EasyTire will confirm it manually.

The deterministic `E2eBookingCalendarSeeder` is restricted to `local` and `testing`; it must never be called by `DatabaseSeeder` or a production deployment.

## Configuration

- Local calendar timezone: `SERVICE_BOOKING_TIMEZONE` (default `Europe/Oslo`).
- Admin: `/admin/booking-calendar`.
- Public JSON endpoint: `/booking/availability?date=YYYY-MM-DD&fulfilment=workshop|mobile` (and `/ru/booking/availability` for Russian QA).

Operations configures:

1. recurring weekly intervals: ISO weekday, location (`workshop`, `mobile`, or `both`), local start/end, minutes per slot, capacity per slot, active state;
2. blackout ranges: location, local start/end, optional internal reason, active state.

Overlapping active weekly intervals for the same effective location and weekday are rejected. Blackouts may overlap and suppress any slot they intersect.

## Slot contract

The browser submits a stable opaque key (`b1-{intervalId}-{YYYYMMDD}-{HHMM}`). The server never trusts the key alone. It reloads and locks the interval row, verifies weekday/location/date/time, regenerates the slots, checks blackouts and counts non-cancelled requests before inserting the request.

The request stores:

- `booking_slot_key`;
- `booking_starts_at` and `booking_ends_at` in UTC;
- the source `booking_schedule_interval_id` while it still exists;
- the human-readable `preferred_window` snapshot.

Deleting a weekly interval does not erase timestamps from existing requests. Setting a request status to `cancelled` releases its capacity.

## Concurrency and retries

Capacity claims and service-request creation run in one database transaction. A `SELECT … FOR UPDATE` lock on the weekly interval serializes two buyers claiming the same slot. Capacity is the number of service requests whose `booking_slot_key` matches and whose status is not `cancelled`.

Every rendered request form carries a UUID submission token. `service_request_submissions` reserves that token transactionally. Retrying or double-submitting the same valid form returns the already created request and does not consume capacity or publish outbox events twice.

## Failure behavior

- Schedule exists, but the slot is missing, forged, blacked out, in the past, or full: reject the request and ask the buyer to reload/select another slot.
- Schedule exists, but availability cannot be fetched: browser fails closed; free text is not treated as a slot.
- No schedule exists for the selected location: require `preferred_window`, store no slot key/timestamps, and keep the request as manual follow-up.

## Verification

Feature coverage lives in:

- `tests/Feature/Storefront/BookingCalendarTest.php`;
- `tests/Feature/Admin/BookingCalendarManagementTest.php`.

Playwright covers the buyer slot selector in `tests/E2E/service-requests.spec.ts` and the custom admin schedule screen in `tests/E2E/admin.smoke.spec.ts`.
