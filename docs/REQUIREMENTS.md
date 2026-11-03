# Simplified requirements — local baseline

Implemented locally after owner authorization on 31 October 2026. See ACCEPTANCE.md for verification and remaining boundaries. The original demo DB was preserved and a clean three-role demo activated; no legacy Accountant was promoted.

## Product boundary

Deliver a small institute administration system: manage people, courses, batches, admissions, fee collection and manual student attendance. Reuse the Laravel/Blade monolith and existing UI. Exactly three stored roles: `admin`, `teacher`, `student`. No custom permission builder, separate staff hierarchy, public API or integration platform is needed.

Admin absorbs admission, accounting and institute-management responsibilities. Existing Accountant accounts require an owner-reviewed mapping: explicitly promote named users to Admin or deactivate them. Do not automatically grant broader privileges. Super Admin, Branch Admin and Admission Officer are proposed in old docs only; their responsibilities become Admin capabilities without new roles.

Applied local simplifications: one organization-wide Admin scope; one teacher per batch; manual meeting links; manual attendance; no payroll in the baseline. Branch labels and freeze workflows remain deferred.

## Role permissions

| Capability | Admin | Teacher | Student |
|---|---|---|---|
| Create/deactivate accounts and assign roles | Yes; protect the last active Admin | No | No |
| Manage student/teacher profiles | Yes | Own basic profile; assigned students' academic fields only | Own basic profile only |
| Courses and batches | Manage all | Read assigned batches and their courses | Read own enrolled course/batch details |
| Sessions | Create/update/cancel all | Create/update/cancel sessions in assigned batches | Read own timetable |
| Admissions, discounts, withdrawals, transfers | Manage within approved rules | Read assigned roster only | Read own enrollment history |
| Attendance | Mark/correct all with reason | Mark assigned sessions for enrolled students | Read own records |
| Fees, payments, receipts, reversals | Manage with audit trail | No student finances or institute totals | Read own ledger, dues and receipts |
| Reports | Global enrollment, collections, dues, attendance | Assigned batch attendance/roster only | Own summaries only |
| Settings and audit records | Manage settings; read audit | No | No |

Enforce role, record and field access server-side on list, detail, export, write and aggregate queries. Hiding menus is insufficient. Users cannot edit their own role, active flag or financial fields. Deactivated accounts must lose access on the next request. No public self-registration in the first release; Admin creates accounts and uses the existing session login flow with secure credential delivery/reset.

## Core acceptance requirements

1. **People and authentication:** Admin can search, create and edit people; unique email; active flag; minimum necessary contact and emergency details. Add a stable student reference if operations require it. Validate role-specific route bindings. Enforce login throttling, password validation, CSRF and protected account recovery. Deactivate rather than cascade-delete historical users.
2. **Courses and batches:** Keep A1–C2 course levels, fee defaults, duration, active state, teacher assignment, date range, capacity and physical/online mode. Keep manual meeting links visible only to Admin and the assigned teacher/enrolled students. Do not expose host credentials. One teacher per batch; no recurring meeting API.
3. **Admissions:** Create a student if needed, an enrollment and its fee plan in one transaction. Prevent duplicate enrollment and over-capacity admission, including concurrent requests. Store the fee agreement independently of later course price edits. Use existing active/completed/dropped/transferred states; do not add freeze states without approval.
4. **Fees:** Record an agreed gross fee and discount once; net payable equals gross minus discount. Support lump sum as one installment, or a small custom schedule whose amounts sum exactly to net payable. Record cash/bank/card/JazzCash/Easypaisa as payment methods only. Support partial payment and early payment against scheduled debt; reject amounts above remaining debt in the first release. No unallocated wallet or online gateway.
5. **Payment integrity:** Each successful payment has one unique receipt and explicit installment allocations. Preserve original installment amounts. Duplicate submission must not create another receipt or change totals twice. Saved financial records are immutable; Admin corrects them by a linked reversal with reason and actor, then a replacement payment if appropriate. No fee-agreement edits after payment without a separately approved adjustment policy. Printable HTML receipts are sufficient initially; PDF export is deferred.
6. **Attendance:** One record per student per session using existing `present`, `absent`, `late`, `excused` values. Only eligible enrolled students can be marked; canceled sessions do not enter percentages. Percentage is `(present + late) / (present + late + absent) × 100`; excused records are excluded, and an empty denominator displays no percentage. Missing marks are unmarked, not automatically absent. Show attendance completeness separately. Corrections record actor, timestamp and reason.
7. **Dashboards:** Admin sees active students/enrollments/batches, current-period posted collections net of reversals, unpaid/overdue debt and recent admissions. Teacher sees assigned batches, upcoming sessions and attendance needing entry. Student sees own active enrollment, timetable, attendance and outstanding amount. Dashboard amounts must reconcile to the same source as reports.
8. **Reports/settings:** Only enrollment roster, fee collections, outstanding dues and attendance summaries are core. Filter by date and applicable batch/course; paginate. Reuse institute identity/currency settings. Start with screen and print output; no mandatory Excel/PDF dependency or KPI framework.
9. **Quality and operations:** Responsive existing layouts, clear validation, transaction rollback on failure, protected sensitive files, private financial audit records, backup/restore instructions and a tested staging deployment. Preserve data and historical references. No new package without a concrete approved need.

## Primary workflows

- **Admin:** Create course, teacher and batch; register student; agree fees; enroll and generate schedule; collect payment and print receipt; review dues/attendance; complete or withdraw enrollment without erasing history.
- **Teacher:** Log in; open assigned batch/session; review limited roster; mark attendance; correct an entry with reason; view assigned attendance summaries.
- **Student:** Log in; view own batch/timetable, attendance, dues and receipts. Account creation, enrollment changes and financial edits remain Admin operations.
- **Transfer:** Admin checks same-course target and capacity. Preserve source enrollment and payment history. Initially allow only transfers with no financial activity after duplicate-debt handling is verified; paid transfers stay blocked until the owner approves financial carry-forward rules and dedicated tests pass.
- **Withdrawal:** Keep the historical agreement/payments; use a reason and explicit status. Do not silently cancel debt or issue refunds. Handling of remaining debt needs a documented owner decision; no refund automation in the first release.

## Deferred scope

Payroll/staff leave, expenses, exams/results, certificates/letters, CRM, materials uploads, task management, notification delivery, Zoom/WhatsApp integrations, biometric attendance, auto-absence, branch access boundaries, bulk import and universal exports require separate scope approval. Existing records and code remain preserved. A deferral must not be presented as a completed or removed implementation.
