# Claude Code and Codex session prompts — LGL ERP

The active baseline is the implemented local three-role core in `docs/README.md`. Read `docs/ACCEPTANCE.md`, `docs/TASKS.md` and `docs/RUNBOOK.md` for current evidence, demo configuration and remaining release boundaries. The previous six-role expansion/fixed 20-day schedule are archived. The feature DOCX is absent; API examples are historical and routing is not enabled.

Current authorization: the owner approved automatic incremental local implementation and confirmed demo-only original data on 31 October 2026. The original DB remains preserved; a clean demo with @lgl.com accounts is active. Routine authorized tasks do not need renewed approval. Do not repeat preparation or reset data. Production deployment, operational-data migration and physical deletion are separate gates; these prompts do not independently expand authorization.

## 1. Review the current baseline

```text
Read AGENTS.md, CLAUDE.md and docs/README.md, then the linked analysis,
requirements, module cleanup, architecture, plan, tasks and agent workflow.
Inspect the current diff and preserve unrelated local edits.
Review the current three-role scope and recorded decisions D01-D08. Identify material
conflicts with the actual implementation and propose concise documentation
corrections only. Do not implement application code, migrate or seed a database,
change dependencies, contact devices/providers, disable modules or delete files.
Present material new decisions. Continue only within recorded authorization;
do not request implementation approval that the owner already granted.
```

## 2. Implement one explicitly approved task

```text
Read AGENTS.md, CLAUDE.md and docs/README.md plus linked planning documents.
Task: [ID]. Owner authorization: [scope/reference].
Verify recorded approval and dependencies in docs/TASKS.md. Inspect Git status.
Reserve exact files using docs/AGENT_WORKFLOW.md; one writer per shared checkout.
If authorization is absent, do not implement. If a required business decision is
missing, record its blocker and continue only independent authorized work.
Reuse the existing stack and tested code. Implement only the reserved task.
Use a verified isolated database for mutation/migration tests, never the current
application database. Test the task's acceptance, permissions and financial
invariants as applicable. Hand off changes, base/commit, exact commands/results
and unresolved risks for read-only review. Mark Done only after acceptance and
review evidence. Do not introduce deferred modules or integration stubs.
```

## 3. Read-only review of another agent's work

```text
Read shared instructions, the task acceptance and approved scope.
Review task [ID] at [base/commit/diff]. Do not edit files or run data mutations.
Check security, row/field/aggregate scoping, transactions, history preservation,
route/view alignment and focused test evidence. Report actionable findings with
file/line and impact; distinguish verified defects from untested risks.
Return findings to the writer. Integration is serialized by the integrator.
```

## 4. Resume safely

```text
Read shared instructions, planning docs and task reservations. Inspect Git status,
recent commits and current diff. Identify existing writer/reviewer ownership,
recorded authorization, blockers, test evidence and next dependency-ready task.
Do not assume another agent's reservation is abandoned. Continue only already
approved work in unreserved files; otherwise report the needed decision.
```

## 5. Financial and release verification after implementation approval

```text
Read REQUIREMENTS.md, ARCHITECTURE.md and PLAN.md acceptance gates.
On a disposable compatible database, verify exact installment sums, one-time
discount, partial/early/full payment, overpayment rejection, matching duplicate
submission retries, conflicting-key rejection, concurrent payment protection,
unique receipts, immutable originals, full reversal and restored outstanding debt.
Verify per-enrollment/global reconciliation and approved transfer/withdrawal rules.
Test role/record/field/aggregate access, manual attendance, dashboards, fresh
installation and existing-data upgrade. Record exact evidence and remaining
failures in docs/ACCEPTANCE.md. Do not claim deployment readiness from placeholder
tests. Prepare release/restore steps; do not deploy without deployment authority.
```
