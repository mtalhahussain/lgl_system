# Local implementation acceptance

Date: 31 October 2026. Scope: the simplified three-role core on a clean local demo, not production deployment. Codex performed implementation and self-review; no independent agent/reviewer or production sign-off is claimed.

## Evidence

| Area | Result | Evidence |
|---|---|---|
| Three roles | PASS locally | Role enum/constants, login/session denial for retired roles, Admin account access, last-Admin guard; `AuthenticationSecurityTest`, `AccountAndProfileTest` |
| People/course workflows | PASS | Create/edit/search, password handling, wrong-profile rejection, deactivation/history preservation, course price snapshot; `PeopleAndCourseTest` |
| Batch/session workflows | PASS | Existing-schema forms, teacher assignment validation, no salary side effects, assigned sessions, cancellation/history, no moving recorded attendance; `BatchAndSessionTest` |
| Authorization/privacy | PASS for active web routes | Teacher row/field/aggregate scopes, Student self-only records, restricted reports/settings/accounts/device routes, owned receipts; `AccessControlTest`, `PaymentIntegrityTest` |
| Admissions | PASS | Atomic registration/enrollment, duplicate/capacity handling, exact discount and rounding, equal/custom schedules and month ends; `EnrollmentIntegrityTest` |
| Ledger integrity | PASS | Partial/early/full payment, duplicate/conflicting key handling, overpayment rejection, immutable receipts, unique full reversal, restored debt, dated reports; `PaymentIntegrityTest`, `CoreWorkflowTest` |
| Receipt identity | PASS | Student/batch/course/institute/currency/timezone details are snapshotted; changing a profile does not rewrite the original receipt; `CoreWorkflowTest` |
| Transfers/withdrawal | PASS within restricted scope | Unpaid same-course transfer moves one schedule; paid transfer/refund refused; withdrawal preserves remaining debt/history; `CoreWorkflowTest` |
| Manual attendance | PASS | Membership/assignment/date checks, partial entry, blank remains unmarked, correction reason/audit, historical roster eligibility, excused/cancelled denominator and completeness; `AttendanceIntegrityTest` |
| Dashboards/reports | PASS locally | Three role dashboards; enrollment, collections, dues and attendance reports; totals reconcile; `CoreWorkflowTest` |
| Settings/profile | PASS | Validated settings/uploads, unsupported field rejection, own-profile scope, current-password verification; `SettingsSecurityTest`, `AccountAndProfileTest` |
| Deferred automation | PASS | No scheduled business tasks; automatic absence/reminder commands refuse execution; no message delivery in tests; `CoreWorkflowTest`, `php artisan schedule:list` |
| Seed consistency | PASS | 23 users, three roles, all `@lgl.com`, payment/allocation/schedule balances reconcile; `SeederConsistencyTest` |
| Test isolation | PASS | SQLite in-memory and safe cache/session/mail; inherited MySQL environment override regression; `TestEnvironmentTest`, `tests/bootstrap.php` |
| MariaDB races | PASS | Separate concurrent processes: one capacity-one admission, no concurrent overpayment, one receipt for same-key replay, cross-account key collision rejected, one full reversal; private `concurrency/lgl_baseline_concurrency_20261031_130753_f1f48a/report.json` |
| Compatible DB install/upgrade/restore | PASS on current demo | 24 migrations; fresh seed; restored-copy upgrade; all 27 restored schemas/data match; private `backups/baseline_20261031_130143_be29fe/report.json` |
| Original data preservation | PASS | All 23 original table hashes/schemas match the initial backup; original lock/migration file hashes match the P01 file manifest |
| HTTP integration | PASS | Real local HTTP login/session/CSRF and page/access checks across three roles: 45 checks, 0 failures; private `http-smoke-20261031_125824.json` |
| Local browser UI/print | PASS for inspected cases | P15a: Chrome desktop 1366×900/mobile 390×844, role dashboard links, keyboard Tab/Enter, mobile menus, four report overflow checks, print emulation and PDF generation; 24 checks / 0 failures |
| Physical device/production validation | NOT RUN | VPS setup belongs to the owner; no physical-phone assessment, independent review, physical printer or production deployment |

The automated suite contains 52 tests after the deferred-command check was added. Exact final command output is recorded in the task handoff; assertion count varies slightly because the demo seeder deliberately creates a random number of enrollments. PHP syntax checks and targeted Pint checks were also performed. HTTP smoke checks verify responses and access, not visual rendering of CDN assets.

## Active demo and preserved legacy state

Active DB: `lgl_system_demo_20261031_125354_80b7ed`; 1 Admin, 2 Teachers, 20 Students, 45 enrollments and 45 ledger payments. Read-only reconciliation reports zero mismatches. `.env` now selects this DB and Asia/Karachi time.

The owner confirmed the original records were demo/testing data and removed the Accountant role from the intended product. The original `lgl_system` DB was retained unchanged instead of silently promoting its Accountant or manufacturing missing payment history. The old 39 balance mismatches remain preserved as historical evidence; no original balances were overwritten.

Before this clean-demo activation, a restored legacy copy correctly refused the three-role migration because it still contained an unsupported account. That expected refusal is not a successful general legacy upgrade. Current-demo restore/upgrade checks pass because the active data already conforms to the approved model.

## Remaining boundaries

- Operational-data imports, paid transfers, refunds, partial reversals, branch isolation and deferred modules require separate requirements/testing.
- The historical teacher-info migration's rollback defect remains documented; protected financial migrations and the runbook prohibit destructive rollback shortcuts.
- Deferred tables/services/templates and disposable databases remain retained. Physical cleanup is not approved or performed.
- No new packages were installed. The owner selected a VPS/other server and will perform server/project setup themselves. Deployment, remote backups and production activation are owner-managed and unverified; [RUNBOOK.md](RUNBOOK.md) is a handoff reference.

Final regression command: `php artisan test --compact` — **52 tests passed, 511 assertions**. Targeted Pint check passed for 31 new PHP files. Six critical HTTP pages also returned 200 after the last query changes. Final source/upload snapshot: `source-and-uploads-final.zip`, with `files-manifest-final.json` and `file-restore-report-final.json`, in the current private backup folder named above.

## P15a browser follow-up — 31 October 2026

The owner requested remaining tasks and a module count. Ten functional groups are implemented locally; see TASKS.md. This follow-up fixed the role-agnostic brand link (Teacher/Student previously hit Admin), added an accessible mobile sidebar toggle, distinguished navigation/account controls, wrapped report tables for narrow screens and clarified that printing a paginated collection report prints the current rows while its net total covers the selected period.

`node scripts/verify-ui.mjs`: **24 passed, 0 failed** using real local Chrome. Evidence: `storage/app/private/ui/2026-10-31T14-06-37-546Z/report.json`, desktop/mobile screenshots and `receipt.pdf`. Admin mobile, Teacher mobile menu, Student mobile menu, Admin desktop and print-emulation screenshots were visually inspected. Keyboard tests use native browser Tab/Enter input. The disposable browser profile disables password-manager prompts so native credential UI does not intercept those inputs; application authentication was not changed.

`php artisan test --compact`: **52 passed, 525 assertions** after the UI fixes. No schema reset, runtime dependency installation, original-data modification or server/deployment setup was performed. This is one Chromium browser with emulated viewports, not a cross-browser/WCAG certification or physical-device/printer test. Earlier browser harness diagnostic runs remain retained; the successful report above is the final P15a result.

The earlier full source archive predates P15a. The follow-up source/doc changes are packaged separately as `source-patch.zip` in the successful UI evidence folder; it does not include browser profiles or alter database backups.
