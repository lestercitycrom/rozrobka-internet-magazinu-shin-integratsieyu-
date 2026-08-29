# EasyTire context map

Updated: 2026-08-29

This file prevents every new PM/engineer session from rereading the entire customer archive.

## Required read order

1. [`STATUS.md`](STATUS.md) — current runtime facts and gates.
2. [`DECISIONS.md`](DECISIONS.md) — permanent accepted decisions.
3. [`WORKFLOW.md`](WORKFLOW.md) — roles and handoff rules.
4. [`CURRENT_TASK.md`](CURRENT_TASK.md) — active Issue.
5. Active Issue — exact scope, sources, acceptance and blockers.
6. [`docs/management/WEBSITE_REQUIREMENTS_BASELINE.md`](../docs/management/WEBSITE_REQUIREMENTS_BASELINE.md) — reconciled current website requirements.
7. [`docs/management/WEBSITE_CONFORMITY_AUDIT.md`](../docs/management/WEBSITE_CONFORMITY_AUDIT.md) and [`WEBSITE_REQUIREMENTS_TRACEABILITY.csv`](../docs/management/WEBSITE_REQUIREMENTS_TRACEABILITY.csv) — latest source-to-implementation audit.
8. Only the architecture, quality, operations and source files linked by the Issue.
9. [`CURRENT_REVIEW.md`](CURRENT_REVIEW.md) and the active PR when reviewing.

Do not start with old ZIPs, full Telegram exports or every report. Open primary evidence only when the Issue cites it or a requirement is disputed.

## Stable technical map

- Application: [`application/`](../application/)
- Architecture: [`docs/architecture/`](../docs/architecture/)
- Test strategy: [`docs/quality/TEST_STRATEGY.md`](../docs/quality/TEST_STRATEGY.md)
- Operations: [`docs/operations/RUNBOOK.md`](../docs/operations/RUNBOOK.md)
- Current website baseline: [`docs/management/WEBSITE_REQUIREMENTS_BASELINE.md`](../docs/management/WEBSITE_REQUIREMENTS_BASELINE.md)
- Source register: [`docs/management/WEBSITE_SOURCE_REGISTER.md`](../docs/management/WEBSITE_SOURCE_REGISTER.md)
- Conformity audit: [`docs/management/WEBSITE_CONFORMITY_AUDIT.md`](../docs/management/WEBSITE_CONFORMITY_AUDIT.md)
- Atomic traceability: [`docs/management/WEBSITE_REQUIREMENTS_TRACEABILITY.csv`](../docs/management/WEBSITE_REQUIREMENTS_TRACEABILITY.csv)
- Previous consolidated TZ (historical until reconciled items are retired): [`docs/management/CURRENT_TZ_2026-08-22.md`](../docs/management/CURRENT_TZ_2026-08-22.md)
- Historical audits/reports: [`docs/management/reports/`](../docs/management/reports/)
- Primary original specification: [`docs/primary/ТЗ.txt`](../docs/primary/ТЗ.txt)

## First prompt for PM / ChatGPT Pro

```text
Ты PM проекта EasyTire. Прочитай project-control/CONTEXT.md и перечисленные там STATUS, DECISIONS и WORKFLOW. Затем проверь активный Issue/PR. Не меняй код. Дай PM REVIEW только на точный head SHA по шаблону WORKFLOW.md.
```

## Owner commands

Engineer:

```text
Создан Issue #N. Приступай строго по нему.
```

PM:

```text
Проверь PR #N по Issue #N и дай verdict на точный head SHA.
```
