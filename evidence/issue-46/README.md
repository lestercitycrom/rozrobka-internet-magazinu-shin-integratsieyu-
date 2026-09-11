# Issue #46 verification index

This directory contains the compact visual evidence for PR #49. Automated
geometry, accessibility-state, keyboard, touch and overflow assertions remain
the source of truth; the images below provide human-reviewable checkpoints.

## Customer homepage

| State | Locale / viewport | Evidence |
|---|---|---|
| Four-offer carousel | NB / 1440 | `customer-home-1440-nb-promotions.png` |
| Four-offer carousel | RU / 1440 | `customer-home-1440-ru-promotions.png` |
| Offer stack | NB / 390 | `customer-home-390-nb-promotions.png` |
| Offer stack | NB / 320 | `customer-home-320-nb-promotions.png` |
| Long conditions, scrollable and CTA contained | NB / 1440 | `customer-home-1440-nb-long-conditions.png` |
| Long conditions, scrollable and CTA contained | RU / 320 | `customer-home-320-ru-long-conditions.png` |
| Additional services | NB / 1440, 390, 320 | `customer-home-1440-nb-additional-services.png`, `customer-home-390-nb-additional-services.png`, `customer-home-320-nb-additional-services.png` |
| Additional services | RU / 1440 | `customer-home-1440-ru-additional-services.png` |
| Wheel services | NB / 1440, 390, 320 | `customer-home-1440-nb-wheel-services.png`, `customer-home-390-nb-wheel-services.png`, `customer-home-320-nb-wheel-services.png` |
| Wheel services | RU / 1440 | `customer-home-1440-ru-wheel-services.png` |

The Playwright suite additionally verifies the exact two-offer case as a
single column at both 390 and 320 pixels in NB and RU, and checks disclosure
operation with real Tab, Enter, Escape, pointer and touch input.

## Administration

| State | Locale / viewport | Evidence |
|---|---|---|
| Additional-service list | NB / 1440 | `admin-additional-services-1440.png` |
| Additional-service list | NB / 1024 | `admin-additional-services-1024-nb.png` |
| Additional-service form | NB / 1440 | `admin-additional-service-form-1440.png` |
| Additional-service validation and focus | RU / 390 | `admin-additional-service-form-390-ru.png` |
| Promotion form | NB / 320 | `admin-promotion-form-320-nb.png` |

The admin Playwright suite covers NB and RU at 1920, 1440, 1366, 1280, 1024,
390 and 320 pixels, including real Tab focus, required-field validation and
the mobile record-card replacement for wide management tables.
