# Task register and evidence

## Current state

31 October 2026: ten functional groups implemented locally; see [PROJECT.md](PROJECT.md). Owner approved automatic incremental work, three roles, Admin-managed accounting and demo-only data. Owner will handle VPS setup. No external deployment or operational-data import is claimed.

Current reservation: none. E01 exam/certificate discovery and design recorded 3 November 2026 by Codex; implementation awaits the requested passing-rule clarification. F13 A1+A2 full-payment package released 3 November 2026 by Codex, sole writer. U10a desktop full-width login released 3 November 2026 by Codex, sole writer. U10 login branding released 3 November 2026 by Codex, sole writer. A04 enrollment completion released 2 November 2026 by Claude Code, sole writer; earlier F12 fee refunds released 2 November 2026 by Claude Code, sole writer; earlier F11 enrollment discount edit released 2 November 2026 by Claude Code, sole writer; earlier U07 show pages redesign released 2 November 2026 by Claude Code, sole writer; earlier P10 teacher payroll released 2 November 2026 by Claude Code, sole writer; earlier A03 batch status automation released 2 November 2026 by Claude Code, sole writer; earlier U02b, U02a and U01 (below) released 2 November 2026 by Claude Code, sole writer. Next owner-requested work, each needing its own design before code: U02 forms/detail/portal pages in the same style; B Google Meet/Zoom online classes; C WhatsApp fee reminders (B and C reopen deferred integration/notification modules). Earlier: Codex was the sole writer/integrator; no concurrent agents, independent review, staging or commits are claimed. Preserve existing unrelated changes, especially composer.lock and the teacher-info migration.

Seeder follow-up: owner requested `password` for all seeded roles. All four user-creation password hashes now use that value. Existing databases were not reseeded or updated; the owner's current Admin email edit remains preserved.

## Roadmap and task status

| Task | Priority / dependency | Result / state |
|---|---|---|
| P00 | P0; first | Three-role scope and continuing authorization recorded; done |
| P01 | P0; P00 | Original backup/restore, isolated tests, fresh/upgrade checks; done |
| P02–P04 | P0; P01 | Role/record/field access, login throttling, active accounts, last-Admin and three-role schema; done locally |
| P05–P06 | P1; access controls | People/course/batch/session/profile flows; done |
| P07 | P0; batches | Locked transactional admission and exact fee plans; done |
| P08–P09 | P0; backup/admission | Ledger schema and legacy discrepancy report; original preserved, clean demo reconciled; done for demo scope |
| P10–P11 | P0; ledger | Idempotent payment/reversal, receipts, restricted transfer/withdrawal; done |
| P12 | P1; enrollment/session | Shared manual attendance, historical eligibility and audited corrections; done |
| P13–P14 | P1; canonical services | Three dashboards, four reports and validated settings; done |
| P15/P15a | P0; core implementation | Local backend, HTTP, database race/restore and Chrome desktop/mobile/print checks; done within stated limits |
| F13 | Owner request; existing admission and ledger | A1+A2 Rs 50,000 full-advance package, exact module allocations, combined receipt and grouped reversals; implemented and activated on local demo |
| Documentation cleanup | Owner request; preserve useful content | Consolidated five docs; redundant Markdown deleted; no application/data changes |
| P16 | P2; separate future approval | Physical legacy module/table/database cleanup; deferred |
| Production release | Owner-managed; staging/recovery gates | No agent deployment task; not executed |

No deferred enterprise module is approved as the next coding task. Future scope should be reserved with files, acceptance criteria and isolated verification before editing. General operational-data migration needs a separate reconciliation/mapping plan before schema cutover.

## Data state

- Active demo: lgl_system_demo_20261031_125354_80b7ed; 1 Admin, 2 Teachers, 20 Students; all @lgl.com; 45 enrollments and 45 ledger payments; zero reconciliation mismatches. Application/database timezone checked as Asia/Karachi.
- Original lgl_system: all 23 table schemas/data hashes match the initial backup. Original 39 inconsistent balances and Accountant were retained there; no silent promotion or fabricated historical payment.
- Do not reset either DB, rerun demo preparation unnecessarily or discard private backups.

## Recorded verification

These are previous implementation results, not checks rerun by the documentation-only cleanup.

- Latest backend regression: 52 tests passed, 525 assertions. Assertion count varies with randomized demo enrollment generation.
- Real HTTP: 45 checks, zero failures across three roles including expected denials; six final critical pages returned 200.
- MariaDB independent-process races: capacity admission, overpayment, same-key replay, cross-account key collision and reversal all passed.
- Backup restoration: 27 tables matched; fresh migration/seeding with 23 users and restored-copy upgrade passed. Original 23-table source stayed unchanged.
- PHP syntax sweep: 85 files, zero errors. Targeted Pint: 31 new files passed; 13 substantially changed existing files formatted.
- Chrome UI: 24 checks passed, zero failed; desktop/mobile menus, role dashboard links, keyboard input, four report widths and receipt print/PDF. No new npm dependencies.
- No business schedule events; deferred automatic absence/reminder commands guarded.
- Self-review only. No production certification, physical-device/printer, complete accessibility or independent cross-browser audit.

## Private recovery/evidence locations

All paths below are under storage/app/private, ignored by Git. Never publish these artifacts or environment copies.

| Evidence | Relative path |
|---|---|
| Initial SQL/source/upload backups | backups/baseline_20261031_113758_1469df/ |
| Original database comparison | original-database-verification.json |
| Current SQL and restore evidence | backups/baseline_20261031_130143_be29fe/ |
| Verified source/upload archive before P15a | backups/baseline_20261031_130143_be29fe/source-and-uploads-final.zip |
| Previous environment file | demo-setup/lgl_system_demo_20261031_125354_80b7ed/environment-before.env |
| Five race results | concurrency/lgl_baseline_concurrency_20261031_130753_f1f48a/report.json |
| Browser report/screenshots/receipt PDF | ui/2026-10-31T14-06-37-546Z/ |
| Verified P15a source overlay | ui/2026-10-31T14-06-37-546Z/source-patch.zip |

Current SQL database.sql SHA-256: 33d07e69dd78be5b7afd1d1fd25ab879083b4fc6d4828b069458f9662b7f719a.

Historical full backups retain earlier documentation. The pre-P15a source archive plus UI overlay do not include the subsequent documentation consolidation; review current source when packaging a new release. Recovery uses a matching code/database pair in an isolated location, never destructive historical migration rollback.

Completed UI refresh reservation: Codex owned layouts/admin, auth/login, dashboards, frontend CSS/build configuration, browser verification and related documentation. Owner requested Tailwind and supplied a navy-sidebar dashboard reference. No business/database changes; one writer.

UI refresh completed: navy sidebar, responsive shared shell, role dashboards, canonical live Admin metrics and split login with password visibility toggle. Existing Bootstrap views remain compatible with compiled prefixed Tailwind utilities. package-lock.json pins the already declared frontend packages. Login uses an app-name fallback before settings migrations. Seeder consistency checks now respect the owner's Admin email and verify the requested password for all seeded users. No seeder or database reset was run.

Validation: npm build passed; 52 backend tests passed (541 assertions); Chrome checks passed 30/30 including compiled utilities, login visibility toggle, three role logins/skip links/mobile keyboard menus, four report widths and receipt print/PDF. Evidence: storage/app/private/ui/2026-10-31T16-42-19-524Z/. Desktop login/dashboard and mobile dashboard screenshots were visually reviewed. Final box-sizing refinement also passed 30/30 checks; final evidence: storage/app/private/ui/2026-10-31T16-43-43-693Z/. Reservation released; current reservation remains none.

## U01 list-page UI (2 November 2026, Claude Code)

Owner approved: layout B (KPI strip, filter toolbar, removable filter chips), server-side column sorting, rows per page (15/25/50/100), Admin-only CSV, row action menus, vanilla JS instead of jQuery/Select2. Lists first; forms/detail/portal pages are U02.

Behavior:
- `app/Support/ListQuery.php` applies whitelisted search (LIKE wildcards escaped, 100-character cap), filters, date ranges, sorts and page sizes to each controller's existing role-scoped query; unknown keys/values are ignored. `counts()` computes KPI totals in one query over the full filtered set. CSV streams every filtered row, Admin only (403 otherwise), with formula-injection protection; fee-collection CSV covers the full period, payments and reversals.
- `OverviewService::debtTotals()`/`installmentTotals()` reuse the dashboard allocation SQL for scoped outstanding/overdue KPIs; cross-checked equal to `financialTotals()` on seeded data.
- Pages: Students, Teachers, Account Access (table with per-row access forms), Courses (cards replaced by a paginated table), Batches, Class Sessions, Enrollments, Fee Ledger (expandable installment/payment rows), Fee Collection Report (method filter), Reports index, Enrollment and Dues reports, Attendance (previously a placeholder; now sessions with needs-marking state) and Attendance Report; Teacher/Student variants use the same components. Teacher filters list only assigned batches; Students get no batch filter; finance KPIs and CSV never reach Teacher/Student.
- `resources/js/list.js`: debounced auto-submit with focus restore, empty-field stripping, `<dialog>` confirmations (`data-confirm`), accessible `select[data-combobox]` used on the live fee-schedule form. jQuery removed from the layout; Bootstrap's own JS drives dropdowns/collapse. Pagination uses the Bootstrap view because default Tailwind classes are not compiled (prefix `tw:`).
- `admin/installments/create.blade.php` still references jQuery/Select2 but is unreachable (the route renders `installments/plan`); left unchanged pending cleanup. `admin/reports/filters.blade.php` and `admin/attendance/reports.blade.php` are now unused.

Validation: `npm run build` passed; `php artisan test` 58 passed (604 assertions), including new `tests/Feature/ListPagesTest.php` (6 tests: every list page with filters/sort/size, filter correctness and chips, ignored hostile params, CSV content/formula guard, Teacher/Student isolation, helper units). Pint applied to new PHP files. Browser: headless Chrome 40/40 (jQuery absent, debounced search and focus restore, select auto-submit, chip removal, aria-sort and order, row menu inside viewport, confirm dialog cancel sends nothing, ledger expansion, combobox focus/filter/Enter selection, no horizontal overflow at 390px on 13 list pages, print hides toolbar, Teacher pages without CSV); screenshots visually reviewed.

Limitations: the browser run used a disposable SQLite copy seeded in the session scratchpad and served by PHP's built-in server with environment overrides (isolation verified by checking that the session row landed in that file). `.env` currently names database `papers`, not the demo, so `scripts/verify-ui.mjs` refuses to run and was not rerun; no MariaDB race/restore scripts rerun (no ledger write paths changed). Self-review only; no independent review, physical-device, cross-browser or screen-reader audit. No commits made.

## U02a two-column create/edit forms (2 November 2026, Claude Code)

Owner asked for create/edit forms in a 6/6 layout. The six live forms (Student/Teacher, Course, Batch, Class Session including the Attendance "New session" route, Enroll Student, Create Fee Schedule) now use titled sections inside one panel: short fields at `col-md-6`, textareas full width, required markers, a right-aligned Cancel/Save bar, single column below 768px. Markup and CSS only: field names, ids, `old()` values and validation are unchanged; batch status/delivery options gained explicit values equal to their previous option text. The Batch form preselects `?course_id=` from the Courses row menu. The receipt "Date (timezone)" label is now "Date" at owner request; the timezone stays in the receipt snapshot.

Validation: `npm run build` passed; `php artisan test` 58 passed (606 assertions). Headless Chrome 18/18 on the disposable scratchpad SQLite copy: every form pairs fields on desktop, has one column and no overflow at 390px, the enrollment custom-schedule toggle still enables its inputs, and a real course create submits successfully; screenshots reviewed. Not covered: profile and settings forms (not create/edit) and dead legacy form views.

## U02b number-wheel guard and searchable selects (2 November 2026, Claude Code)

Owner asked that scrolling must not change number inputs and that normal selects become Select2-style. Real Select2 requires jQuery, which U01 removed at the owner's request, so the existing vanilla combobox in `resources/js/list.js` was extended and now enhances every `select.form-select` (opt out with `data-native`; used for the rows-per-page picker and Account Access role selects inside the table). It supports type-to-search with optional `data-meta`, choosable empty options ("All" clears a filter), keyboard (arrows, Enter, Escape restores, Tab accepts), a "No matches" row, disabled options, fixed positioning that opens upward near the viewport edge so clipping panels/tables cannot hide it, sync on programmatic change/form reset, and focusing the input when a hidden required select is invalid. Selected values still submit through the original select, so no controller/validation changes. A passive global wheel listener blurs a focused number input so the page scrolls instead. Filter-toolbar focus restore is now limited to the search box.

Validation: `npm run build` passed; `php artisan test` 58 passed (610 assertions). Headless Chrome on the disposable scratchpad SQLite copy: U02b suite 23/23 (wheel leaves 5000 unchanged while an unguarded control input becomes 5003; course, batch, filter, enrollment and attendance-marking comboboxes; search, Enter, Escape, "All" clears filter, list on top inside clipped panels and the attendance table, real batch save, mobile fit) and the U01 list suite re-run 40/40; screenshots reviewed. A same-day session with eligible students was added to the disposable copy only, to exercise the marking page.

## A03 batch status automation and dashboard reminders (2 November 2026, Claude Code)

Owner asked that batch statuses follow start/end dates without manual admin work, with a dashboard reminder 2 days ahead. Owner chose a daily scheduler job (cron) over date-derived reads, and Admin-only reminders for both start and end.

- `Batch::statusForDates()`: end date before today means completed; start date on or before today means ongoing; otherwise upcoming. The end date itself still counts as ongoing.
- `php artisan batches:sync-status` (`app/Console/Commands/SyncBatchStatuses.php`), scheduled daily at 00:05 in `routes/console.php`. It only moves upcoming/ongoing batches forward (a missed run sends upcoming straight to completed), never touches cancelled or moves a status back, uses row locks per batch, and writes one `batch.status_synced` audit row (null actor) per change. Re-running it changes nothing.
- Batch create/edit: the status select now offers only "Automatic from dates" or "Cancelled"; saving derives the status from the dates unless cancelled. Legacy posted values (upcoming/ongoing/completed) are accepted and treated as automatic.
- Admin dashboard "Batch reminders" panel (`OverviewService::batchReminders()`): upcoming batches starting and ongoing batches ending within today+2 days, with today/tomorrow/in N days labels; hidden when empty; not on Teacher/Student dashboards.
- Deployment: the host needs `* * * * * php artisan schedule:run`. Until the job first runs, existing demo batches keep their stored statuses; the command was not run against the active demo database.

Validation: `php artisan test` 64 passed (652 assertions), including the new `tests/Feature/BatchStatusSyncTest.php` (6 tests: transitions, missed run, null end date, cancelled skip, manual-early kept, idempotent audit count, daily schedule expression, save-time derivation, 2-day vs 3-day window, role visibility). `BatchAndSessionTest` now expects completed only after the end date passes. `npm run build` passed; `php artisan schedule:list` shows `5 0 * * * php artisan batches:sync-status`. No browser check or independent review was done.

## U03 password show/hide toggle (2 November 2026, Claude Code)

Owner asked for an eye button on the Student/Teacher form "Sign-in" password fields. `initPasswordToggles()` in `resources/js/list.js` wraps every `input[type=password]` in admin-layout pages (Student/Teacher form, Profile, legacy pages) with the login page's `.password-wrap`/`.password-toggle` button (aria-label and aria-pressed update; opt out with `data-no-reveal`); the login field already has its own toggle and is skipped. CSS centres the button on Bootstrap `form-control` height. Edit-mode note now also states the 8-character minimum. Validation: `npm run build` passed; `php artisan test` 64 passed (654 assertions). No browser check was run.

## U04 full-height sidebar (2 November 2026, Claude Code)

Owner asked for the sidebar to start at the top of the page. From 768px up the navy sidebar is now fixed at `top:0` with full viewport height, carries the brand (`.sidebar-brand`, 80px to match the topbar), and the white topbar starts after it (`margin-left:232px`). Below 768px the topbar brand and collapsible sidebar are unchanged. Files: resources/views/layouts/admin.blade.php, resources/css/app.css. Validation: `npm run build` passed; `php artisan test` 64 passed. No browser check was run.

## U05 desktop sidebar collapse (2 November 2026, Claude Code)

Owner asked for a hamburger to collapse the sidebar. From 768px up a topbar button (`#sidebarCollapse`) toggles `html.sidebar-collapsed`: the sidebar becomes a 72px icon rail (brand name, section headings and footer hidden; links show icons with the label as a tooltip), and the topbar and main content shift to 72px. `initSidebarCollapse()` in `resources/js/list.js` updates aria-expanded/label and stores the choice in localStorage (`lgl:sidebar`, wrapped in try/catch); a small head script applies it before paint. Mobile keeps its existing collapsible menu. Validation: `npm run build` passed; `php artisan test` 64 passed. No browser check was run.

## P10 teacher payroll (2 November 2026, Claude Code)

Owner chose: each teacher has one pay type (fixed monthly salary or per student per batch); per-student earning is generated once per batch; teachers see their own earnings. New clean ledger mirroring fees; legacy `teacher_earnings`/`batch_teacher_earnings`/`teacher_payments` and their unregistered API controllers are untouched.

- Migration `2026_11_02_000001_create_teacher_payroll_tables`: `teacher_dues` (kind salary|batch, period, batch, student_count/rate/amount snapshot, unique nullable `active_key`, void fields), `teacher_payouts` (voucher, unique submission key + payload hash, slip snapshot), `teacher_payout_reversals` (one per payout). down() refuses. Models block updates/deletes (a due may only be voided).
- Pay settings reuse existing `users.salary_type` (`monthly`/`per_student`) and `monthly_salary` (now cast decimal:2) via a new "Pay" section on the Teacher form. Rate for per-student teachers is the course `teacher_per_student_amount`.
- `PayrollService`: generate salaries for a current/past month (active monthly teachers with salary > 0; existing months skipped); generate a batch due (ongoing/completed batch, per-student teacher, rate > 0, active+completed students, one per batch); payout under due row lock, capped at outstanding, idempotent by submission key; full reversal once with reason; void only without posted payouts (clears `active_key` so it can be regenerated). Every write is audited.
- `PayrollController` + routes under `/payroll` (Admin manages; Teacher index/show/slip scoped to own dues; Student blocked). Views `resources/views/payroll/{index,show,slip}`; sidebar "Teacher Payroll" (Admin) / "My Earnings" (Teacher).
- Not included (YAGNI): pro-rating/absence deductions, advances, tax, notifications, dashboard payout KPI, legacy table cleanup.
- Deployment: run `php artisan migrate` (adds three tables only). It was not run against the active demo database.

Validation: `php artisan test` 70 passed (717 assertions), including new `tests/Feature/PayrollTest.php` (6 tests: salary targeting/idempotency/snapshot/future month, batch count and eligibility, payout cap/idempotency/key reuse/reversal/immutability, void rules and regeneration, Teacher/Student access, admin pages, filters and teacher pay form). `npm run build` passed. Tests use SQLite in memory; MySQL migration and browser checks were not run. No independent review.

## U06 person profile page redesign (2 November 2026, Claude Code)

Owner asked to improve `/teachers/{id}`. The shared `admin/people/profile` view (Teacher and Student) now has an identity hero (initials avatar, ID, status, specialization, hire/join date, actions), KPI strip, contact facts with mailto/tel links, and for Teachers a "Teaching and pay" panel (pay settings Admin-only), an assigned-batches table (course, dates, capacity bar, status; ongoing first) and Admin-only "Recent pay" (last 5 dues, link to filtered payroll). Students get an enrollments table; the fees link now shows only to Admin or the student. `TeacherController::show` loads batches with active counts and dues. CSS under "Profile pages" in app.css. Validation: `npm run build` passed; `php artisan test` 71 passed (731 assertions), including a new profile render test in PayrollTest. No browser check was run.

## U07 show pages redesign (2 November 2026, Claude Code)

Owner asked to improve `/payroll/payouts/{id}` and every show page. New components: `x-show.hero` (icon tile, eyebrow, title, status, meta line, action buttons; reuses the profile hero CSS) and `x-show.document` (printable receipt/slip card with institute, document number, large amount, detail rows, "Reversed" stamp and notice, print footer). Redesigned: payroll slip and fee receipt (document card, print button, reversal moved into a collapsible panel), batch (meta, Join class, KPIs, student roster without contact fields, upcoming sessions), course (KPIs, fees, description, batches table), class session (meta, attendance counts, table with notes, collapsible cancel), enrollment (fee summary with progress, installment status, payments with receipts, transfer/withdraw panel, withdrawal/transfer notices), teacher-view student page, and the payroll due header. Native `confirm()` handlers were replaced with the shared `data-confirm` dialog. Controllers: batch show also loads teacher, capacity, platform and sessions; course show loads batches with active counts (the unused stats query was removed); enrollment show eager-loads payments with reversals.

Bug found by the new test: the receipt page would fail (500) for a reversed payment because `payment_reversals.created_at` is not cast; the document component now parses it.

Validation: `php artisan test` 72 passed, including new `tests/Feature/ShowPagesTest.php` (39 assertions across batch, course, session, enrollment, receipt, slip and Teacher views). `npm run build` passed. No browser check was run.

## U08 back links on internal pages (2 November 2026, Claude Code)

Owner asked for a back button on internal pages. `App\Support\BackLink::for()` maps each non-sidebar route to a fixed parent (show/create → list; edit → its show page; session attendance → session; batch fees → batch; student fees, fee report and installment plan → fee ledger; attendance report → Reports for Admin, Attendance for Teacher; student attendance → student; report pages → Reports). The admin layout renders "Back to …" above the content. A fixed parent is used instead of browser history so Back after a save never returns to the submitted form. Students see no layout back link (their fee/attendance pages are their top-level pages); receipt, slip and payroll due pages keep their own back buttons. Validation: `php artisan test` 72 passed (792 assertions; ShowPagesTest now checks targets, Teacher labels and that list and Student pages have none). `npm run build` passed. No browser check was run.

## U09 Users page (formerly Account Access) (2 November 2026, Claude Code)

Owner asked to rename Account Access, drop the Save button and plural Role filter names. Sidebar, title and heading now say "Users" (route `/accounts` unchanged). Each row's role select and Active switch save immediately: `initAutoSave()` in `resources/js/list.js` posts the row form (`data-autosave`) with fetch on change, disables the controls while saving, shows a toast (`showToast()`, shared live region), updates the status pill, and on a rejected change (last active Admin, role change with academic history, network error) restores the last saved value and shows the error. `AccountController::update` returns JSON for JSON requests (message, role, is_active, and a dashboard redirect when an Admin removes their own access); the non-JS form path and redirects are unchanged (a `<noscript>` Save remains). The Role filter shows Admins/Teachers/Students; the per-row select stays singular and no longer has an empty "Choose" option. Validation: `php artisan test` 73 passed (792 assertions), including a new Users page test (rename, autosave markup, plural filter, JSON response, self-demotion redirect). `npm run build` passed. No browser check was run.

U09 follow-up: owner asked to remove Admin from the per-row role dropdown, UI only (server still accepts `admin`; owner chose this over a server-side block). Non-admin rows offer Teacher/Student; Admin rows show a fixed "Admin" pill with a hidden `role=admin` field so toggling Active can never demote them. The Role filter still lists Admins. Validation: `php artisan test` 73 passed (810 assertions).

## F11 enrollment discount % display and edit (2 November 2026, Claude Code)

Owner asked to show each student's discount % and allow editing it; owner chose adjusting unpaid installments over payment-only or display-only. This relaxes the earlier "immutable schedule" rule for this one Admin action (PROJECT.md updated).

- `Enrollment::discount_percentage` accessor derives the % from the stored amount (integer basis points, trailing zeros trimmed). Shown on the enrollment page (with amount), a new Discount column and CSV columns on Enrollments, and the fee ledger row/detail (with an "Edit discount" link for Admin).
- `PaymentService::changeDiscount()`: Admin only, reason 5–1000 chars, 0–100% with 2 decimals, same rounding as admission. Under the enrollment row lock it requires a reconciled ledger, rejects transferred enrollments, unchanged values and a new net below the paid amount. More discount trims outstanding amounts from the latest installments backwards; less discount is added to the latest unpaid installment (or the last one). Balances/statuses are refreshed, reconciliation re-checked, and an `enrollment.discount_changed` audit row records before/after discount, % and per-installment amounts.
- Route `PATCH /enrollments/{enrollment}/discount` (`enrollments.discount`, Admin) and a collapsible "Edit discount" form in the enrollment Fee agreement panel. The legacy unrouted `applyDiscount` (which did not adjust installments) is left unused.

Validation: `php artisan test` 76 passed (841 assertions), including new `tests/Feature/DiscountEditTest.php` (trim before payments, below-paid rejection, partial-payment trimming that leaves paid amounts intact, decrease back to 12.5%, full settlement afterwards, reason/range/unchanged/100% cases, Teacher forbidden, % shown on enrollment, list and ledger). `npm run build` passed. No browser check was run.

## F12 refunds when a discount exceeds unpaid fees (2 November 2026, Claude Code)

Owner asked why 10% → 25% was blocked for a student who had already paid; the new net fee was below the amount paid and the system had no refund record. The block message first gained the paid amount and the highest possible % (DiscountEditTest), then the owner chose to record refunds.

- Migration `2026_11_02_000002_create_fee_refunds_table`: `fee_refunds` (amount, method, reference, reason, refunded_at/by, voucher `RFD-…`, unique submission key + payload hash, slip snapshot) and `enrollments.refunded_amount` (default 0). down() refuses. `FeeRefund` blocks updates/deletes; `Enrollment::net_paid` = paid − refunded, used by remaining fee and progress.
- `PaymentService::changeDiscount()` accepts optional refund details. When the new net is below the net paid amount, it requires a refund method (error names the refund amount and the highest % without one), trims all unpaid amounts, records the rest as a refund, increases `refunded_amount`, and audits the refund with the discount change. Same submission key returns the applied result; a different payload is rejected. Reconciliation now requires refunded cache = refunds and scheduled = net + refunded. Payments of an enrollment with a refund cannot be reversed.
- UI: discount form has an optional "Refund (only if needed)" section; refund slip page `/refunds/{refund}` (Admin or the student; `x-show.document`); enrollment page shows Paid / Refunded / Net paid, refund rows and a schedule note; fee ledger Paid shows net paid; collection report lists refunds as negative rows with a Refunds KPI and CSV rows; dashboard net collected and this-month net subtract refunds.
- Not included: refund reversal, credits, a refund list in the student's own fee page (the slip is reachable from the enrollment page for Admin).
- Deployment: run `php artisan migrate`. Not run against the active demo database.

Validation: `php artisan test` 79 passed (883 assertions), including new `tests/Feature/RefundTest.php` (fully paid 25% refund with idempotent resubmit, unchanged paid schedule, reversal block, slip/enrollment/ledger/report/dashboard figures, student access; partial payer trimming then refund then later decrease creating new debt and settlement; no refund when the discount fits). `npm run build` passed. No browser check was run.

F12 follow-up: owner asked to list refunds for students. The fee ledger (Admin global/batch/student views and the student's own "My Fees" page) now shows a "Refunds" table under Payment history, scoped like the payments list and hidden when there are none, linking each refund slip. Validation: `php artisan test` 79 passed; RefundTest checks the student sees their own refund on their fee page and another student sees none.

## A04 enrollments follow batch completion (2 November 2026, Claude Code)

While explaining the A1 → A2 flow, enrollments were found to stay "active" forever: `EnrollmentService::completeEnrollment` and `EnrollmentController::complete` exist but are unrouted, so a student moving to A2 showed two active enrollments and inflated active counts. Owner asked for the fix.

- `Batch::syncEnrollmentStatuses()`: completed batch → its active enrollments become completed; upcoming/ongoing batch → completed enrollments become active again; cancelled, withdrawn and transferred are untouched. One `enrollment.status_synced` audit row per change. Fees are not touched; outstanding balances remain payable (payments do not depend on enrollment status except transferred).
- `batches:sync-status` calls it when it advances a batch and also catches up batches that are already completed but still have active enrollments (the first run fixes existing data). Batch edit calls it after saving, so changing the end date moves enrollments either way.
- Batch page roster now lists active and completed enrollments with a status column (it previously showed only active, so a completed batch would look empty).
- A2 admission is a separate enrollment with its own fee agreement and discount; nothing carries over from A1.

Validation: `php artisan test` 81 passed (919 assertions), including two new BatchStatusSyncTest cases (job completion with withdrawn untouched, catch-up of an older completed batch, idempotent audit count, payment on a completed enrollment, roster; edit to past/future/cancelled). The active demo database was not changed; its existing completed batches will be caught up on the next `batches:sync-status` run.

F11 follow-up: owner asked for discount % per batch on the student page (`/students/{id}`). The profile Enrollments table now shows Discount % (amount below), Net fee (of gross), Outstanding and status (withdrawn label for dropped); for Admin the batch name links to the enrollment page where the discount is edited. The student sees the same figures on their own profile without the link. Validation: `php artisan test` 81 passed (DiscountEditTest checks Admin and student views).

F11 follow-up 2: enrollment #41 showed outstanding equal to its discount; the audit log showed the discount was lowered from 10% to 5% after full payment, which correctly created Rs 1,750 of debt (the field is the new total %, not an extra %). To prevent this, the enrollment discount form now states that the value is the new total discount and shows a live preview (`initDiscountPreview()` in resources/js/list.js, same half-up basis-point rounding as the server): old → new %, old → new net fee, and either the new outstanding, the extra amount the student will owe (warning), or the refund that will be recorded (warning; the refund section is highlighted and its method becomes required). No data was changed for #41. Validation: `npm run build` passed; `php artisan test` 81 passed; preview arithmetic was checked against the #41 figures in Node (10% → no change, 15% → 1,750 refund, 5% → 1,750 due, 0% → 3,500 due). No browser check was run.

## U10 professional institute login (3 November 2026, Codex)

Owner requested a professional login, the Settings logo, and the name Land of German Language. The public Blade login now has a navy/white split layout, German flag accents, a learning welcome card and a compact mobile brand header. The configured public-disk logo uses object-fit containment; LGL is the fallback without a logo. Empty and known legacy default names display Land of German Language; custom Settings names remain escaped and respected. Reads remain guarded before the settings table exists. Existing authentication, remember-me, validation, CSRF and password toggle behavior are retained. No database settings or records were changed. The missing local public/storage link was created with php artisan storage:link.

Files: resources/views/auth/login.blade.php, resources/css/app.css, tests/Feature/LoginBrandingTest.php, README.md, docs/PROJECT.md and docs/TASKS.md. Existing unrelated untracked Markdown was preserved.

Validation: php artisan test --compact passed 83 tests (942 assertions), forced SQLite :memory: configuration checked before execution. npm run build passed. git diff --check passed (line-ending notices only). Login HTTP returned 200. Chrome desktop 1440x1000 and mobile 390x844 screenshots were inspected; mobile field overflow found during review was fixed. New tests cover missing settings table, fallback name, current/replaced logo URL and escaped custom name. Initial test fixture lacked the required setting label; corrected before the final passing suite. Evidence is private at storage/app/private/login-u10/. No independent review or physical-device check; real upload is covered by the existing Settings security test, and the login tests verify its stored path rendering. Reservation released.

U10a follow-up (3 November 2026): owner requested full-width desktop login. Removed outer padding, maximum shell width, rounded border and shadow; the two-column login now fills the viewport edge to edge with a 100vh minimum height. Mobile rules remain compatible. npm run build passed; git diff --check passed with line-ending notices only. CSS-only change; backend tests were not rerun. Reservation released; no independent review.

## F13 A1 + A2 full-advance package (3 November 2026, Codex)

Owner approved the recommended flow: Rs 50,000 only with full advance payment; normal per-module fees/installments otherwise. Added an Admin package form reachable from Enrollments and normal admission. It requires an existing active Student, open/capacity-eligible A1 and A2 batches, sequential dates, positive normal module fees totaling at least Rs 50,000 and PKR currency. Both seats are reserved immediately; the future A2 batch must already exist. No automatic promotion, unscheduled credit, package installments or extra levels were introduced.

EnrollmentService locks batches in ID order, Student and course rows, then creates both enrollments/one-installment agreements and canonical PaymentService postings in one transaction. The package price is allocated proportionally to normal module fees with integer rounding and the exact remainder assigned to A2. The normal fees and package discounts remain visible per module. A unique package submission key/payload returns the same receipt on an identical retry and rejects conflicting reuse. The immutable enrollment_packages row snapshots identity/currency/timezone, module names/dates/fees/discounts and original payment IDs. A nullable restrictive enrollment foreign key preserves membership. Combined receipts are summaries of the two ledger allocations, so reports/dashboards do not double-count them.

Admin and the owning Student can view/print the combined receipt from either module receipt or fee ledger. Teacher and unrelated Student requests are denied. Package module discounts are blocked server-side and their edit links hidden. Reversing either original allocation reverses both under package and ordered enrollment locks; a failure in either ledger rolls the whole reversal back. Original receipts/agreements remain and debt returns. Existing withdrawal keeps history and issues no refund; package cancellation/refund policy, unknown future batches and configurable prices remain future scope. Normal module-wise admission and payments remain independent.

Files: new migration database/migrations/2026_11_03_000001_create_enrollment_packages_table.php, app/Models/EnrollmentPackage.php, app/Http/Controllers/EnrollmentPackageController.php, resources/views/enrollment-packages/{create,show}.blade.php and tests/Feature/EnrollmentPackageTest.php; small changes in Enrollment, EnrollmentService, PaymentService, BackLink, routes, enrollment views, module receipt and fee ledger. Existing login edits and untracked historical Markdown were preserved. No agents, independent review, commits or deployment.

Validation:

- Final php artisan test --compact: 93 tests passed, 1,067 assertions (earlier run 1,071; existing randomized demo fixtures vary counts). PHPUnit XML and bootstrap were checked: forced SQLite :memory:, array sessions/cache, no operational database writes. Ten new package tests cover exact/unequal allocation, replay/conflict, full-only input and sequence validation, eligibility/currency/price rejection, second-module rollback, access restrictions, immutable snapshots, grouped reversal/rollback and normal module progression with unpaid debt.
- Final npm run build passed. git diff --check passed with line-ending notices only. PHP syntax checks passed for both changed services and both verification scripts. Targeted Pint passed/fixed the four new PHP files; broader pre-existing formatting was preserved.
- MariaDB disposable-process verification passed all eight race groups: admission capacity, overpayment, payment replay, cross-account key collision, original payment reversal, package replay, grouped package reversal and two-module last-seat competition. Every resulting enrollment reconciled. Final evidence: storage/app/private/concurrency/lgl_baseline_concurrency_20261102_195105_b119eb/report.json; earlier run also passed at lgl_baseline_concurrency_20261102_194552_2b54e0.
- Baseline backup/restore, fresh migrations, restored-copy upgrade and fresh demo seed all passed on isolated MariaDB databases. The first verifier run restored correctly but its old all-emails-@lgl.com assertion rejected the owner's existing custom Admin email. The verifier now permits that Admin address while checking exactly 1 Admin/2 Teachers/20 Students and @lgl.com for non-Admins; seed content was unchanged. Final successful evidence: storage/app/private/backups/baseline_20261102_194753_06ae92/report.json. Backup SHA-256: 1988aa346a8af2151c88719d84baefa7607d860c8972e7c3eb6a64f641cd0402. Earlier failed verifier evidence was retained, not presented as a pass.
- After checking the source was exactly the configured local clean demo and verifying the private backup hash, applied only the new additive package migration (exit 0). Existing business-table rows/columns were fingerprinted before/after and matched; the new package table remained empty. Evidence: the same backup directory's package-activation.json. Original lgl_system was not connected to or changed; no existing enrollment/payment was edited or package sale fabricated on the demo. No resets or active-demo seeders were run.
- Chrome desktop 1440px and mobile 390px package form/receipt checks passed on isolated lgl_baseline_fresh_20261102_194753_06ae92, including exact preview and zero document overflow. Private evidence: storage/app/private/package-ui/report.json and four screenshots. Desktop form/mobile receipt were visually inspected; the receipt table then received a minimum width inside its existing horizontal-scroll container for readable mobile dates/amounts. No physical-device or print/PDF check was performed for this change.

Reservation released. New sales start from Enrollments > A1 + A2 package. The local demo has the schema ready but no test package sale; operational deployment remains owner-managed.

## E01 dynamic institute certificates: discovery and design (3 November 2026, Codex)

Owner requested that passing an exam create a dynamic Student certificate and will provide the instructor signature later. Reference direction is a professional language-exam certificate layout, branded and issued by Land of German Language. This reopens the previously deferred exams/results and certificate scope; unrelated deferred modules remain deferred.

Read-only inspection found existing Exam, ExamResult and Certificate models and three legacy tables. None has active web routes. ExamResult currently compares marks with an exam's passing_marks; Certificate has no result linkage and its PDF URL method is a placeholder. Legacy number generation reads the latest row without concurrency protection, and legacy foreign keys cascade deletes. Those helpers are not a safe issuance boundary and must not simply be exposed.

Proposed behavior: create an exam for a batch/level; record and verify the Student result; issue one uniquely numbered institute certificate for a verified passing result; display Student identity, course/CEFR level, exam and issue dates, result, institute logo and named signatory. Admin manages exam/template/issuance; Teacher academic access is assigned-batch only; Student access is self only. Template editing should use bounded plain-text fields and approved dynamic placeholders, with preview and A4 browser print/Save as PDF. Signature is optional until supplied, stored privately with authenticated access and retained versions. No forged signature or external examination-provider branding/issuance claim is proposed.

Issued documents should snapshot identity, result, template and branding so later profile/template edits do not silently alter originals. Duplicate submissions must not issue duplicate certificates. Preserve originals and support audited revocation/correction rather than destructive replacement; a result correction must not leave an invalid passing certificate active. Reuse existing legacy tables through a forward-only migration, preserving historical records and repairing relevant deletion constraints where needed. No new PDF package or third-party provider call is required for browser print.

Clarification requested through chat: whether passing is Admin-verified Pass, fixed/configurable total-mark percentage, or a separate pass requirement for every exam section. No choice has been assumed or implemented. The signature can arrive later and does not block template work; the passing rule determines result validation and issuance behavior. Reserve the final implementation files/acceptance criteria again once that choice is known.

This was documentation/design work only. No application code, schema, exam, result or certificate was changed; no tests or seeders were run for E01. Existing login/package edits and historical untracked Markdown were preserved. No agents or independent review. Reservation released while awaiting the rule; E01 implementation remains pending.