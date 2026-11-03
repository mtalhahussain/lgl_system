# Implementation roadmap

Status: the owner subsequently approved automatic local implementation and confirmed demo-only data. P00–P14 local results and P15 local checks are recorded in TASKS.md/ACCEPTANCE.md. The original database remains unchanged and a clean demo is active. Production hosting/activation and physical cleanup are not complete or authorized. Estimates and decision defaults below are retained as initial planning context, not a claim of production delivery.

## Approval decisions

| ID | Proposed default | Effect if changed |
|---|---|---|
| D01 | Approve the three-role core in REQUIREMENTS.md; defer payroll, exams, certificates, expenses, CRM and integrations | Add only specifically required features with tasks, estimates and acceptance criteria. |
| D02 | One organization-wide Admin; defer branch structure/access boundaries | If NIPA/North Nazimabad isolation is essential, revise ownership/schema and permission tests before implementation. An Online batch is a delivery mode, not automatically a branch. |
| D03 | One teacher per batch, manual attendance, manual meeting links | Shared teaching or hardware/integration requirements need separate designs. |
| D04 | Printable receipts, no overpayment wallet, no automated refunds, no paid-transfer automation | PDF/legal receipt numbering, credit/refunds or paid transfers require explicit rules before the related task. |
| D05 | Review the existing Accountant account; promote only if full Admin authority is appropriate, otherwise deactivate | Named-account mapping is required before role migration. Do not infer permission escalation from baseline approval alone. |
| D06 | Preserve financial history and reconcile the 39 local mismatches before cutover | Owner must identify authoritative evidence for unresolved balances; no automatic overwrite. |
| D07 | Withdrawal preserves debt until an approved adjustment; freeze/unfreeze deferred | If operations need freeze or debt cancellation, agree states and accounting effects before enabling them. |
| D08 | Confirm actual deployment target and whether legacy records are demo or operational | Determines backup, import, migration and deployment checks. No assumption that local sample-looking records are disposable. |

Approval can accept defaults and name exceptions. D05, D06 and deployment-specific details can remain task blockers while independent approved work proceeds. No database mutation or deletion is authorized by this document itself.

## Delivery order

| Phase | Tasks | Depends on | Output and exit gate | Effort range |
|---|---|---|---|---|
| 0. Baseline | P00 | Owner review | Record approved scope/exceptions and role/data/hosting decisions | Owner-dependent |
| 1. Safety and reproducibility | P01–P03 | P00 | Isolated database, backup/restore proof, baseline route/schema checks, no cross-role/global-data leaks | 2–4 engineer-days |
| 2. Three roles and core records | P04–P06 | P01–P03; D05 for P04 | Exactly three active role paths; people/course/batch/session screens pass permission and workflow tests | 2–4 engineer-days |
| 3. Admissions and fees | P07–P11 | P06; D04/D06/D07 for relevant flows | One admission path; reconciled ledger; idempotent receipts/reversals; no duplicate debt or lost history | 5–8 engineer-days |
| 4. Attendance and portals | P12–P14 | P06/P07; P11 for financial summaries | Manual attendance and three dashboards with correctly scoped, reconciling summaries | 2–4 engineer-days |
| 5. Release and optional cleanup | P15–P16 | P11–P14 | Acceptance evidence, restore/deployment runbook, sign-off; optional cleanup reviewed separately | 2–3 engineer-days |

Estimate: 13–23 engineer-days after approval, excluding owner decisions and unexpected data repair. Re-estimate after P01. Concurrent agents do not halve elapsed time because access control, schema and financial cutover are shared dependencies.

## Risk order and practical task sizes

First fix data access boundaries and establish a disposable database baseline. Next simplify role routing and stabilize reused screens. Then repair admission/fee writes and reconciliation before trusting dashboards. Attendance and portal polish follow. Split each register task into small file-owned subtasks when it exceeds one reviewable change; do not mix migration repair, role mapping and payment cutover in one diff.

Only cosmetic polish can slip without approval. Never cut permission, financial reconciliation, concurrent payment or restore checks to meet a date. A missing external integration is not a reason to add a stub or dependency for deferred scope.

## Release acceptance

- Wrong roles and wrong records are denied, including direct requests, lists and aggregates; Teacher fields exclude fees and unrelated enrollments; Student sees only self.
- Named Accountant mapping is reviewed; no unsupported roles remain in DB, seeders, routes, policies, helpers or navigation.
- Admission cannot exceed capacity or leave partial records; duplicate admission is rejected.
- Fee schedules sum exactly, discounts apply once, partial/early payments work, overpayments fail, duplicate/concurrent submissions cannot double-charge, and full reversal preserves original receipt evidence.
- Existing balances reconcile or remain explicitly blocked with an owner-approved exception process; course-price changes do not rewrite agreements; transfers/withdrawals obey approved restrictions.
- Attendance validates membership, assignment and status; correction history is retained and percentages match the documented denominator.
- Dashboards and reports reconcile to canonical financial and academic queries; deferred modules are not advertised as operational.
- Fresh install and upgrade pass on a disposable compatible database; backups restore; release rollback preserves posted history; no production demo credentials.

Record exact tests, environment and results in a future `docs/ACCEPTANCE.md`. It is a release artifact to produce during P15, not evidence already obtained.
