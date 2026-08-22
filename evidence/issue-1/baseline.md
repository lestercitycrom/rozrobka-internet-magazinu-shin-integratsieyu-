# Issue #1 baseline evidence

Recorded: 2026-08-22

## Accepted code

- Local branch before bootstrap: `main`
- Local/staging SHA: `c477e4a7869d0bbedbe02d7e18203433d341c3a2`
- GitHub baseline tag: `github-bootstrap-baseline-2026-08-22`
- Staging product read-back: HTTP 200 and expected NDI sandbox quantity notice present.

## Safety audit

- Reachable history: 58 commits.
- Custom secret-pattern scan of all reachable commits: no matches.
- Historical path scan: no committed `docs/access.md`, root `.env` or `application/.env`; only the safe `application/.env.example` template exists in history.
- `docs/access.md`, `.env`, private customer files and runtime credentials remain excluded.
- Local object database contains large unreachable loose objects. They were not rewritten or deleted and are not transferred by a normal reachable-ref push.

## Runtime

- No application code changed by the baseline publication.
- No production or external integration gate changed.
- Existing server deployment remote remains intact as `staging`.
