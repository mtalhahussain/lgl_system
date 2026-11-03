# Local operations and deployment preparation

## Current local environment

- PHP 8.2.12, Laravel 12 requirement, local MariaDB 10.4.32 using Laravel's MySQL driver.
- `.env` selects `lgl_system_demo_20261031_125354_80b7ed` and `APP_TIMEZONE=Asia/Karachi`. The local database server's system/session timezone was inspected and aligns with Asia/Karachi.
- The original `lgl_system` database is preserved unchanged, including its original accounts, 39 enrollments and historical role enum. It is not the active application database.
- The local preview was started at `http://127.0.0.1:8791`. To start it in a later session, run `php artisan serve --host=127.0.0.1 --port=8791` if that port is free.
- Local seeded login: `admin@lgl.com` / `admin123`; Teachers: `hans.mueller@lgl.com` and `anna.schmidt@lgl.com` / `teacher123`; Students: `student1@lgl.com` through `student20@lgl.com` / `student123`. These are demo accounts. The seeder refuses environments other than local/testing.

No dependency versions were changed by implementation. The user's pre-existing Composer lock and teacher-info migration edits are preserved. Do not run `composer update` or reset those edits as a setup shortcut.

## Tests and repeatable checks

```powershell
php artisan test
php scripts/verify-concurrency.php
php scripts/verify-baseline.php D:/xammppp/mysql/bin
php scripts/report-legacy-balances.php
node scripts/verify-ui.mjs
```

PHPUnit uses SQLite `:memory:` with forced environment settings and a test bootstrap that also replaces inherited `$_SERVER` values. The separate MariaDB checks create uniquely named disposable databases and retain them for inspection. They do not migrate, seed or delete the configured database. `verify-baseline.php` takes a private SQL dump, restores it to a new DB, compares all schemas/data, runs fresh migrations/seeding and checks the restored-copy upgrade. Supply the actual MySQL client directory on another machine.

The balance reporter is read-only. It detects whether a ledger exists: legacy mode compares old cached totals/paid installments; ledger mode verifies payments, allocations, cache and schedule conservation. Reports go to ignored private storage and may contain internal account references/financial amounts.

Browser QA uses installed Chrome/Edge and Node 22 built-ins; it adds no npm dependency. `verify-ui.mjs` accepts only a loopback preview with the configured local demo, logs in to seeded roles and checks desktop/mobile navigation, keyboard input, report overflow and print output. Override `LGL_BROWSER_BINARY` or `LGL_UI_URL` when needed. It creates a separate private browser profile and saves screenshots, a receipt PDF and a result report under `storage/app/private/ui/`; keep those profiles/artifacts local, outside deployed release packages. Latest successful run: `2026-10-31T14-06-37-546Z`, 24 checks passed. This does not replace physical-device, cross-browser or real-printer assessment.

`prepare-local-demo.php` is an intentional local-only setup action: it preserves the current DB, backs up `.env`, creates and verifies a new demo DB, then switches the local DB/timezone configuration. Do not run it merely to repeat tests; each run creates another retained demo DB. It does not delete or promote legacy accounts.

## Backups and recovery

Original-state evidence: `storage/app/private/backups/baseline_20261031_113758_1469df/`. It includes the original SQL dump and source/upload archives verified by extraction and file hashes. The original DB's 23 table schemas/data were compared with that snapshot after implementation and all remained unchanged; see `storage/app/private/original-database-verification.json`.

Current demo SQL backup and restore evidence: `storage/app/private/backups/baseline_20261031_130143_be29fe/`. Its dump SHA-256 is `33d07e69dd78be5b7afd1d1fd25ab879083b4fc6d4828b069458f9662b7f719a`. All 27 restored tables matched. Fresh installation, 23 seeded users and the restored-copy upgrade passed. The final source/upload snapshot is `source-and-uploads-final.zip` in the same folder, with `files-manifest-final.json` and `file-restore-report-final.json`. Its extraction is verified against the file hashes; the verification report records the exact file count and archive hash.

The prior environment file is preserved at `storage/app/private/demo-setup/lgl_system_demo_20261031_125354_80b7ed/environment-before.env`. Backups, SQL, environment copies and private test output remain outside public storage and Git. Copy them to an approved backup destination before relying on this machine for operational data; no remote backup service has been configured.

To restore, create a **new** compatible database, import the selected SQL dump, compare its schemas/row hashes/totals, and verify the matching application release in an isolated directory. Activate only the reviewed code/DB pair. Keep the existing database and environment backup until validation passes. A rollback to pre-ledger code also needs its matching pre-ledger database/source archive; pointing new code at the legacy database is not a complete rollback.

Do not use `migrate:fresh`, `db:wipe`, automatic database drops or the demo seeder on operational records. Ledger/transfer rollback methods deliberately refuse to drop history. The pre-existing teacher-info migration still has a destructive `down()` ownership defect; it was reproduced on a disposable MariaDB probe and its file was intentionally preserved. Use a reviewed forward repair or matching backup restoration rather than rolling historical migrations back.

## Hosting checklist — not yet executed

Owner decision, 31 October 2026: deployment target is a VPS/other server, and the owner will perform server/project setup themselves. This checklist is a handoff reference only. No server configuration, SSH access or deployment work is assigned to the coding agents. Do not request hosting credentials or continue deployment preparation unless the owner asks.

1. Owner verifies VPS OS, domain, PHP extensions, database compatibility, storage permissions and a separate staging database. Exact host configuration remains owner-managed; no external deployment is authorized or claimed.
2. Prepare a reviewed release and install locked production dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`. Preserve the current Laravel/Blade stack. Existing pages use Bootstrap/Font Awesome CDN assets; confirm browser connectivity and styling on the target host. Vite dependencies were not changed or newly installed.
3. Set environment secrets privately, use `APP_ENV=production`, disable debug, enable HTTPS/secure cookies, and configure application/database timezones consistently. Keep the same app key for an existing deployment; generate a key only for a genuinely new installation.
4. Back up database, configuration and uploads; restore-test the backup before migration. Run migrations first on the staging copy. The three-role migration refuses unsupported legacy accounts; real-data migration also needs explicit role mapping and authoritative balance reconciliation. The local clean-demo path is not a general operational-data migration.
5. Provision a non-demo Admin through an approved trusted administrative process. Do not run the demo seeder in production. Verify role/record/field access, last-Admin protection, receipts, dated reversals, attendance and report reconciliation.
6. Configure public storage access if institute logo uploads are used. Keep documents, SQL backups and environment files private. Review file permissions and test a real logo upload and receipt printout in a browser.
7. No persistent queue worker or business scheduler is required by the baseline. Automatic attendance/reminder commands are disabled by default; do not restore old cron entries that invoke deferred modules. Schedule database backups only after the host's supported backup mechanism is agreed.
8. Run responsive/keyboard/print checks, record staging evidence, review deployment/rollback steps and obtain deployment authority before activation. Never discard newly posted financial activity by restoring an old snapshot without reconciliation.

Physical removal of retained modules/tables and cleanup of disposable test databases are separate reviewed actions. Nothing in this runbook grants deletion approval.

The full source archive described above records the original local handoff before P15a. Its subsequent UI/source/doc changes are supplied as `storage/app/private/ui/2026-10-31T14-06-37-546Z/source-patch.zip`; this is a source overlay, not a database restore or deployment command.

