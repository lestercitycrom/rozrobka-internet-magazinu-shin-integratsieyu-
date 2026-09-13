# Current task

- Issue: [#44 — Implement customer homepage and navigation revision](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44)
- State: `in-progress`
- Branch: `codex/issue-44-hook-storage-isolation`
- PR: [#52 — `[TASK-44] Isolate deployment tests from persistent storage`](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/52) (Draft, relates to #44).
- Exact accepted application candidate/base: `8ea1a65cb34f7786764c520d6662fe86201d310d`.
- Scope: isolate Composer/build/Laravel tests from persistent staging storage and runtime configuration, prove failure/success ordering in Linux CI, and return the exact hook artifact for PM review.
- Known pending materials: exact `003 muzik.mp4` and a confirmed Snapchat destination. The staging review must use the poster fallback and omit Snapchat until supplied or explicitly deferred.
- Runtime boundary: local/CI work only. No hook installation, staging replay/deploy, server migration/seeding, service start, integration call, payment, notification or real customer submission is authorised before exact-SHA PM review.
- Review gate: return the exact PR head, hook SHA-256, unchanged application-tree proof, regression/CI results and the exact proposed install/replay procedure; keep the PR Draft.

The PM runtime-remediation instruction in the project thread is the current start gate. This file is only a pointer.
