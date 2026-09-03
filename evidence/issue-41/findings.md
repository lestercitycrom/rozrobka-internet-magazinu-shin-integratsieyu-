# Phase A atomic finding register

Baseline: `9db6bb1304d257c2ecf262a74a0c2cc3e6ee6612` plus audit-only fixture/harness files. Broad visual remediation begins only after this register is recorded.

| ID | Priority | Finding | Deterministic evidence | Required resolution | Status |
|---|---|---|---|---|---|
| A41-001 | P1 | Booking calendar and service-page list impose a 499–513 px document width at both 390 and 320 px. The page itself pans sideways and right-side actions can leave the viewport. | `before/mobile-390x844-measurements.json`, `before/mobile-320x800-measurements.json`, selected mobile screenshots | Contain wide data inside the labelled table region; page width must equal viewport and page actions must wrap. | Open |
| A41-002 | P2 | Six shared table families are mouse-scrollable but not keyboard-focusable or named: requests, booking, orders, catalogue, services, pricing, plus order-detail tables. | All `before/*-measurements.json`; missing `tabindex`, `role`, and accessible label on overflow owner | Every wide table owner must expose `tabindex="0"`, `role="region"`, and an accessible label without overwriting specific existing labels. | Open |
| A41-003 | P2 | Dashboard hierarchy is a nested card maze: four marketing-style cards repeat “EasyTire Admin”, repeat their own title as a link, and a large identity panel duplicates destinations already present in the sidebar. At 320 px KPI labels break into very short fragments. | `before/selected/desktop-dashboard.png`, `before/selected/mobile-dashboard.png` | One clear page heading, compact semantic KPIs, one task list with one affordance per destination, and no duplicate identity/navigation panel. | Open |
| A41-004 | P2 | Attention counts, passive totals, request lifecycle states, and operational health states do not share a meaningful semantic status system. Attention and passive totals both read as green; request states all use the same red treatment. | Dashboard, requests, and health screenshots in desktop/mobile contact sheets | Introduce reusable attention/info/success/neutral/error status styles and apply them consistently. | Open |
| A41-005 | P2 | Admin pages mix raw utility recipes and shared classes for headings, panels, forms, alerts, controls, radii, shadows, and table headers. The result changes density and shape between adjacent destinations. | `before/contact-desktop-1440.jpg`, `before/contact-mobile-390.jpg`; source inventory across `resources/views/admin` | Add one admin presentation layer scoped to the authenticated shell and normalize these shared families without changing business behavior. | Open |
| A41-006 | P2 | Legacy/unknown request option codes render as raw translation keys instead of a safe readable fallback. | Request detail code path; covered by post-remediation unresolved-key assertion | Render known translated labels and humanize unknown legacy values; never expose `project.request.*` to the operator. | Open |

Confirmed matches which must remain stable:

- Login and validation error remain legible on desktop and mobile.
- Sidebar drawer opens, closes with Escape, and returns focus to the trigger.
- NB/RU admin locale switching works and is not exposed in the public storefront.
- At 1024 px and above, no audited surface has global horizontal overflow.
- Existing save/submit routes, filters, pagination, payment/NDI controls, and unsaved-change guard are behaviorally out of scope and must not regress.
