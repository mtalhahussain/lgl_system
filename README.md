# LGL institute management

Laravel 12 / PHP 8.2+ institute management using Eloquent, Blade, Tailwind 4 with Bootstrap compatibility, and MySQL-compatible storage. Exactly three roles: Admin, Teacher and Student. Ten functional groups are implemented locally; production deployment is owner-managed.

## Local use

The owner confirmed on 5 November 2026 that the currently configured local `lgl_system` is the working database. Use additive migrations; do not reset it or overwrite preserved databases/backups to resume work.

```powershell
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8791
php artisan test
```

Use an existing preview at http://127.0.0.1:8791 if already running. Local demo accounts only:

- Admin: `admin@admin.com` / `password`.
- Teachers: `teacher@lgl.com`, `teacher1@lgl.com` / `password`.
- Students: `student1@lgl.com` through `student20@lgl.com` / `password`.

These match the current seeder and checked local demo accounts. Editing the seeder alone does not update existing accounts.

The three role portals and login use a German flag theme: black navigation, red primary actions and gold accents on white surfaces. The dashboard and login load compiled Tailwind/Vite assets. Node 22 is used locally; build assets before serving or packaging a release. Bootstrap remains for existing forms, tables and navigation behavior. Tailwind utilities use the `tw:` prefix without its reset to avoid conflicts.

Demo seeders refuse production. Never use these accounts for production provisioning.

## Documentation

Displayed times across dashboards, class/attendance pages, notifications, machine logs, fee history and printable receipts/payroll slips use 12-hour AM/PM format. Stored times, native form values, calculations and CSV exports retain their existing formats.

Date/time pickers have separate month/year dropdowns, labelled Hour/Minute/AM–PM choices and Today/Now, Clear and Done actions. Dates display DD-MM-YYYY and times display hh:mm AM/PM; submitted values retain existing controller formats. Calendar layouts fit narrow screens and keep existing date limits.

Staff Attendance lists only non-teaching users with the staff role, including the staff selector and daily punch totals. Teacher punch history remains available in the machine punch log; this report does not affect payroll.

Phone and Emergency contact fields accept digits with an optional leading + and preserve leading zeroes. Forms use telephone keyboards and remove letters/pasted formatting; server validation also rejects text. Emergency contact remains optional.

New staff Joining date and Teacher Hire date default to today in the application timezone. Editing preserves the saved date, including an unset date; validation retries preserve submitted input.

Student My Result includes exam-name search, batch/status filters and personal Scheduled, Results available, Passed and Failed counts. Filters show only the student's own records, with a clear reset when nothing matches.

Teacher and Student password fields are optional on create/edit. Leave them blank on creation to set a password later through Admin edit before the person's first sign-in; leave them blank on edit to retain the existing password. Supplied passwords require at least 8 characters and matching confirmation.

- [Project requirements, architecture and operations](docs/PROJECT.md)
- [Task status, verification and recovery evidence](docs/TASKS.md)
- [Shared agent rules and collaboration workflow](AGENTS.md)
- [Claude Code entry point](CLAUDE.md)

These five Markdown files are the current documentation. Deferred modules remain outside the active product. Historical API examples were removed because API routing is unregistered and Sanctum is not installed.

The login page fills the desktop viewport edge to edge and displays the institute logo uploaded in Settings, with an LGL fallback before upload. Its default branding is Land of German Language; Settings can override the institute name.

Admin can sell a multi-level package (2–4 consecutive levels, e.g. A1 + A2, A1 to B1, A1 to B2) from Enrollments > Multi-level package. Admin enters the agreed price per sale (package or student-specific discount), chooses full payment now or monthly installments per level, and the system reserves every seat and splits the price exactly across level ledgers. Normal module-wise admission remains available. Per-student teachers are paid monthly per batch (rate × students that month) from Teacher Payroll > Generate month.

Exam-based institute certificates are the next requested extension. Design is recorded under E01 in docs/TASKS.md; passing-rule clarification is pending and the legacy exam/certificate modules remain inactive.

Student exam announcements open My Result, showing a Scheduled state for ungraded exams in their enrolled batches. A separate Results section appears only after marks are recorded for that student. Existing exam notifications with staff-only exam URLs are adapted when shown in the bell or notification history; stored notifications remain unchanged. The current workflow records marks through Teacher/Admin; it does not provide online question attempts.

Admin batch save now automatically creates regular dated classes from weekly days/times through the batch end date, starting today or the batch start date. Start and end dates are required for every batch; the end date must be on or after the start date. Teachers see assigned batch dates/timings and today’s direct attendance links; use Extra / makeup class only for additional classes. Existing dated classes and attendance are preserved. For an older batch, Admin can edit and save to fill missing future dates.

Class Sessions now separates teacher confirmation from student attendance. Open a class to record Class taken or Class missed with a reason, then use Schedule linked makeup on an original missed class. This week’s teaching shows missed classes as makeup needed, pending or covered; coverage requires the makeup’s explicit Class taken confirmation. The additive teaching-record migration was applied to the owner-authorized local `lgl_system` on 5 November 2026.

Teacher Dashboard class names and dates/times open class details; assigned weekly timing opens the batch-filtered dated class list. For online teaching, Admin can enter one recurring meeting link on the batch, which regular class generation copies into new sessions. Separate meeting URLs require individual session links; meeting creation is manual. Changing a batch link does not overwrite links already stored on existing sessions.

Teacher create/edit uses searchable Select2 dropdowns for qualification, German-teaching specialization and experience (0–50 years). Existing custom values remain available when editing their teacher.

For an unpaid mistaken admission, open Enrollments > View > Correct batch. Admin can choose another available batch of the same course and provide a reason before academic records exist. The original stays linked in history; fees, installments and admission date are preserved. Package admissions and any payment history require a separate reviewed process.

Admin sidebar includes Enrollments below Students for admissions, batch correction, transfers and withdrawals.

For per-student teacher pay, select Per student per month on teacher create/edit and enter Amount per student per month. This teacher's rate applies to students in every assigned batch each month, independent of the course amount. Existing teachers without a positive rate need it configured before new batch dues can be generated; previously generated dues and payouts keep their saved amounts.

Enrollment, class-session, profile, fee-ledger and enrollment/dues report tables show course levels beside course or batch names.

Shared tables place Rows per page at the top left with search on the right; filters sit in a separate compact group below. On mobile, search wraps to a full-width row. Result count and pagination stay below the table. Changing page size preserves filters and sorting and returns to the first page. Financial histories with fixed page sizes retain their existing pagination.

Date/time fields use a locally bundled Flatpickr calendar and AM/PM time picker with the portal's red/gold theme, including dynamically added schedule rows. Displayed and typed dates use DD-MM-YYYY; submitted dates remain YYYY-MM-DD and submitted times remain HH:MM. Existing required fields and date bounds remain enforced. Rebuild assets after frontend changes.
