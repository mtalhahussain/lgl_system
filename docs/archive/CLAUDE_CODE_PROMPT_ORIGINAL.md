# Claude Code Prompts — LGL ERP

Put these files in your repo first:
- `docs/LGL_ERP_Complete_Features.docx` (or a .md copy of it)
- `docs/TASKS.md`

---

## Prompt 1 — Audit + Plan (run once, Day 1)

```
You are working on an EXISTING Laravel project: the LGL ERP for Land of German Language
(branches: NIPA, North Nazimabad, Online).

Read these first:
- docs/LGL_ERP_Complete_Features.docx (the full spec; source of truth)
- docs/TASKS.md (module-wise task list with a 20-day schedule)

Do NOT write feature code in this step. Your job:

1. Audit the codebase: routes, controllers, models, migrations, views, packages
   (composer.json), auth, and any existing roles/permissions.
2. Update docs/TASKS.md: mark each task [x] done, [~] partial, or [ ] not started.
   For every [~] add a one-line note of what is missing.
3. Create docs/PLAN.md with:
   - Current stack and architecture summary (Laravel version, UI stack, packages)
   - Database schema plan: new tables and changes to existing ones, per module
   - Key design decisions for fee ledger, installment allocation, receipts,
     and teacher payout snapshots (locked, auditable)
   - Risks and any conflicts between the spec and the existing code
   - Revised 20-day schedule based on what already exists
4. Create CLAUDE.md with project rules (see below) so they apply to every session.

Ask me before deleting or rewriting any existing module.

Project rules for CLAUDE.md:
- Follow existing code conventions, folder structure and UI components.
- Every module: migration, model, policy, form request validation, controller,
  views, feature tests.
- Every record is branch-scoped; enforce access with policies, never only in the UI.
- Money: store as integer paisa or DECIMAL(12,2), never float. Wrap payments in DB transactions.
- Never update or delete a saved receipt or approved payout; use reversals/adjustments
  with reason, approver and audit log.
- Secrets (Zoom, WhatsApp) only in .env. Never log tokens.
- Target shared hosting: scheduler via cron, no persistent queue workers.
- After each task: run tests, update docs/TASKS.md, and give me a 3-line summary.
```

---

## Prompt 2 — Daily build (reuse every day, change the day number)

```
Read CLAUDE.md, docs/PLAN.md and docs/TASKS.md.

Today is Day [N]. Work on the modules scheduled for Day [N] in docs/TASKS.md.

For each open task, in order:
1. Briefly state what you will change (files, migrations).
2. Implement it following CLAUDE.md rules.
3. Write or update feature tests, including permission tests
   (wrong role and wrong branch must be denied).
4. Run `php artisan test` and fix failures.
5. Mark the task [x] in docs/TASKS.md.

When the module is finished, check its "Acceptance" items and report:
- Done tasks
- Anything blocked or needing a client decision
- What is next for Day [N+1]

If a task is blocked by a client dependency (B-01 to B-05), build it behind a
config flag or stub, mark it [~] with a note, and continue.
```

---

## Prompt 3 — Resume after a break or new session

```
Read CLAUDE.md, docs/PLAN.md and docs/TASKS.md.
Run `git log --oneline -15` and `php artisan test`.
Tell me in 5 lines: what is done, what is partial, current failing tests,
and the next task. Then wait for my go-ahead.
```

---

## Prompt 4 — Fee & payout verification (Day 8 and Day 11)

```
Write and run feature tests that prove these scenarios for M05 and M07:
- Lump-sum payment; 3-installment plan; partial payment; advance payment;
  discount; adjustment with approval; overpayment blocked; duplicate receipt blocked.
- Teacher payout: 20 eligible students × PKR 2,000 = PKR 40,000 under each
  eligibility policy (all / any payment / fully paid only).
- Approved payout snapshot does not change after a later student payment.
- Partial disbursement shows correct paid and remaining amounts.
Report any scenario that fails and fix it.
```

---

## Prompt 5 — Final acceptance (Day 19)

```
Go through the "Acceptance checklist" in docs/LGL_ERP_Complete_Features.docx and
every "Acceptance" item in docs/TASKS.md. For each item: test it, then mark
PASS / FAIL with evidence (test name or steps). Save the results to
docs/ACCEPTANCE.md. Fix all FAIL items, starting with security and fees.
```
