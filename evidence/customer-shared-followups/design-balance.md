# Profile-specific design balance — 17 September 2026

Owner follow-up to the completed shared requests at `b25bc47`, explicitly
clarified again during implementation:

- **Classic only:** give the existing bold main headings sufficient line spacing
  for Norwegian diacritics; keep its font family, weight and finder design.
- **Modern only:** make main headings bolder (700 instead of 600), and give the
  vehicle finder a restrained frame, grouped surfaces and four category tiles.
  Keep its original heading leading and ordinary body-copy weight.

The modern rules are scoped to `[data-storefront-design='modern']` in
`customer-design-balance.css`. The Classic stylesheet changes line-height values
only. No markup, JavaScript, business configuration, catalogue or database edits.

## Customer-request audit

The complete available text of `ChatExport_2026-09-16/messages.html` was checked
again against the shared-request mapping. All recorded written requests are
implemented, subject to the owner's intentional split between the two designs.
The verified Snapchat destination is still pending the customer; its visible
placeholder and administrator setting are ready. Three voice attachments were
not included in the export, so their contents are not claimed as reviewed.

## Verification and publication

- Before snapshots: `application/tmp/customer-balance/before/` (both live
  profiles, Norwegian, 1440 and 390 px).
- Focused browser matrix: `playwright.balance.config.ts`; Modern/Classic,
  NB/RU, 1440/1280/900/390/320 px. Checks profile boundaries, real diacritic ink
  clearance, heading weights, finder controls and GET-only navigation.
- Candidate captures: `application/tmp/customer-balance/evidence/`.
- Deploy predecessor on both hosts is pinned to the verified `b25bc47` release
  `20260917021110-b25bc47621c0-customer-shared`. Both predecessors must remain
  available for rollback, with environments and Classic session/cache isolation.
- Exact final SHA, actual pass/skip counts and post-deploy read-back are recorded
  in PR #84 after verification. GitHub Actions is billing-blocked; local checks
  are used without claiming a green cloud run or disabling branch restrictions.
