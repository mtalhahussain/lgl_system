# Claude Code and Codex collaboration

Both agents follow `AGENTS.md`; Claude also reads `CLAUDE.md`. Neither agent has permanent module ownership or authority to expand scope. Default to one implementation writer and one read-only reviewer. The owner chooses the writer for each task.

## Approval boundary

On 31 October 2026 the owner approved automatic incremental local implementation, removed Accountant from scope and confirmed the old data was demo-only. Current authorization/status is in `docs/TASKS.md`; evidence is in `docs/ACCEPTANCE.md`. A new local demo is active and the original database remains unchanged. Do not repeat setup or re-request routine task approval. Operational-data migration, external deployment and physical deletion remain explicit gates; a backlog or generic prompt does not expand authority.

## Work reservation

1. Read the task register, plan and current diff. Identify unrelated user changes; preserve them.
2. The owner/integrator reserves a task with agent name, branch/worktree, exact owned file paths and start time. A reservation must be committed/communicated before another agent starts overlapping work. A shared Markdown file is not an atomic lock.
3. In a shared checkout, allow only one writer, including task/doc edits. The reviewer may inspect and report without editing. Concurrent writes require separate Git worktrees/branches and disjoint ownership confirmed by the integrator; otherwise serialize.
4. Treat routes, migrations, shared models/services/layouts, dependency manifests/locks, agent instructions and the task register as exclusive files. Serialize changes to these even when features differ. Coordinate schema interfaces before independent feature work.
5. Implement only approved task scope, run targeted checks in an isolated environment, and provide the reviewer with base/commit, files, behavior, test results and data implications. Do not run schema changes against the configured application database.
6. Reviewer inspects the diff and acceptance evidence, identifies regressions and reports findings. Reviewer does not patch the writer's files concurrently. Writer resolves findings and reruns affected tests.
7. Integrator merges one reviewed task at a time, resolves conflicts, reruns affected integration checks and updates the central register. Only then may dependent work start. Never overwrite conflicts by selecting an entire side without review.

Do not reset, clean, stash, stage or commit another person's changes. If commits are requested, stage explicit owned files only. Avoid blanket formatting and dependency updates. Never share a mutable DB for concurrent migration/testing tasks; each worktree uses a distinct test DB.

## Task lifecycle

`Blocked approval` → `Ready` → `In progress` → `Review` → `Done`. Use `Blocked decision` when an identified decision prevents safe progress. A task is Done only when its acceptance criteria pass and reviewer/integrator evidence is recorded. Existing code and passing placeholder tests do not justify Done.

Every handoff records: task ID; scope/files; branch/commit; changes; commands/results; unresolved risks; next dependency. Record decisions once in the register and update requirements if behavior changes. Do not let agent chat become a competing source of truth.

## Session templates

**Implementation:** Read instructions and planning docs. Verify authorization for task `[ID]`, inspect status/diff, reserve owned files, implement only that task, validate acceptance, and hand off for review.

**Review:** Review task `[ID]` at `[commit/diff]` against acceptance and permissions. Report defects with file/line and practical impact. Do not edit files or expand scope.

**Resume:** Read instructions, task register, recent commits and working diff. Report current ownership, blockers, evidence and next ready task. Continue only within recorded authorization; do not assume an interrupted reservation is abandoned.
