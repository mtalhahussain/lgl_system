# Existing implementation audit

Historical pre-implementation snapshot. Findings below were retained as original evidence; see ACCEPTANCE.md and TASKS.md for fixes and current local behavior.

## Scope and confidence

Reviewed the available `/docs` content, `CLAUDE_CODE_PROMPT.md`, `EXAMPLE_QUERIES.md`, dependency manifests, bootstrap, routes, controllers, models, policies, services, migrations, seeders, views and tests. The only original file in `/docs` was `TASKS.md`. Its referenced `LGL_ERP_Complete_Features.docx` is missing; no claim of reviewing that source is made.

The local configured database was inspected with SELECT, SHOW TABLES and SHOW COLUMNS only. No business records, schema, credentials or device state were changed. Local observations are not proof of production state. Existing code is reusable material, not certified working functionality.

## Stack and architecture

- PHP requirement `^8.2`; local CLI 8.2.12; Laravel requirement `^12.0`.
- Eloquent MVC monolith, session authentication, Blade templates, custom role middleware and six policy classes. No permission package is declared.
- Actual admin UI uses Bootstrap 5.3 and Font Awesome via CDN. Vite/Tailwind 4/axios are declared in `package.json`; that does not make Tailwind the main rendered UI.
- `.env` selects the MySQL driver; the connected local server identifies as MariaDB 10.4.32. Sessions, cache and queues use database drivers.
- `bootstrap/app.php` loads web and console routes, not `routes/api.php`. Sanctum is referenced by dormant API routes and example docs but is not declared in `composer.json`.
- Business behavior is divided between controllers, services and model helpers, with repeated enrollment, fee, attendance, dashboard and payroll implementations.

## Database snapshot

The local database contains 23 tables: 8 Laravel infrastructure tables and 15 domain tables: users, courses, batches, enrollments, fee_installments, class_sessions, attendances, teacher_leaves, teacher_earnings, exams, exam_results, certificates, settings, batch_teacher_earnings and teacher_payments.

| Observed item | Local count |
|---|---:|
| Users | 24: 1 Admin, 2 Teachers, 20 Students, 1 Accountant |
| Courses / batches | 6 / 4 |
| Enrollments / fee installments | 39 / 39 |
| Attendance records | 0 |
| Teacher earnings / batch teacher earnings / payments | 0 / 0 / 0 |

Twenty-one migrations are recorded as applied. Users still have the four-value role enum. There is no branch table or branch ownership column in the reviewed application migrations. Teacher salary and biometric columns already exist. Historical records must be preserved even when a module is deferred; zero rows locally do not prove zero rows elsewhere.

## Findings with implementation evidence

| Priority | Evidence | Consequence and planned response |
|---|---|---|
| P0 | `routes/web.php` exposes `/reports` and `/reports/{type}` under authentication only; `ReportsController` applies no role authorization | Any authenticated role can reach global report logic. Restrict financial/global reports to Admin and test direct requests. Some reports currently fail later for independent schema/view defects. |
| P0 | A read-only aggregate found all 39 `enrollments.paid_amount` values differ from their summed paid installment amounts | Balances have competing sources of truth. Investigate seed history, transfers and all write paths before selecting corrected values; do not overwrite either total automatically. |
| P0 | `FeeController::pay` marks an installment paid without updating enrollment totals; `FeeInstallment::markAsPaid` increments them without a repeat-payment guard | Payment paths can disagree or double-count. Consolidate into one transactional, locked, idempotent payment operation. |
| P0 | `FeeCalculatorService::processPayment` rewrites installment amounts for partial payments; no immutable receipt/payment ledger exists | Payment history and scheduled debt are conflated. Preserve evidence and introduce payment/allocation records before removing legacy writes. |
| P1 | Loaded routes reference missing `EnrollmentController::edit`, `update`, `destroy`, `withdraw`; controller has a separate `drop` method | Four declared enrollment routes cannot execute their actions. Retain only approved actions and align route methods and views. |
| P1 | `/attendance/{session}` is declared before `/attendance/reports` | Static report path risks being captured as a session. Order static paths first and add a route regression test. |
| P1 | `InstallmentController::store` reads `$enrollment->batch->course->fees`; course schema uses `total_fee`; admission fee logic also exists elsewhere | Use the agreed enrollment snapshot and one schedule generator, with rounding and transaction-boundary checks. |
| P1 | `ReportsController` references `paid_at`, `amount_paid`, and teacher earning `amount`; migrations define different names. `admin.reports.*` detail templates are absent | Existing report shell is reusable; report queries and detail pages are incomplete. Rebuild only core reports from canonical records. |
| P1 | `SalaryCalculatorService::generateTeacherEarning` writes `user_id`, `base_salary`, `bonus`, `deductions`, `calculation_details`, unlike the `TeacherEarning` model/schema | Payroll is not a reliable completed flow. Defer payroll and prevent accidental exposure rather than extending both systems. |
| P1 | `teacher_earnings` and `batch_teacher_earnings`/`teacher_payments` implement separate payout structures | Do not choose a financial model from naming alone. Preserve both and design one only if payroll is later approved. |
| P1 | Student detail loads all enrollments and fee records once a teacher has any qualifying enrollment; attendance summary statistics include global counts | Record-level checks alone do not guarantee field or aggregate privacy. Scope both queries and returned fields to the teacher's assigned batches. |
| P1 | `EnrollmentService::transferStudent` copies paid totals to a new enrollment and moves pending installments | Transfer history can duplicate financial totals. Block financial transfer rollout until an explicit account-linking approach is tested. |
| P1 | `Attendance::getAttendanceStats` reuses a query after adding status filters | Later counts accumulate incompatible predicates. Compute independent counts and define the attendance percentage denominator. |
| P1 | Teacher-info migration has its `up` additions commented out, but `down` drops columns already created by the salary migration | Rollback can remove columns this migration did not create. Preserve the user's local edit and test a safe forward repair on a disposable database. |
| P1 | Login regenerates the session and rejects inactive users, but no login throttle is declared; role middleware does not check active status on subsequent requests | Add throttling and active-account enforcement with regression coverage. Demo seeder credentials must not become deployment accounts. |
| P2 | Exam/result/certificate models and migrations exist without corresponding routed CRUD controllers/views; certificate PDF method returns a placeholder | These are scaffolding, not delivered modules. Defer rather than build them merely because tables exist. |
| P2 | Hikvision service, fingerprint routes and auto-absent scheduler exist | External hardware and automatic attendance are operational complexity; recommend manual attendance for the first release. Do not contact hardware during this audit. |

## Verification and limits

- `php artisan route:list --except-vendor --json` succeeded. Reflection over loaded controller routes identified the four missing actions above; no HTTP mutations were sent.
- `php artisan test`: 2 tests passed, 2 assertions. They cover `true === true` and the login page response only. No business, permission, finance or migration confidence follows from this pass.
- Database counts, role distribution, migration history and the balance mismatch were read directly. No row-level personal data was exported.
- Full UI workflows, concurrent payments, fresh migration/rollback, deployment and third-party integration behavior were not executed. Future baseline work must verify them in isolated environments.
- Existing local changes to `composer.lock` and `database/migrations/2026_01_20_170942_add_teacher_info_fields_to_users_table.php` predate this audit and are outside the documentation change. The original prompt and docs were untracked; their original contents are preserved under `docs/archive` before revision.
