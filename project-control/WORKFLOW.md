# EasyTire GitHub workflow

Version: 1.1  
Adopted: 2026-08-22  
Based on: `UNIVERSAL_GITHUB_COLLABORATION_PROTOCOL_V1.md`

This profile keeps the useful parts of the universal protocol and removes process that does not help the owner, PM or engineer make a safer decision.

## 1. Roles

### Owner

- makes business and production decisions;
- supplies customer decisions and external prerequisites;
- starts a work or review cycle with one short message;
- never needs to relay full Issue, blocker, report or review text between agents.

### PM / ChatGPT Pro

- turns owner/customer input into a bounded Issue;
- records acceptance criteria and authoritative sources;
- answers requirement questions in the Issue;
- reviews the exact PR head SHA, code, tests and runtime evidence;
- posts `PM REVIEW` and merges only the reviewed SHA;
- does not implement or silently edit the engineer's branch while reviewing it.

### Chief engineer / Codex / Mister Red

- owns architecture and implementation quality;
- works from the active Issue in one `codex/issue-N-short-name` branch;
- adds tests and proportionate evidence;
- reports facts and blockers in GitHub, not through owner relays;
- posts `ENGINEER UPDATE` and stops at the review gate;
- never self-approves the PR.

Additional engineers or auditors may handle bounded subtasks, but their changes and evidence land in the same authorised branch/PR unless the Issue is split first.

## 2. Three truth domains

There is no single universal priority list for every kind of fact.

### Required product behaviour

1. merged permanent decisions in `DECISIONS.md`;
2. the active Issue, including later explicit `DECISION` comments;
3. the consolidated current requirements document linked from `CONTEXT.md`;
4. older source material referenced by the Issue.

### Implemented behaviour

1. accepted commit on `main`;
2. current automated tests and As-Built documentation;
3. the active PR for proposed, not-yet-accepted behaviour.

### Runtime state

1. fresh read-back/evidence from the named environment;
2. `STATUS.md`;
3. runbooks and deployment configuration.

Code is evidence of what exists, not proof that an old implementation overrides a new customer decision. A chat message becomes authorised engineering scope only after the PM records it in the active Issue or a permanent decision.

## 3. One bounded change = one Issue + branch + PR

- Issue `#N` is task `TASK-N`.
- Branch: `codex/issue-N-short-name`.
- PR title: `[TASK-N] Short result`.
- PR body contains `Closes #N`.
- Review fixes stay in the same PR.
- Direct feature pushes to `main` are forbidden.

Large initiatives use one umbrella Issue and several independently reviewable child Issues. They do not use one giant PR merely to satisfy the one-Issue rule.

## 4. What belongs where

### Issue

- goal and business context;
- sources and precedence for this task;
- allowed and forbidden actions;
- acceptance criteria;
- scope changes, decisions and blockers;
- runtime-gate impact and stop condition.

### Pull Request

- implementation and changed boundaries;
- tests and evidence paths;
- migration, deployment and rollback notes;
- review findings and fixes;
- exact current head SHA.

### Permanent control files

- `STATUS.md`: short runtime and release facts only;
- `DECISIONS.md`: decisions that must survive closed Issues;
- `CONTEXT.md`: read order and authoritative document pointers;
- `CURRENT_TASK.md` / `CURRENT_REVIEW.md`: pointers, not copied conversations.

Do not create timestamped handoff folders, duplicate final reports or ZIP archives when the Issue/PR already contains the work.

## 5. Work cycle

1. PM creates a complete Issue and marks it `ready`.
2. Owner tells the engineer: `Создан Issue #N. Приступай строго по нему.`
3. Engineer posts `ENGINEER STARTED`, creates the branch and Draft PR.
4. Questions and scope decisions go to the Issue; implementation evidence goes to the PR.
5. Engineer posts `ENGINEER UPDATE` and marks the PR ready.
6. Owner tells PM: `Проверь PR #N по Issue #N.`
7. PM reviews the exact head SHA and posts `PM REVIEW`.
8. `CHANGES_REQUESTED` returns the same PR to engineering. Any new commit invalidates the old approval.
9. `APPROVED_FOR_MERGE` permits merge of that SHA only.
10. Merge closes the Issue. Runtime enablement still requires its own recorded gate decision.

Workflow labels are intentionally limited to `ready`, `in-progress`, `pm-review`, and `blocked`.

## 6. Standard comments

### ENGINEER STARTED

```text
ENGINEER STARTED

- Issue: #N
- PR: pending or #N
- Base SHA: <full SHA>
- Head SHA: <full SHA>
- Scope: <authorised work>
- Tests: pending or baseline
- Evidence: pending or paths
- Runtime state: <current gates>
- Blockers: none or exact blocker
```

### ENGINEER UPDATE

```text
ENGINEER UPDATE

- Issue: #N
- PR: #N
- Base SHA: <full SHA>
- Head SHA: <full current SHA>
- Result: <completed scope>
- Tests: <exact results and CI links>
- Evidence: <paths/links>
- Migration / rollback: <facts>
- Runtime state: <final gates>
- Blockers: none or exact blocker
```

### PM REVIEW

```text
PM REVIEW

- Issue: #N
- PR: #N
- Verdict: CHANGES_REQUESTED | APPROVED_FOR_MERGE | BLOCKED
- Reviewed head SHA: <full exact SHA>
- Accepted: <facts>
- Required changes: <none or exact list>
- Runtime authorization: NONE | STAGING | PRODUCTION
```

### DECISION / BLOCKER

Use the universal protocol fields. A decision that must outlive the task is also summarised in `DECISIONS.md`.

## 7. Quality gates

Every PR must pass:

- project-control structure validation;
- secret scan over reachable history;
- Laravel/Pest regression and Pint;
- frontend production build;
- Playwright buyer/admin coverage proportionate to the changed surface.

The default CI runs the full existing Chromium E2E suite. Visual work additionally includes reviewed screenshots at the Issue's named viewports. High-risk integration work adds fake-provider contract tests and explicit runtime read-back; CI must never send C1, capture money, notify customers or use live credentials.

Evidence belongs in `evidence/issue-N/` only when it is useful after the CI log expires. Keep it small, redacted and reproducible.

## 8. Merge and runtime gates

These are separate decisions:

```text
APPROVED_FOR_MERGE
STAGING_DEPLOY_APPROVED
STAGING_INTEGRATION_APPROVED
PRODUCTION_DEPLOY_APPROVED
PRODUCTION_INTEGRATION_APPROVED
```

A merge never enables payment, commercial NDI C1, imports, email, Notion, Telegram or any other external side effect. The Issue and PR state at minimum:

```text
production_enabled
integration_mode
real_data_used
payments_or_external_side_effects
rollback
```

GitHub `origin/main` is canonical. The `staging` remote is a deployment target only. Until a repository deploy key is configured, the chief engineer deploys the exact merged `origin/main` SHA using the existing protected server remote and records the read-back.

## 9. Customer input

Telegram, calls, PDFs, spreadsheets and voice messages remain primary evidence. They do not need to be copied into every task. The PM records the accepted meaning in the Issue, includes the source path/message/date, and notes whether it supersedes an older decision.

If transcription or interpretation is uncertain, the Issue stays blocked on that exact point rather than silently inventing content.

## 10. Hotfix

A production incident may use a short `hotfix` Issue and branch, but still requires:

- reproducible incident evidence;
- the smallest safe change;
- automated regression;
- exact SHA review;
- rollback and post-deploy read-back.

Urgency may shorten the Issue, never remove auditability or permit unreviewed external side effects.

## 11. Minimal owner interaction

Engineer start:

```text
Создан Issue #N. Приступай строго по нему.
```

PM review:

```text
Проверь PR #N по Issue #N и дай verdict на точный head SHA.
```

Everything else is read directly from GitHub.
