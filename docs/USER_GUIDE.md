# LGL ERP quick guide

The active local system has three roles: Admin, Teacher and Student. The default local preview is `http://127.0.0.1:8791` while its development server is running.

## Admin

1. Create or activate a course. Its fee is the default for future admissions, not a live price applied to existing agreements.
2. Add a Teacher under Teachers, then create a batch with that teacher, dates, capacity and delivery mode. Meeting links are entered manually.
3. Add a Student with an initial password. Registration can optionally include immediate enrollment. Alternatively, use Enrollments to enroll an existing student.
4. Choose a discount and equal monthly installments, or enable a custom schedule and enter amounts/dates totaling the net fee. Admission and its schedule are saved together.
5. Open Fee Management or a student's Fees and Receipts page. Enter an amount, method and optional reference. Partial and early payments are allocated to scheduled debt; amounts above the remaining balance are rejected.
6. Open and print the resulting receipt. To correct a saved payment, reverse it with a reason and enter a replacement if needed. Do not edit its amount or identity details.
7. Use Account Access to manage supported roles and activation. The last active Admin cannot be disabled or demoted. Accounts with academic history keep their role; create a separate account when a person needs a different role.
8. Deactivate people/courses or cancel batches/sessions to preserve history. Withdrawal preserves unpaid debt. Only enrollments with no payment activity can transfer to an available batch of the same course.
9. Use the four reports: enrollments, collections, outstanding dues and attendance. Reversals subtract in the period when they occur. Fees and receipts are not a separate payroll/accounting package.

## Teacher

Open assigned batches, limited student rosters and class sessions. Create or edit sessions for assigned batches, then mark attendance. Blank selections remain unmarked/unchanged. Existing entries require a correction reason when changed. Historical eligibility uses the enrollment/withdrawal/transfer date; unrelated students cannot be marked.

Teachers cannot access student finances, institute-wide financial reports, account administration or institute settings. Use the profile dropdown to edit basic personal details or change your own password.

## Student

The dashboard shows your enrollments, timetable, attendance and outstanding amounts. Open My Fees for your ledger and printable receipts, or My Attendance for your records. Active enrollments can access manually entered meeting join links. You cannot edit enrollment or payment data or read another student's records.

The profile dropdown supports name/contact/address changes. A password change requires the current password. Ask Admin to correct your email or reset access if you cannot log in.

## Attendance meaning

Percentage is `(present + late) / (present + late + absent) × 100`. Excused entries and cancelled sessions are excluded. No marked classes means no percentage. Missing marks are not automatic absences; the Student dashboard shows unmarked eligible sessions through today separately.

Payroll, staff leave, exams/certificates, CRM, expenses, automatic reminders, hardware attendance and Zoom/WhatsApp provisioning are deferred. Their legacy tables/files are retained, but their product entry points are inactive.
