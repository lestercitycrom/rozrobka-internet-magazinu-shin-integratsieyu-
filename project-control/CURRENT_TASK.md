# Current task

- Issue: [#44 — Implement customer homepage and navigation revision](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44)
- State: `in-progress`
- Branch: `codex/issue-44-mysql-schema-repair`
- PR: pending (new PR relates to #44 and must not close it before release acceptance).
- Exact base: `fc695b63f3721e98ac665b4c0e505f313592afbd`.
- Scope: repair MySQL error 1118 with a prerequisite conversion of four legacy URL columns to `TEXT`, make the homepage composition migration resumable and fail-closed, and prove fresh/update/partial/repeat/conflict paths on real MySQL 8.
- Known pending materials: exact `003 muzik.mp4` and a confirmed Snapchat destination. The staging review must use the poster fallback and omit Snapchat until supplied or explicitly deferred.
- Runtime boundary: code and isolated CI work only. Staging remains stopped at active `f70f358f06af384364973951eaab8d4fac2b2203`; no replay/deploy, server migration/DDL/restore, `current` switch, service start, integration call, payment, notification or real customer submission is authorised before a new exact-SHA PM runtime gate.
- Review gate: return one ENGINEER UPDATE with the exact PR head, migration diff, real-MySQL regression evidence, complete CI result, backward-compatibility facts and protected failed-candidate/backup retention plan.

Issue #44 comment `5650254216` is the current bounded forward-schema-repair gate. This file is only a pointer.
