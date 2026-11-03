# LGL ERP documentation

Local implementation and acceptance date: 31 October 2026. The owner approved automatic step-by-step implementation of the simplified baseline and confirmed the old data was demo-only. The active local DB has three roles; the original database is preserved unchanged.

Start here:

1. [USER_GUIDE.md](USER_GUIDE.md): current Admin, Teacher and Student workflows.
2. [ACCEPTANCE.md](ACCEPTANCE.md): tests, compatible-DB checks, HTTP smoke, evidence and limits.
3. [RUNBOOK.md](RUNBOOK.md): local demo, backups, recovery and target-specific deployment preparation.
4. [TASKS.md](TASKS.md): completed tasks, decisions, ownership and remaining release gates.

Design/history:

- [REQUIREMENTS.md](REQUIREMENTS.md): approved local scope and permissions.
- [ARCHITECTURE.md](ARCHITECTURE.md): service boundaries, schema and financial invariants.
- [MODULE_CLEANUP.md](MODULE_CLEANUP.md): kept/merged/deferred modules; no physical cleanup performed.
- [PLAN.md](PLAN.md): roadmap and initial decision/estimate context.
- [ANALYSIS.md](ANALYSIS.md): original implementation audit, retained as pre-change evidence.
- [BASELINE.md](BASELINE.md): original P01 backup/isolation evidence, retained as history.
- [AGENT_WORKFLOW.md](AGENT_WORKFLOW.md): shared writer/reviewer coordination.

Repository instructions: `AGENTS.md`, `CLAUDE.md`, `CLAUDE_CODE_PROMPT.md`. The original six-role task list and prompt are in `archive/` and are not executable scope. Their referenced feature DOCX was absent from the checkout. Dormant API examples do not represent the active web interface.

The owner selected a VPS/other server and will perform server/project setup themselves. Hosting and deployment are owner-managed. Local Chrome desktop/mobile, basic keyboard navigation and print-emulation checks now pass. Remote backups, independent review, physical devices/printers and other browsers remain unverified. The local preview is not a production deployment.
