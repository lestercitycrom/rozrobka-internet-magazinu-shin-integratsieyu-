# GRAY-030 — Catalog read model and test data

Owner: Mister Gray
Status: done
Model: Codex Terra

## Goal

Provide a stable, tested public catalog query boundary over the normalized NDI data.

## Scope

- Implement typed catalog filter/query DTOs and a paginated read service for tires and wheels.
- Support only fields already present in the schema: product type, brand, text/SKU, tire dimensions/season and wheel dimensions/fitment where available.
- Expose display-safe price/stock projections without embedding markup policy assumptions.
- Add deterministic factories/builders or a local/testing-only catalog demo seeder for feature/E2E use.
- Add focused tests for filtering, pagination, leading-zero SKUs, unpublished/quarantined rows and query count sanity.

## Boundaries

- Do not edit public or admin views/routes.
- Do not modify the NDI parser/publisher without coordinating with Mister Red.
- Do not infer currency or delivery promises.
- No external network calls.

## Acceptance

- Public/admin consumers can query a documented read model without reaching into supplier tables directly.
- Hidden/quarantined/incomplete rows cannot leak into public results.
- Relevant Pest tests pass.
