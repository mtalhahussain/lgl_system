# Shared project instructions

## Authorization and scope

The owner authorized incremental local implementation on 31 October 2026: "yes krte rahoo step by step auto". Exactly Admin, Teacher and Student; Admin handles accounting. The owner confirmed original records were demo-only. Earlier work preserved the original database and used a separate demo. On 5 November 2026 the owner clarified that the currently configured local `lgl_system` is the working database and authorized using it for incremental implementation, including additive migrations. This does not authorize resets, seeding, deletion or overwriting backups. Do not request routine task-to-task approval again.

The owner separately authorized redundant project Markdown deletion and consolidation into README.md, AGENTS.md, CLAUDE.md, docs/PROJECT.md and docs/TASKS.md. This does not authorize deleting application modules, historical records, databases or private backups. Deployment is owner-managed; do not request server access or pursue hosting work without a new request.

Read README.md, docs/PROJECT.md and docs/TASKS.md before changes. Do not rerun demo preparation or reset the active database merely to resume work.

## Implementation rules

- Preserve Laravel/PHP, Eloquent, Blade/Bootstrap and database compatibility. Reuse working behavior; avoid new packages and broad rewrites.
- Enforce role, row, field and aggregate access server-side. Teacher access is assigned-batch academic data only; Student access is self only. Finance, global reports, settings and account management belong to Admin.
- Keep mutations in existing services and controllers small. Use meaningful validation and tests rather than speculative layers.
- Store money as DECIMAL; calculate using integer minor units. Financial writes require transactions, row locks, unique submission keys and receipts. Preserve originals and correct through linked reversals.
- Never cascade-delete enrollment or financial history. Deferred modules and columns remain pending separately approved cleanup.
- Do not rewrite applied migrations. Preserve pre-existing user edits, especially composer.lock and database/migrations/2026_01_20_170942_add_teacher_info_fields_to_users_table.php. Its historical down() defect requires forward repair or matching backup recovery, not destructive rollback.
- Never print credentials or whole .env files, contact devices/providers during ordinary checks, or run demo seeders on operational data. Keep private backups and browser profiles out of releases.
- Run database-writing verification only in isolated test databases; confirm configuration first. Never reset the active demo or preserved legacy database.

## Claude Code and Codex workflow

1. Inspect Git status, scope and docs/TASKS.md. Reserve one task and its files there before editing.
2. Only one writer uses a shared checkout. Do not spawn agents unless explicitly requested. For authorized concurrent work, use separate worktrees and non-overlapping tasks; database changes still need isolated databases.
3. Preserve unrelated edits. Review the diff and run checks appropriate to the change. Review may be read-only; integrate changes serially through one owner.
4. Record behavior, exact check results, limitations and remaining work in docs/TASKS.md; release the reservation. Claim independent review only when it actually occurred.
5. Keep these five docs aligned. Do not recreate separate overlapping plans, prompts or guides without a concrete need. If scope lacks approval, complete the planning deliverable and wait.
