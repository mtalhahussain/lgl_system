# Task register and evidence

## Current state

31 October 2026: ten functional groups implemented locally; see [PROJECT.md](PROJECT.md). Owner approved automatic incremental work, three roles, Admin-managed accounting and demo-only data. Owner will handle VPS setup. No external deployment or operational-data import is claimed.

Current reservation: none. Codex was the sole writer/integrator; no concurrent agents, independent review, staging or commits are claimed. Preserve existing unrelated changes, especially composer.lock and the teacher-info migration.

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
