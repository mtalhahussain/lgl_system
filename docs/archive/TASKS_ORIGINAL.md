# LGL ERP — Module-wise Task List (20-Day Delivery)

Source: `LGL_ERP_Complete_Features.docx` (v1.0, 30 Oct 2026)
Stack: Laravel (PHP), responsive UI, MySQL
Branches: NIPA, North Nazimabad, Online

## Status legend
- `[ ]` not started · `[~]` partial / exists but incomplete · `[x]` done and tested
- Task ID format: `M<module>-T<task>` (e.g. `M05-T03`)
- Every module is "done" only when its **Acceptance** items pass.

## Day 1 blockers (start immediately, client-side)
- [ ] B-01 Client confirms payroll policies: payout approval timing, frozen/cancelled eligibility, refund effects, split pay for shared batches
- [ ] B-02 Client creates/approves Zoom account + Server-to-Server OAuth app, confirms licence and concurrent-class capacity
- [ ] B-03 Client applies for WhatsApp Business API (Meta) + submits reminder templates for approval (approval can take days)
- [ ] B-04 Client shares existing Excel files (students, batches, enrollments, staff, fees)
- [ ] B-05 Verify Hostinger staging: PHP version/extensions, Composer, DB, cron, outbound API access, no persistent queue workers

---

## M00 — Project Foundation & Audit
- [ ] M00-T01 Audit existing codebase; mark each task below as `[ ]`, `[~]` or `[x]`
- [ ] M00-T02 Branch model (NIPA, North Nazimabad, Online) and branch scoping on all records
- [ ] M00-T03 Base layout: responsive shell, role-based navigation, shared components (tables with search/filter/sort/pagination, forms, alerts, confirm modals)
- [ ] M00-T04 Global audit-log service (who, what, when, old/new values)
- [ ] M00-T05 Settings module (institute info, logo, receipt prefix, academic levels A1–C2)

## M16 — User Roles & Security (build first)
- [ ] M16-T01 Roles: Super Admin, Branch Admin, Accountant, Admission Officer, Teacher, Student
- [ ] M16-T02 Custom permissions per role (spatie/laravel-permission or existing package)
- [ ] M16-T03 Branch-level data restriction via policies/global scopes
- [ ] M16-T04 Secure login, password reset, rate limiting, session timeout
- [ ] M16-T05 Activity logs viewer (Super Admin)
- [ ] M16-T06 Automated daily DB backup (cron) + restore instructions
- **Acceptance:** students see only own records; teachers only assigned batches; branch staff only their branch; financial screens hidden from non-finance roles

## M02 — Student Registration
- [ ] M02-T01 Student model: unique auto ID, photo, contact, guardian details
- [ ] M02-T02 CNIC/passport fields + document uploads (private storage)
- [ ] M02-T03 Statuses: Active, Completed, Frozen, Cancelled with status history
- [ ] M02-T04 Student profile page linking enrollments, fees, attendance, results, certificates
- **Acceptance:** create/edit/search students; duplicate CNIC/phone warning; documents downloadable only by authorized roles

## M04 — Course & Batch Management
- [ ] M04-T01 Courses A1–C2 + exam prep types (Goethe, ÖSD, telc, TestDaF)
- [ ] M04-T02 Batches: timings, classroom, start/end dates, capacity, mode (online/physical), branch
- [ ] M04-T03 Teacher assignment (supports multiple teachers per batch)
- [ ] M04-T04 Batch teacher payout settings: per-student rate + eligibility policy (see M07)
- [ ] M04-T05 Student transfer between batches and freeze/unfreeze with history
- **Acceptance:** capacity enforced; transfer/freeze recorded with reason and date

## M03 — Admissions & Enrollment
- [ ] M03-T01 Admission form: course/level, branch, mode, batch, teacher
- [ ] M03-T02 Fee setup at admission: admission fee, course fee, discount, lump-sum or custom installment plan
- [ ] M03-T03 Enrollment history per student
- [ ] M03-T04 Confirmation letter PDF
- **Acceptance:** one admission creates student (if new), enrollment and fee plan in a single transaction

## M05 — Fee Collection & Accounts (highest risk — test heavily)
- [ ] M05-T01 Fee plan per enrollment: total agreed fee, discount, installments with amounts and due dates
- [ ] M05-T02 Student ledger: admission fee and tuition fee kept separately identifiable
- [ ] M05-T03 Payment entry: cash, bank, card, JazzCash, Easypaisa (recording only, no gateway)
- [ ] M05-T04 Partial and advance payments with explicit allocation to installments
- [ ] M05-T05 Digital receipts (PDF, unique sequential number, no duplicates)
- [ ] M05-T06 Adjustments with reason + approval + audit trail; never edit historical receipts (reverse/void instead)
- [ ] M05-T07 Overpayment prevention or explicit advance credit
- [ ] M05-T08 Reports: daily, monthly, branch-wise collections, dues, defaulters
- **Acceptance:** lump-sum, installment, partial, advance, discount and adjustment scenarios all reconcile; branch totals match ledger totals

## M06 — Attendance
- [ ] M06-T01 Daily student attendance per batch: Present, Absent, Leave, Late
- [ ] M06-T02 Teacher attendance
- [ ] M06-T03 Batch-wise and monthly reports
- [ ] M06-T04 Low-attendance alerts (configurable threshold)
- [ ] M06-T05 (Future, out of scope) QR attendance — leave extension point only

## M07 — Staff, Teacher & Payroll
- [ ] M07-T01 Staff profiles, assigned courses/batches, working hours
- [ ] M07-T02 Salary structure for non-batch staff: base, bonuses, deductions, salary slips
- [ ] M07-T03 Teacher batch payout: eligible students × per-student rate = base payable
- [ ] M07-T04 Eligibility options: all students / any payment received / fully paid only
- [ ] M07-T05 Bonuses and deductions shown separately
- [ ] M07-T06 Approval creates a **locked snapshot** (teacher, batch, rate, eligible student list, policy, amount)
- [ ] M07-T07 Partial disbursements: approved total, paid, remaining + payment statement PDF
- [ ] M07-T08 Post-approval changes only via authorized, audited adjustment
- [ ] M07-T09 Apply client decisions from B-01 (frozen/cancelled/refund/split pay)
- [ ] M07-T10 Teacher performance notes
- **Acceptance:** example 20 × PKR 2,000 = PKR 40,000 works; later fee payments do not change an approved snapshot

## M08 — Expenses
- [ ] M08-T01 Expense categories, branch, vendor, payment method
- [ ] M08-T02 Receipt attachments and approval status
- [ ] M08-T03 Monthly income vs expense report

## M09 — Examinations & Results
- [ ] M09-T01 Internal tests, mock exams, module-level marks
- [ ] M09-T02 Reading, Writing, Listening, Speaking reports
- [ ] M09-T03 External exam results (Goethe, ÖSD, telc, TestDaF) + certificate history

## M10 — Certificates & Letters
- [ ] M10-T01 Templates: completion, participation, enrollment certificates
- [ ] M10-T02 Letters: recognition, embassy, teaching experience
- [ ] M10-T03 Auto-insert student details, PDF generation
- [ ] M10-T04 Unique verification number + public verification page

## M11 — Leads & Follow-up CRM
- [ ] M11-T01 Lead sources: website, WhatsApp, call, walk-in
- [ ] M11-T02 Interested level, counsellor assignment, follow-up date, remarks
- [ ] M11-T03 Statuses: Hot, Warm, Cold, Converted, Lost
- [ ] M11-T04 Convert lead → admission (prefill form)
- [ ] M11-T05 Conversion reports

## M14 — Tasks & Notifications
- [ ] M14-T01 Staff tasks with deadline and priority; pending/completed views
- [ ] M14-T02 In-app notifications: fee due, notices, batch completion, certificate requests
- [ ] M14-T03 Institute notices (targeted by branch/batch/role)

## M12 — Student Portal
- [ ] M12-T01 Profile, current course, batch, timetable, attendance
- [ ] M12-T02 Fee ledger, receipts, dues, test results
- [ ] M12-T03 Certificates, notices, learning materials
- [ ] M12-T04 Upcoming Zoom classes (join link only for enrolled students)

## M13 — Teacher Portal
- [ ] M13-T01 Assigned batches, schedules, announcements
- [ ] M13-T02 Mark attendance, notes, upload learning materials, enter marks
- [ ] M13-T03 Student performance remarks
- [ ] M13-T04 Payout statement view (own batches only)

## M18 — Zoom Integration
- [ ] M18-T01 Server-side credential config (env, never exposed)
- [ ] M18-T02 Create/update/cancel meetings linked to batch, teacher, timetable
- [ ] M18-T03 Store meeting ID, date, duration, timezone, join info
- [ ] M18-T04 Host start links visible only to assigned teacher/admin
- [ ] M18-T05 Idempotency key + reconciliation to prevent duplicate meetings; visible failure states

## M19 — WhatsApp Fee Reminders
- [ ] M19-T01 Official WhatsApp Business API, tokens in env
- [ ] M19-T02 Student consent record + opt-out
- [ ] M19-T03 Templates: upcoming due, overdue, payment confirmation (amount + due date)
- [ ] M19-T04 Manual send + scheduled processing via cron (no persistent worker assumed)
- [ ] M19-T05 Frequency limits and duplicate prevention
- [ ] M19-T06 Store provider message ID; states pending/accepted/delivered/failed (accepted ≠ delivered)
- [ ] M19-T07 Admin review queue before retrying failed/uncertain sends

## M01 — Management Dashboard
- [ ] M01-T01 Totals: students, active enrollments, batches, branch performance
- [ ] M01-T02 Monthly collection, outstanding, income vs expense
- [ ] M01-T03 Attendance summary, new admissions, leads, alerts, pending tasks
- [ ] M01-T04 Role-specific dashboards (branch admin sees own branch)

## M15 — Reports & Analytics
- [ ] M15-T01 Enrollment, fee collection, outstanding, expense reports
- [ ] M15-T02 Branch, teacher, batch, attendance, payroll, lead conversion reports
- [ ] M15-T03 Excel and PDF export on every report

## M17 — Data Migration
- [ ] M17-T01 Excel import templates (students, batches, enrollments, staff, fees)
- [ ] M17-T02 Validation + duplicate check + error report before import
- [ ] M17-T03 Import payable, received and outstanding balances
- [ ] M17-T04 Reconciliation report: imported totals vs Excel totals

## M20 — UX & Accessibility (apply throughout)
- [ ] M20-T01 Responsive desktop/tablet/mobile
- [ ] M20-T02 Clear validation, success/failure messages, confirm prompts on destructive actions
- [ ] M20-T03 Accessible labels, keyboard navigation, visible focus, readable contrast

## M21 — Deployment & Handover
- [ ] M21-T01 Hostinger staging, separate DB/files from live LGL site, restricted access
- [ ] M21-T02 Cron for scheduler; queue `sync` or `database` driver run by cron
- [ ] M21-T03 Deployment docs + VPS migration plan (files, DB, storage, cron, env, backups, rollback)
- [ ] M21-T04 User guide per role + training session
- [ ] M21-T05 Backup restore test
- [ ] M21-T06 Acceptance checklist results documented; client sign-off

---

## 20-Day Schedule

| Day | Modules | Output |
|---|---|---|
| 1 | M00, B-01…B-05 | Audit done, task statuses updated, blockers sent to client |
| 2 | M16 | Roles, permissions, branch scoping, audit log |
| 3 | M02 | Students complete |
| 4 | M04 | Courses, batches, transfers, freezes |
| 5 | M03 | Admission + enrollment + fee plan setup |
| 6–8 | M05 | Fee plans, ledger, payments, receipts, adjustments, fee reports |
| 9 | M06 | Attendance + alerts |
| 10–11 | M07 | Staff, payroll, teacher payout snapshots |
| 12 | M08, M09 | Expenses, exams/results |
| 13 | M10, M11 | Certificates/letters, CRM |
| 14 | M14, M12 | Tasks/notifications, student portal |
| 15 | M13, M18 | Teacher portal, Zoom |
| 16 | M19 | WhatsApp reminders |
| 17 | M01, M15 | Dashboard, reports, exports |
| 18 | M17 | Data import + reconciliation |
| 19 | QA | Full acceptance checklist, permission tests, mobile test, bug fixes |
| 20 | M21 | Staging deploy, docs, training, sign-off |

**Buffer rule:** if a day slips, cut polish from M01/M15 first. Never cut M05, M07 or M16 testing.

## Out of scope (per spec)
QR attendance, live payment gateway, Zoom recordings/auto-attendance/embedded video, pricing changes.
