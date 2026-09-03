# Phase A surface inventory

| Family | Surface/state | Route | Evidence |
|---|---|---|---|
| Authentication | Login; invalid credentials | `/admin/login` | screenshot + assertion |
| Shell | Sidebar, topbar, NB/RU switch, logout | every authenticated route | measurement + screenshot |
| Shell | Mobile drawer open, Escape close, focus return | dashboard at 390/320 | screenshot + assertion |
| Dashboard | NB and RU dashboard | `/admin` | screenshot + measurement |
| Dashboard | deterministic zero, low/attention, and large KPI values; semantic tones and stable geometry | testing-only `/admin/_e2e/dashboard/{state}` | NB/RU state screenshots at 1440/1024/390/320 + acceptance-state JSON |
| Dashboard | seven-viewport before/after comparison | `/admin` | `before/dashboard/` + `after/dashboard/` |
| Keyboard | sidebar/topbar, locale, logout, dashboard primary/work links | `/admin` | real Tab/Shift+Tab order + screenshots + acceptance-state JSON |
| Keyboard | form field/submit, named table region and first row action | content create + requests list | real Tab order + screenshots + acceptance-state JSON |
| Keyboard | drawer entry, reverse traversal, Escape close and focus return | dashboard at 390/320 | screenshot + assertion + acceptance-state JSON |
| Requests | list, long data, pagination | `/admin/service-requests` | screenshot + measurement |
| Requests | detail, editable status/note | seeded detail route | screenshot + measurement |
| Booking | calendar/capacity list | `/admin/booking-calendar` | screenshot + measurement |
| Booking | interval form | `/admin/booking-calendar/intervals/create` | screenshot + measurement |
| Orders | filtered list | `/admin/orders` | screenshot + measurement |
| Orders | payment/NDI detail | seeded detail route | screenshot + measurement |
| Catalogue | filtered list | `/admin/catalog` | screenshot + measurement |
| Catalogue | publication/product detail | seeded detail route | screenshot + measurement |
| Imports | list/empty state | `/admin/imports` | screenshot + measurement |
| Services | page list and create form | `/admin/services`, `/admin/services/create` | screenshot + measurement |
| Services | add-ons list | `/admin/service-addons` | screenshot + measurement |
| Services | public price list | `/admin/public-service-prices` | screenshot + measurement |
| Content | page list and create form | `/admin/content-pages`, `/admin/content-pages/create` | screenshot + measurement |
| Content | client validation, server validation, dirty locale guard | content create + promotion create | safe synthetic interactions + screenshots + acceptance-state JSON |
| Content | promotions list and create form | `/admin/homepage-promotions`, `/admin/homepage-promotions/create` | screenshot + measurement |
| Content | reviews list | `/admin/homepage-reviews` | screenshot + measurement |
| Content | rating summaries list | `/admin/homepage-rating-summaries` | screenshot + measurement |
| Content | business partners list | `/admin/homepage-business-partners` | screenshot + measurement |
| Media | upload, supplier-managed asset | `/admin/media` | screenshot + measurement |
| Media | destructive confirmation, cancelled without mutation | `/admin/media` | synthetic unassigned asset + screenshot + acceptance-state JSON |
| Pricing | list and create form | `/admin/pricing-rules`, `/admin/pricing-rules/create` | screenshot + measurement |
| Settings | storefront controls | `/admin/storefront-settings` | screenshot + measurement |
| Operations | integration/system health | `/admin/health` | screenshot + measurement |
| Long content | long synthetic request identity in NB/RU | `/admin/service-requests` | desktop screenshots + 1440/390/320 containment JSON |

Each route above is traversed in all seven viewport projects. The 1440, 1024, and 390 projects retain full route screenshot sets; other projects retain measurements and dashboard evidence. The acceptance-completeness interactions run only in the relevant desktop/mobile projects, while every project records its dashboard states. All form data, assets, and identities use obvious E2E/synthetic values; destructive and dirty-state dialogs are dismissed.
