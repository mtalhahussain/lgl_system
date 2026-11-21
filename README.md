# LGL institute management

Teachers can use Exams > New exam with the same name, type, date and four-section marks form as Admin, limited to their currently assigned batches. Server validation and a batch-row lock recheck enforce assignment at creation; notifications go to that batch’s active students and Admin. Existing result grading remains scoped; exam editing retains its prior Admin/authorized Staff permissions. Admin can still turn off the Teacher Exams module.

Admin opens Certificates > Level paragraphs (also linked from Settings) to save one German paragraph for each A1–C2 level. Draft defaults are provided; selected level updates the issue preview. Issue & print automatically selects the server-side level paragraph and saves its wording on the certificate. Later edits affect new issues only; earlier certificates without a snapshot retain the previous original wording. Plain text is limited to 350 characters and checked against the actual PDF signature area before all six settings save atomically. Template editing remains Admin-only, including when Staff may issue certificates. Deploy the additive paragraph_text migration before using the new issue flow.

Certificate print/PDF uses reference-aligned A4 landscape typography: regular Times heading, bundled Poppins body with bold italic greeting/course, German month dates, an 8mm heading gap and a 33mm greeting block with a 6mm gap before the final paragraph. Paragraph text uses 15pt/1.3 line height; PDF-specific Poppins line-box compensation keeps longer text above the signature. Original certificate artwork remains; uploaded logos are excluded. Deploy resources/fonts/certificates and the shared CSS source with PHP views, plus the full public/build assets.

Fee Payment Receipt fits blank upload margins and displays the logo up to 240px wide / 110px high, aligned alongside Amount in the first row, with receipt heading and number below on screen and print. Certificates retain their original template without an uploaded-logo overlay. Logo selection: Payslip / Receipt first, then Login, then Sidebar. Missing/unreadable files fall through to the next logo; without any readable upload, the existing icon and saved institute name remain.

All application JavaScript, including retained legacy screens and Select2 enhancement, is compiled through Vite. Blade supplies escaped data attributes and page-module markers; it contains no executable inline scripts or event handlers. Existing Bootstrap/Chart.js and local jQuery/Select2 vendor script dependencies retain their loading paths. Upload changed views plus the complete public/build directory together and clear compiled views after deployment.

Admin dashboard omits the Batch reminders panel; teacher availability remains. Batch Add class day and date bounds initialize from the compiled Vite bundle; deploy the updated batch form and complete public/build together.

Course create/edit asks only for catalogue details, default fee and duration in weeks; sessions per week and session duration are no longer editable or displayed on active course pages. Historical columns/values remain for compatibility; new rows use existing database defaults. Batch weekly days/times and class generation remain unchanged.

Laravel 12 / PHP 8.2+ institute management using Eloquent, Blade, Tailwind 4 with Bootstrap compatibility, and MySQL-compatible storage. Roles: Admin, Teacher and Student, plus Staff who may sign in with the module actions Admin grants in Users > Role permissions. Ten functional groups are implemented locally; production deployment is owner-managed.

## Local use

The owner confirmed the local working database on 5 November 2026 and clarified on 19 November that current development data is disposable demo data. A deliberate `migrate:fresh --seed` is authorized after substantial changes when useful, after verifying the intended local development target. Never reset production/shared targets or overwrite backups; routine database-writing tests still use isolated databases.

```powershell
npm ci
npm run build
node scripts/verify-built-styles.mjs
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

New batch names are automatic, e.g. `BTC-001-Nov-26`: the numeric part comes from the saved batch ID and the month/year from its start date. The form shows a read-only preview; the server assigns the final number on save. Existing names stay unchanged when editing.

Leads has a default-collapsed Link validity accordion with Copy link plus permanent (recommended for ads), 24-hour and 7-day inquiry-link choices. Expiring links are signed and reject both opening/submission after expiry. All public inquiry submissions share limits of 5 attempts per 10 minutes and 20 per hour per IP, plus the existing honeypot; the same phone creates at most one retained public inquiry across all links, regardless of age or link expiry, with duplicates receiving the same thank-you response. The permanent public endpoint remains available, so link expiry complements these checks and does not prevent all spam by itself.

Public inquiry Level of interest uses an required A1–C2 code dropdown. Its header shows only the uploaded Login logo, sized responsively with blank upload margins fitted; without a logo the LGL badge and institute name remain.

On Student admission, selecting an existing student automatically loads their saved name, contact details, address, birth date, father name and CNIC into the form. Filled fields stay read-only; empty basic fields are editable and can be completed with the admission. The Choose button remains a no-JavaScript fallback; previously saved values cannot be overwritten.

Active portal module styles and Select2 theme/custom CSS are bundled by Vite. Public application and browser certificate print styles have separate versioned CSS entries. The Student admission selector uses compiled responsive classes, with padding and a stacked mobile action. Upload updated Blade files and the complete `public/build` directory (including manifest and all assets) together; a partial build upload can break styling. `verify-built-styles.mjs` checks compiled entries/selectors and active browser views for inline styles; standalone PDF embedded styles and retained dormant templates are explicit exceptions.

Materials are accessed from attachment counts in Student My Levels and Teacher Assigned Levels tables, rather than a separate sidebar link. Each link opens that level's protected file list; Admin manages files on the Level detail page.

Login uses a wider, naturally wrapping headline and description below the logo, followed by a compact Guten Tag learning card on desktop. Compact spacing fits normal desktop and mobile screens; scrolling remains available when zoom, keyboard or validation content needs extra room.

Student tables include an attendance-machine status and sync icon. Without an active configured machine, new people receive no machine number. Configured machines receive the app's reserved Employee No. through the institute bridge; the same ID appears as confirmed only after a successful bridge result. Pending/failed syncs remain visible, and existing numbers are preserved. Sync queues work for the bridge's next run; it does not directly contact the device.

Portal success, error, warning and validation alerts use one shared display with inline icons and deduplicated messages. Field-level validation guidance remains beside inputs.

Displayed times across dashboards, class/attendance pages, notifications, machine logs, fee history and printable receipts/payroll slips use 12-hour AM/PM format. Stored times, native form values, calculations and CSV exports retain their existing formats.

Date/time pickers have separate month/year dropdowns, labelled Hour/Minute/AM–PM choices and Today/Now, Clear and Done actions. Dates display DD-MM-YYYY and times display hh:mm AM/PM; submitted values retain existing controller formats. Calendar layouts fit narrow screens and keep existing date limits.

Attendance is one workspace at `/attendance`, with Students, Teachers and Staff tabs for Admin. Each tab has summary cards and date/person filters. All three tabs default to Today; choose another date span through the shared range picker. Their cards show recorded Present, Late, Absent and Attendance rate for the selected filters; Excused is excluded from the rate and unmarked is not Absent. Counts are per student per class; a separate notice links to classes needing marking. Students retain batch/session marking and a student filter. Teachers/Staff show Check-in/Check-out (first/last recorded machine punches) in hh:mm:ss AM/PM format, punch counts, recorded/single-punch status and expandable punch details. Their cards show people with/without punches for the selected range; no punches does not mean absent. Teachers retain assigned-batch student attendance only. Payroll is unchanged; the older combined machine-attendance route remains compatible.

Phone and Emergency contact fields accept digits with an optional leading + and preserve leading zeroes. Forms use telephone keyboards and remove letters/pasted formatting; server validation also rejects text. Emergency contact remains optional.

New staff Joining date and Teacher Hire date default to today in the application timezone. Editing preserves the saved date, including an unset date; validation retries preserve submitted input.

Student My Result includes exam-name search, batch/status filters and personal Scheduled, Results available, Passed and Failed counts. Filters show only the student's own records, with a clear reset when nothing matches.

Teacher and Student password fields are optional on create/edit. Leave them blank on creation to set a password later through Admin edit before the person's first sign-in; leave them blank on edit to retain the existing password. Supplied passwords require at least 8 characters and matching confirmation.

- [Project requirements, architecture and operations](docs/PROJECT.md)
- [Task status, verification and recovery evidence](docs/TASKS.md)
- [Shared agent rules and collaboration workflow](AGENTS.md)
- [Claude Code entry point](CLAUDE.md)

These five Markdown files are the current documentation. Deferred modules remain outside the active product. Historical API examples were removed because API routing is unregistered and Sanctum is not installed.

The login page fills the desktop viewport edge to edge. An uploaded Settings login logo replaces the visible institute wordmark with a responsive image up to 340px wide on desktop (320px on mobile), with blank white/transparent upload margins fitted out of the login display; without a logo, the LGL badge and institute name remain. Admin can open `/settings?show=true` to upload three independent logos: Login, Sidebar, and a shared Payslip/Receipt logo. The expanded sidebar/mobile header uses the Login logo; expanded desktop branding fits the artwork into the available width without increasing header height; the collapsed desktop sidebar uses the Sidebar logo. Missing logos use the original name/icon branding (icon only when collapsed). These upload controls and the Integrations section are hidden on normal Settings and visible only with ?show=true; saved logos always render at their respective destinations. Blank uploads preserve saved files. Existing login uploads remain the login/public application logo; document branding uses its own upload. Login and public application logo URLs use the current application address (including port/base path), respecting any configured asset URL. Its default branding is Land of German Language; Settings can override the institute name.

Admin can sell a multi-level package (2–4 consecutive levels, e.g. A1 + A2, A1 to B1, A1 to B2) from Enrollments > Multi-level package. Admin enters the agreed price per sale (package or student-specific discount), chooses full payment now or monthly installments per level, and the system reserves every seat and splits the price exactly across level ledgers. Normal module-wise admission remains available. Per-student teachers are paid monthly per batch (rate × students that month) from Teacher Payroll > Generate month.

Exam-based institute certificates are the next requested extension. Design is recorded under E01 in docs/TASKS.md; passing-rule clarification is pending and the legacy exam/certificate modules remain inactive.

Student exam announcements open My Result, showing a Scheduled state for ungraded exams in their enrolled batches. A separate Results section appears only after marks are recorded for that student. Existing exam notifications with staff-only exam URLs are adapted when shown in the bell or notification history; stored notifications remain unchanged. The current workflow records marks through Teacher/Admin; it does not provide online question attempts.

Admin batch save now automatically creates regular dated classes from weekly days/times through the batch end date, starting today or the batch start date. Start and end dates are required for every batch; the end date must be on or after the start date. Teachers see assigned batch dates/timings and today’s direct attendance links; use Extra / makeup class only for additional classes. Existing dated classes and attendance are preserved. For an older batch, Admin can edit and save to fill missing future dates.

Class Sessions now separates teacher confirmation from student attendance. Open a class to record Class taken or Class missed with a reason, then use Schedule linked makeup on an original missed class. This week’s teaching shows missed classes as makeup needed, pending or covered; coverage requires the makeup’s explicit Class taken confirmation. The additive teaching-record migration was applied to the owner-authorized local `lgl_system` on 5 November 2026.

Teacher Dashboard class names and dates/times open class details; assigned weekly timing opens the batch-filtered dated class list. For online teaching, Admin can enter one recurring meeting link on the batch, which regular class generation copies into new sessions. Separate meeting URLs require individual session links; meeting creation is manual. Changing a batch link does not overwrite links already stored on existing sessions.

Teacher create/edit uses searchable Select2 dropdowns for qualification, German-teaching specialization and experience (0–50 years). Existing custom values remain available when editing their teacher.

Across portal modules, Teacher, Course, Student, Batch and Enrollment selectors (including report/list filters) use searchable Select2, as do long choice lists. Small fixed choices, Rows per page and date/time controls stay native. Select2, jQuery and Bootstrap theme assets are served locally; no runtime CDN is needed for these dropdowns. Run `node scripts/verify-search-selects.mjs` for isolated browser checks.

For an unpaid mistaken admission, open Enrollments > View > Correct batch. Admin can choose another available batch of the same course and provide a reason before academic records exist. The original stays linked in history; fees, installments and admission date are preserved. Package admissions and any payment history require a separate reviewed process.

Admin uses one Students screen and one Student admission form for new/existing students. The Students table includes each enrollment's level/batch, fixed discount and outstanding fee, with detail links; Multi-level package is available in its header. There is no separate admissions tab. Existing admission URLs remain compatible and records are preserved. The shared form defines batch/fee fields once, fixed currency discount and equal/custom installments. Leads has a shareable public inquiry link (/apply): submissions become New leads only; Admin contacts them, qualifies and converts to a student, or rejects with a reason. No second applicant-completion step is required. Meta is removed from visible source filters; historical rows remain.

For per-student teacher pay, select Per student per month on teacher create/edit and enter Amount per student per month. This teacher's rate applies to students in every assigned batch each month, independent of the course amount. Existing teachers without a positive rate need it configured before new batch dues can be generated; previously generated dues and payouts keep their saved amounts.

Enrollment, class-session, profile, fee-ledger and enrollment/dues report tables show course levels beside course or batch names.

Shared tables place Rows per page at the top left with search on the right; filters sit in a separate compact group below. On mobile, search wraps to a full-width row. Result count and pagination stay below the table. Changing page size preserves filters and sorting and returns to the first page. Financial histories with fixed page sizes retain their existing pagination.

From/To fields across filters, reports and class-generation forms use one DD-MM-YYYY range picker, submitting the original separate date values. Attendance shortcut buttons are removed. Run `node scripts/verify-date-ranges.mjs` for isolated range checks. Single date/time fields use a locally bundled Flatpickr calendar and AM/PM time picker with the portal's red/gold theme, including dynamically added schedule rows. Displayed and typed dates use DD-MM-YYYY; submitted dates remain YYYY-MM-DD and submitted times remain HH:MM. Existing required fields and date bounds remain enforced. Rebuild assets after frontend changes.
