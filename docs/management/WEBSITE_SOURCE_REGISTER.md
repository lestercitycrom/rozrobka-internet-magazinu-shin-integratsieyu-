# EasyTire website source register

Updated: 2026-08-29

Scope: Issue #10 source-to-implementation audit

Privacy: redacted; no credentials, customer PII, raw exports or proprietary supplier workbooks are reproduced here.

## Precedence used

1. Permanent decisions in `project-control/DECISIONS.md`.
2. The latest explicit customer decision that can be tied to an exact message, file or voice identifier.
3. Active PM decisions in Issues #9/#10.
4. Canonical requirements documents.
5. Earlier TZ/proposals.

Developer, PM and ChatGPT Pro proposals were treated as proposals unless a customer/owner message accepted them. Runtime behaviour was taken from accepted Git history plus a fresh staging read-back, never from a screenshot or old report alone.

## Package and file inventory

| ID | Source | SHA-256 / identity | Coverage | Authority and audit note |
|---|---|---|---|---|
| SRC-001 | `docs/primary/ТЗ.txt` | `EA831972D2042C9E92990F49D7284742A44AE2CEC065DB207F98E785A5DCBB5D` | Original four-section brief | Early customer baseline. Later explicit corrections supersede its visual, wording and integration assumptions. |
| SRC-002 | `razrabotka-internet-magazina-shin-integratsiey-1643402.zip` | `7A946B3FEA53A27769BADE3D63F3A7DD853603DA4ECDCC7C5CA5DDB4C28092F4` | 104 archived files; project posting; all 188 `conversation.json` messages reviewed | Primary Freelancehunt evidence through the archive date. |
| SRC-003 | `ChatExport_2026-08-28.zip` | `1A6E844086AB27864880731C9D1A41182586794ADAB97461B44668CC8E6623B2` | All 140 `div.message.default` records, all 8 service/date-separator nodes, all 24 supplied screenshots and all 3 voice messages reviewed | Primary later Telegram evidence. The deterministic message count includes both normal and `joined` default records, including empty-text/media-only records. The eight `div.message.service` date separators are not customer messages, giving 148 total message nodes. The filename differs from the Issue's `(1)` suffix, but the required hash is identical. No governing customer message was excluded. |
| SRC-004 | Local/private customer source `Корректировка 1 - Shop NDI.pdf` (excluded from Git) | `B32E7E55C699DA88358078E66B5C95E25E7255C596909020A70CA2354037173E` | All 8 pages rendered and visually inspected | Primary consolidated correction after the customer call. The raw PDF is intentionally not present in accepted `origin/main` or this PR. |
| SRC-005 | Local/private customer source `docs/files/SEO EasyTire.no.xlsx` (excluded from Git) | `BDAEA064E1FA6648B43018EAF1AAFC93F4E232870D8AE69FA6072073231EDCCD` | Sheet `Лист1`, range A1:N13; 11 non-empty SEO/service URL rows | Primary customer content/metadata/form source. The raw workbook is intentionally not present in accepted `origin/main` or this PR. It was readable locally; the older PM note `blocked access` is false and superseded by this audit. |
| SRC-006 | Local/private `docs/files/` customer media package (excluded from Git) | private customer source set | 107 raw files / 102 meaningful files: 40 PNG, 24 JPG, 15 JPEG, 3 HEIC, 14 videos, 2 PDF, favicon set, 1 XLSX | Primary customer-supplied brand/workshop/service media. Five `.DS_Store` files are non-content metadata. Representative images and key video frames were visually inspected; the videos are marketing/work footage, not accepted requirements text. |
| SRC-007 | `API oppsett og data filer fra NDI Norge AS.pdf` | `A27C68FD7803F4800253ADB11799E96708C60F102406BDBA850F0CB5B0E95A53` | Provider setup reply | Primary provider evidence for API/data boundaries, not customer visual scope. |
| SRC-008 | `API oppsett og data filer fra NDI Norge AS 2.pdf` | `075E0A8E41FF34CE0551E74440A58EE27E1A09114D9C3EF98A5BC19B464AC29A` | Provider image/access follow-up | Primary provider evidence for NDI image availability and safe media behaviour. |
| SRC-009 | `EASYTIRE_PM_PACKAGE_2026-08-28.zip` | `C7CFDB971BD762AD16AD777D0C063442F76F156AE1D74D9B004531D35CE2DC9D` | README, project review, website draft, Notion draft, 86-row preliminary source matrix | Secondary navigation only. Every material conclusion was checked against SRC-001–SRC-008. Its SEO blocker and untranscribed-voice caveats are now stale. |
| SRC-010 | `EASYTIRE_GIT_COLLABORATION_PROTOCOL_V1.1.md` | `31BDF1EE78C0DA10A2A3AE6DC0FC98C60279C2B3697FD5CE3A3861B3814AC4AD` | Workflow/gates | Governance source; does not define customer product behaviour. |
| SRC-011 | ChatGPT Pro frontend deliverables v1/v2 | historical handoff archives | Visual proposals previously reviewed and selectively integrated | Secondary proposals. They do not override later customer corrections or accepted application behaviour. |
| SRC-012 | GitHub `origin/main` at audit start | `4f95712be4bf2238219b51533301823595c306a0` | Accepted implementation/code/test baseline | Authoritative implemented behaviour. |
| SRC-013 | Staging read-back | `REVISION 01a931d432b32a5a0633d1b508256b225387d429` | Runtime/public/admin verification on 2026-08-29 | Runtime authority. The one newer `main` commit is documentation-only. Exact operational capacities/counts remain out of public evidence. |

## Significant customer decisions

| Reference | Date | Side | Supported meaning | Supersession / uncertainty |
|---|---|---|---|---|
| Freelancehunt `63792521` | 2026-07-26 | customer | Modern combined services + tyre/wheel ecommerce site; Norwegian; editable content; registration lookup; cart/payment/admin/SEO. | Foundational. Later messages refine implementation and visual direction. |
| Freelancehunt `63847158` | 2026-08-01 | customer | Seasonal service is primary; shop is a full second direction visible immediately; registration and manual-size entry. | Supersedes the early placeholder storefront hierarchy. |
| Freelancehunt `63845605` | 2026-08-01 | customer | Retail pricing/markup, local vs remote stock, DOT and supplier-data visibility concerns. | Final production formula and DOT age policy remain unresolved. |
| Freelancehunt `63845814` | 2026-08-01 | customer | Mounting/balancing belongs to EasyTire; TPMS/bolts are opt-in only with official applicability data. | Safety boundary remains current. |
| Freelancehunt `63847346` | 2026-08-01 | customer | Restructuring the Notion workspace is later/separate paid work after the website. | Supports the owner's 2026-08-29 postponement; current TZ wording that treats Notion as active is stale. |
| Freelancehunt `63847678` | 2026-08-01 | customer | Contact/map/social/local-business and B2B direction. | Partner logos/claims still require explicit approval and rights. |
| Freelancehunt `63890028–63890061` | 2026-08-06 | customer | Rebuild seasonal request; one/two cars; known/unknown size; Dekkhotell renewal; exact/range quote. | Supersedes old Tilda form/pricing assumptions. |
| Freelancehunt `63899505`, `63899518`, `63910552` | 2026-08-08 | customer | SEO workbook is the source for the service pages. | Workbook was independently opened and mapped in this audit. |
| Freelancehunt `63934803`, `63935040` | 2026-08-11 | customer | Avoid `É/én` and rejected phrase `mobilt dekkskift`; prefer `dekkskift hjemme hos deg`. | Supersedes earlier generated Norwegian wording. |
| Freelancehunt `63936526` | 2026-08-11 | customer | Sharp first block/no rounded version preferred. | Later owner/customer decision makes the sharp storefront the primary version. |
| Telegram `94971`, `94978` | 2026-08-14 | customer | Alternate dark black/red and light sections; do not make everything dark. | Current accepted visual direction. |
| Telegram `94982` | 2026-08-14 | customer | One strong seasonal CTA on hero left; shop finder remains a complete adjacent action. | Current home hero contract. |
| Telegram `94984–94993` | 2026-08-14 | customer | Remove the old strip/large heading; compact four-step process; price preview approved. | Supersedes earlier home composition. |
| Telegram `95001` + voice `audio_1@14-08-2026_20-03-43.ogg` | 2026-08-14 | customer | Image-led promotions with revealed copy; ideas include two-car and early-October discounts. | Voice was independently transcribed locally. Exact discount values/dates were not approved; minor noun-level uncertainty in the small local transcript does not affect that conclusion. |
| Telegram `95005`, `95007`, `95010`, `95012`, `95014`, `95019` | 2026-08-14 | customer | Accepted service-card format; remove local-team home block; Dekkhotell directly after hero; reviews approved; smaller map/socials; optional translucent sticky nav. | Current home-section contract. |
| Correction PDF pages 1–8 | 2026-08-14 | owner/customer call consolidation | Coherent sharp redesign, mobile priority, KFC-like typography reference, professional filters/category navigation, no template copying. | Current visual baseline; not a licence to clone the referenced sites. |
| Telegram voice `audio_3@20-08-2026...ogg` | 2026-08-20 | customer | Two mobile vans exist; the team may choose area allocation or fill-one-then-second workflow. | Independently transcribed. Capacity is confirmed; the algorithm is deliberately not approved yet. |
| Telegram/Dintero messages `95811–95823` | 2026-08-19 | customer/provider discussion | NDI goods go to EasyTire, not directly to buyer; Dintero authorization precedes delayed capture after readiness. | Consolidated in permanent decision COM-001. |
| Owner decision in Issue #9/#10 | 2026-08-29 | owner | Audit first; separate PM reviews exact SHA; Notion postponed; website only. | Highest current project-scope decision. |

## SEO workbook routes verified

`/dekkskift-tromso`, `/dekkhotell-tromso`, `/dekkskift-tesla-elbil-tromso`, `/omlegging-balansering-dekk-tromso`, `/dekkreparasjon-tromso`, `/dekk-og-felger`, `/tpms-sensor-tromso`, `/oljeskift-tromso`, `/bilbatteri-tromso`, `/bildiagnose-tromso`, `/bytte-lyspaerer-viskerblad-tromso`.

## Explicit limitations

- No private archive, raw customer message, credential, registration number, provider payload or proprietary NDI workbook is committed.
- All 140 Telegram default message records, all eight service/date separators, all 24 screenshots and all three voice messages were inspected. Empty-text and media-only records were retained in the count; no governing customer message was omitted. Raw media remains outside Git evidence.
- The customer media videos contain demonstrative workshop/marketing material. Representative keyframes were inspected; they were not promoted to textual requirements.
- Production pricing, DOT age policy, promotion values/dates, partner logo rights, licensed wheel/full-kit fitment, production Dintero and outbound notification credentials remain decisions/external prerequisites rather than inferred defects.
