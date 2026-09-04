# Issue 34 cumulative staging acceptance

This directory contains compact, redacted, reproducible evidence for the final cumulative visual-release acceptance.

- Application candidate: `f70f358f06af384364973951eaab8d4fac2b2203`.
- Previous accepted staging application: `ee512d723e78a65a89bfdee4268c3a779f632b33`.
- The PM approved managed queue recovery, exact staging deployment and read-only live acceptance for this exact application SHA.
- The exact candidate is deployed on staging and is awaiting final PM review in PR #43.
- No credentials, unrestricted server paths, customer PII, real order/request data, supplier payloads or external mutations belong in committed evidence.

`predeploy-checkpoint.md` records the initial Phase 1 result. `runtime-precondition-b.md` records the PM-authorised external-gate hardening, bounded queue inventory, read-only worker diagnosis and repeated health checkpoint. `final-staging-acceptance.md` records the managed recovery, exact deployment, public/admin live matrices, ledger reconciliation and final runtime read-back. Selected durable screenshots are in `live/`.
