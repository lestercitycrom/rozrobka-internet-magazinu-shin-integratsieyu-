# GREEN-020 — Custom admin catalog and import visibility

Owner: Mister Green
Status: done
Model: Codex Terra

## Goal

Turn the custom admin shell into a useful operational interface for the catalog/import foundation, using the same EasyTire brand system as the storefront.

## Scope

- Add custom admin navigation and pages for catalog overview, product/SKU inspection and NDI import runs.
- Add import-run detail with artifact roles, status, counts, rejections and change summary using existing audit data.
- Keep all labels in Norwegian Bokmål and Russian.
- Reuse the storefront's color palette, typography, logo and shared design tokens while keeping the admin compact, calm and operational.
- Add filters/pagination and safe empty states.
- Keep this slice read-only unless an existing domain action explicitly supports the requested mutation.
- Extend admin feature and Playwright tests for the new pages.

## Boundaries

- No Filament, Livewire or ready-made admin framework.
- The former monochrome-only requirement is cancelled; do not maintain a separate visual brand for admin.
- Do not change the public storefront.
- Do not expose stored supplier files, credentials, raw sensitive payloads or filesystem paths.
- Do not bypass role/active-user authorization.

## Acceptance

- An authorized admin can inspect current catalog state and the latest import outcome in NB/RU.
- Unauthorized users remain blocked.
- Admin Playwright covers login, catalog/import navigation, RU switch and logout.
- Build and relevant tests pass.
