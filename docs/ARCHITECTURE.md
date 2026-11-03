# Target architecture and database plan

Status: implemented local baseline; see ACCEPTANCE.md. The stack remains Laravel 12/PHP 8.2+, Eloquent, Blade/Bootstrap, Vite and MySQL-compatible storage. No runtime dependency was added. The active clean demo has three roles and ledger data; the original DB is preserved, not migrated in place. General operational-data cutover remains a separate task.

## Application boundaries

Browser requests use existing web/session routes. Role middleware handles coarse access and active-account checks; policies handle resource permissions. Queries must also restrict rows and fields before rendering lists, statistics and exports. Small request-validation classes are appropriate for complex admission/payment operations, not a requirement to add every layer to every screen.

Controllers validate/authorize and delegate mutations to services. `EnrollmentService` owns admission/transfer/withdrawal orchestration; `FeeCalculatorService` handles exact fee plans; the focused `PaymentService` owns posting/allocation/reversal transactions and reconciliation; `AttendanceService` owns manual marking/statistics. `OverviewService` reuses ledger sources for dashboard totals. Model relationships remain reusable. Legacy payment writers are blocked/delegated, and dormant APIs remain unregistered.

Keep a single database and application. Paginate lists, use existing indexes and eager loading, then add indexes justified by actual core queries. Do not introduce branch global scopes, dynamic permissions, integration jobs or an event platform for deferred modules.

## Proposed schema

| Entity | Reuse or incremental change | Invariants |
|---|---|---|
| `users` | Keep single role field, contacts and active flag; safely migrate enum to three values after reviewed mapping | Preserve IDs; unique email; no silent Accountant-to-Admin promotion; protect last active Admin. Retain unused salary/biometric columns for now. |
| `courses` | Keep existing structure and fee default | Price changes affect future agreements only. Archive/deactivate referenced courses. |
| `batches` | Keep course/teacher FK, dates, capacity and meeting fields | One Teacher-role assignee; capacity positive; no deletion that cascades historical enrollments/payments. |
| `enrollments` | Keep agreement and status fields; document `total_fee` as gross, `discount_amount` once, net derived; later add source-enrollment link for approved transfers if needed | Gross/discount nonnegative; discount no greater than gross; retain existing batch/student uniqueness until re-enrollment is explicitly designed. `paid_amount` is transitional cache only. |
| `fee_installments` | Keep schedule amounts/dates; stop treating rows as receipts | Schedule sum equals net agreement; partial payments do not rewrite original amount; status is derived from allocations and due date. |
| `payments` (new) | Enrollment FK, positive DECIMAL(12,2) amount, method, reference, paid timestamp, received_by FK, unique receipt_number, unique submission key, created timestamp | Immutable posted record. One payment belongs to one enrollment. Store no card credentials. Receipt can use a unique DB-generated payment ID format; no separate sequence engine unless legally/operationally required. |
| `payment_allocations` (new) | Payment FK, installment FK, positive DECIMAL(12,2) amount, unique payment/installment pair | Same enrollment on both sides; allocations sum to payment; no over-allocation after reversals. Enforce cross-table rules inside locked transaction. |
| `payment_reversals` (new) | Unique payment FK, reason, reversed_by FK and timestamp | Full reversal only initially; original payment/allocations remain. Net collections exclude reversed payments; replacement is a new payment. Refund/disbursement handling is deferred. |
| `audit_logs` (new, targeted) | Actor, action, subject type/ID, timestamp, reason and minimal before/after values | Capture role/account changes, admission/status changes, fee plan/payment/reversal and attendance correction; append-only application access, Admin read-only UI if required. Never store secrets or fingerprint payloads. |
| `class_sessions`, `attendances` | Reuse schema and unique session/student pair; add correction actor/time only if audit records are insufficient | Authorized batch; student was eligible for session date; manual marking; canceled sessions excluded from summaries. |
| `settings` | Reuse existing key/value and public flag | Admin writes; public values contain no secrets. Keep institute identity/currency only in baseline UI. |
| Deferred domain tables | Keep as-is and exclude from active product surface after approval | No DROP, truncation, reseeding or retrospective reinterpretation. |

Laravel sessions/cache/jobs/password reset tables remain infrastructure. Existing financial foreign keys often cascade; propose forward migrations to restrict deletion of financial/history owners rather than relying only on UI controls.

## Transaction and money rules

Use DECIMAL storage and integer minor-unit arithmetic at calculation boundaries; do not calculate monetary values with binary floats. Distribute installment rounding remainder deterministically so the exact schedule sum is maintained. Increasing decimal precision, if needed, is an additive migration decision rather than a rewrite of old migrations.

Admission locks the target batch, checks role/status/capacity/duplicate enrollment, and creates all records in one transaction. Payment locks the enrollment and relevant schedule rows, checks remaining debt and submission key, posts payment and allocations, assigns the unique receipt and appends audit evidence atomically. A retry returns the original result only when its submitted payload matches; reused keys with different amounts fail. Reversal locks the same account, allows only one full reversal and updates derived balances atomically. Validate out-of-order/concurrent requests in tests.

Keep one authoritative paid total: posted payments less reversals. Derive due balances from installment allocations. If retaining `enrollments.paid_amount` temporarily for old screens, update it transactionally and reconcile it against the ledger; it must not become another independent writer.

## Migration and reconciliation sequence

The following sequence describes a future operational-data migration. Local implementation instead used the owner's confirmed demo-data decision: preserve `lgl_system` unchanged, generate a new correctly reconciled demo with canonical services, and switch `.env` only after verification. No unsupported account was promoted or historical payment date fabricated. Current evidence and DB names are in ACCEPTANCE.md/RUNBOOK.md. New ledger foreign keys restrict deletion; existing academic application deletes now deactivate/cancel and preserve history.

1. Snapshot database/files and verify restoration on an isolated copy. Inventory actual deployed schema, role counts, financial records and deferred-module usage; local counts are not production clearance.
2. Run fresh-install and upgrade checks on disposable MySQL/MariaDB databases. Investigate overlapping teacher-info migrations; do not overwrite the existing local migration edit or roll back the current database.
3. Add ledger tables and compatible code paths. Produce a discrepancy report linking legacy enrollment totals, paid installments and transfer chains; owner decides which historical evidence is authoritative.
4. Backfill only approved, identifiable historical payments. Preserve source IDs/mapping and distinguish imported records; do not invent payment dates, receipts or methods when evidence is absent. Unresolved accounts remain blocked from financial cutover.
5. Compare per-enrollment and global totals before/after. Cut over all fee writers and report readers together for reconciled accounts. Keep legacy fields until acceptance and a later cleanup decision.
6. Migrate reviewed roles while values are still supported, verify no Accountant remains, then restrict the enum and remove active role references. Preserve original mapping for recovery.

Rollback is release-specific: restore compatible code or use a compensating forward migration. Once real payments are posted, never roll back by dropping ledger tables. Document reconciliation and restore strategy before deployment.

## Hosting boundary

Retain existing hosting until deployment requirements are confirmed. Shared hosting/cron is an earlier planning assumption, not a verified target. Baseline does not require persistent workers or external device/network calls. Disable deferred scheduled business commands only in the approved implementation phase. Keep secrets in environment configuration and avoid printing `.env` contents. Test database isolation must be verified before migrations or business tests run.
