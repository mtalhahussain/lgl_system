# Project requirements, architecture and operations

## Product and current analysis

A small institute administration monolith, preserving Laravel 12, PHP 8.2+, Eloquent, Blade/Bootstrap and MySQL-compatible storage. Local verification used PHP 8.2.12 and MariaDB 10.4.32. The shared shell, three dashboards and login use compiled Tailwind 4/Vite assets and a navy/purple visual theme. Existing Bootstrap/Font Awesome CDN assets remain for compatibility. Tailwind utilities use the tw: prefix without Preflight; npm ci and npm run build prepare assets. Existing declared frontend dependencies are now installed and pinned in package-lock.json; no new package was added to package.json. Database sessions/cache remain infrastructure; no persistent workers are needed. One business scheduler task exists: `batches:sync-status` daily at 00:05, which needs the host cron entry `* * * * * php artisan schedule:run`.

The original project mixed academic functions with payroll, device integrations and multiple financial writers. Its legacy demo had 24 users, including an Accountant, and 39 inconsistent enrollment balances. Those records were preserved rather than converted into invented payments. The active clean demo uses canonical services and reconciles. Local implementation is complete; general operational-data migration and production acceptance are separate work.

## Essential modules and cleanup decisions

| # | Functional group | Decision and current behavior |
|---|---|---|
| 1 | Authentication and accounts | Keep; three roles, login throttling, active-session checks and last-Admin protection |
| 2 | People and self-profile | Merge Student/Teacher administration around users; deactivate to retain history |
| 3a | Teacher payroll | Each teacher is fixed monthly salary or per student per batch (course teacher amount × active+completed students, once per batch). Admin generates immutable dues, records capped idempotent payouts with slips, reverses payouts once with reason and voids unpaid dues; Teacher sees own dues/slips read-only |
| 3 | Courses and batches | Keep; one assigned Teacher, capacity, dates and manual meeting links. Upcoming/ongoing/completed follow dates (end date counts as ongoing); only cancellation is manual; a completed batch completes its active enrollments (and moving it back reactivates them), leaving fees payable; Admin dashboard shows batches starting or ending within 2 days |
| 4 | Sessions and timetable | Keep; schedule actual class sessions and cancel without erasing history |
| 5 | Admissions and enrollments | Improve; transactional registration, fee snapshot, restricted transfer and withdrawal |
| 6 | Fee plans and ledger | Merge financial writers into one payment boundary; allocations, receipts and full reversals |
| 7 | Attendance | Keep manual marking; shared validation, correction audit and completeness |
| 8 | Dashboards | Keep one focused portal per role with scoped data |
| 9 | Reports | Keep only enrollment roster, collections, dues and attendance summaries |
| 10 | Institute settings | Keep validated identity/logo/currency settings |

Legacy payroll code/tables, staff leave, expenses, exams/results, certificates/letters, CRM, tasks/materials, bulk imports, branch isolation, hardware/biometrics, Zoom/WhatsApp integrations and notification delivery are deferred. Their legacy files/tables are retained. Deferred route/scheduler entry points were disabled; absence/reminder commands refuse execution by default. No automatic absence, wallet, payment gateway, refund automation, paid transfers or partial reversals is included. Dormant API routes are unregistered; old API examples are not a supported contract.

## Roles and dashboards

Only stored roles `admin`, `teacher`, `student` are allowed. Admin absorbs accounting/admission/institute management. There is no Accountant or dynamic permission builder. Unsupported historical accounts must receive an explicit reviewed mapping before an operational migration; never silently promote them.

| Capability | Admin | Teacher | Student |
|---|---|---|---|
| Accounts, role assignment and deactivation | Yes; protect last active Admin | No | No |
| People, courses, batches and sessions | Manage | Assigned academic records only | Own timetable/enrollment only |
| Basic profile/password | Own; administer accounts | Own | Own |
| Admission, fee plans and financial writes | Yes | No | No |
| Financial records and receipts | All | No | Own only |
| Attendance | Manage/correct | Assigned eligible sessions | Own read-only |
| Global reports and institute settings | Yes | No | No |

Admin dashboard shows active academic counts, period collections net of reversals, debt and recent admissions. Teacher sees assigned batches, upcoming sessions and attendance work. Student sees own enrollment, timetable, attendance, dues and receipts. Restrictions apply to queries, fields and aggregate totals as well as pages.

## Core workflows and invariants

1. Admin creates courses, Teachers and batches, then admits a Student. Admission locks the batch, checks duplicate/capacity eligibility, and creates the enrollment and schedule atomically. Later course price edits do not rewrite fee agreements.
2. Gross less discount is the exact net agreement. Support one installment, up to 12 equal monthly installments with deterministic remainder and safe month-end dates, or custom amounts/dates summing exactly to net. Money uses integer minor-unit calculations and DECIMAL storage.
3. Admin posts cash/bank/card/JazzCash/Easypaisa as recorded payment methods. Partial/early payment allocates against debt. Reject overpayment and unreconciled legacy balances. Lock the enrollment, validate the unique submission key/payload, and create payment, allocations, unique receipt and audit atomically. An identical retry returns the same receipt; conflicting reuse fails.
4. Posted payments and allocations are immutable. Admin may fully reverse once with reason and actor; preserve originals and restore debt. Collections report records payments in their payment period and negative reversals in their reversal period. Receipt snapshots preserve identity, course/batch, institute, currency and timezone. Currency freezes once payments exist.
5. Transfer only between same-course batches with capacity and no financial activity. Link the retained source to its target, move the schedule once and avoid duplicate debt. Withdrawal retains agreement, debt, payment history and a reason; it does not issue a refund.
6. Teacher or Admin marks eligible Students for a session, never a future date. Blank stays unmarked. Corrections require a 5–1000 character reason and audit. Attendance percentage is `(present + late) / (present + late + absent)`; excused/cancelled records are excluded and an empty denominator has no percentage. Show completeness separately. Historical eligibility accounts for completed/dropped/transferred enrollment dates.
7. Student views only their own portal and receipts. Password change requires the current password. Account/profile input cannot escalate role or active state. Deactivation/cancellation preserves history.

Lists/reports paginate. Responsive menus, keyboard navigation and printable HTML receipts/reports reuse existing layouts. Collection printing clearly describes current-page rows versus full-period totals. No mandatory PDF/Excel package is needed; browser QA generated a print PDF as evidence only.

## Architecture and database

Web/session middleware checks active accounts and coarse roles; policies and scoped queries enforce record/field/aggregate access. Controllers authorize/validate then delegate to existing services: EnrollmentService, FeeCalculatorService, PaymentService, AttendanceService and OverviewService. Keep one application and database; use pagination, eager loading and justified indexes rather than extra platforms.

| Data | Purpose and constraints |
|---|---|
| users | Single role, unique email, contact/profile fields, active flag; retain unused legacy columns |
| courses, batches | Fee defaults, course/Teacher relationships, capacity, dates and meeting fields |
| enrollments | Gross fee, discount, status, source-transfer link; paid_amount is reconciled cache only |
| fee_installments | Scheduled amounts/dates are fixed except an Admin discount change, which moves the difference through unpaid amounts of the latest installments (paid amounts never change; reason and audit required); allocation-derived balances/status |
| payments | Enrollment, amount, method/reference, actor/time, unique submission key/payload and receipt snapshot |
| payment_allocations | Explicit payment/installment amounts; same enrollment and exact payment conservation |
| payment_reversals | One full reversal per payment with actor, timestamp and reason |
| fee_refunds | Refund recorded only when an Admin discount change pushes the net fee below what was paid: amount, method/reference, reason, actor/time, unique submission key/payload and slip snapshot; immutable. `enrollments.refunded_amount` caches the total; the schedule keeps covering paid amounts (scheduled = net + refunded). Payments of an enrollment with a refund cannot be reversed |
| audit_logs | Targeted account/admission/finance/attendance evidence; no secrets |
| class_sessions, attendances | Session/student eligibility, unique pair and manual status |
| settings | Validated institute identity/currency; no public secrets |
| Infrastructure/deferred tables | Retain sessions/cache/jobs and historical deferred data |

New migrations restrict roles, create four ledger tables and link transferred enrollments. Ledger foreign keys restrict deletion; application academic deletes deactivate/cancel. Ledger/transfer down methods deliberately refuse to discard history. The pre-existing teacher-info migration has a destructive down() ownership defect: preserve the user's edit and use reviewed forward repair or matching backup recovery.

## Safe verification and recovery

```powershell
php artisan test
php scripts/verify-concurrency.php
php scripts/verify-baseline.php D:/xammppp/mysql/bin
php scripts/report-legacy-balances.php
node scripts/verify-ui.mjs
```

PHPUnit uses forced SQLite :memory: settings and a bootstrap replacing inherited server/environment values. MariaDB checks create uniquely named disposable databases and retain them; baseline verification privately dumps the source, restores separately and compares schema/data hashes before fresh/upgrade checks. Supply the actual database client directory on another machine. Balance reporting is read-only.

Browser checks use installed Chrome/Edge and Node 22 built-ins, loopback plus local-demo guards, a private temporary profile, screenshots and receipt PDF. Optional LGL_BROWSER_BINARY/LGL_UI_URL overrides are supported. Do not deploy private browser profiles or test artifacts. Physical devices, cross-browser behavior, real printers and independent review remain unverified.

Do not rerun scripts/prepare-local-demo.php merely to test: it intentionally creates another retained demo, backs up .env and switches the local database. Never use migrate:fresh, db:wipe or demo seeders on operational records. Restore a backup into a NEW compatible database, verify schemas/totals and matching release, then review activation. Preserve the previous code/database pair; never restore over new financial activity without reconciliation. Evidence paths are in [TASKS.md](TASKS.md).

## Owner-managed VPS handoff

The owner will set up the server/project. Agents have no deployment assignment or server-access requirement.

1. Verify compatible PHP/extensions/database, domain, permissions and isolated staging. Install locked production dependencies using composer install --no-dev --prefer-dist --optimize-autoloader; build frontend assets with npm ci and npm run build on the build machine and include public/build in the release; confirm CDN assets load.
2. Set production secrets privately, disable debug, use HTTPS/secure cookies and aligned app/database timezones. Preserve the app key for an existing deployment; generate only for a new installation.
3. Back up database/configuration/uploads and restore-test. Validate migrations on staging first. Real-data cutover requires explicit role mapping and authoritative balance reconciliation; the clean-demo script is not an operational migration tool.
4. Provision a trusted non-demo Admin. Verify access, payment/reversal/attendance/report totals and storage/logo permissions. Keep SQL, environment copies and private output outside public storage/releases.
5. Review staging, backup/rollback and responsive/print results before activation. Add the `schedule:run` cron entry for `batches:sync-status`; no persistent worker is required and deferred cron jobs stay disabled. Arrange actual host backups separately.
