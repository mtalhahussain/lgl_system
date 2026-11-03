# P01 local safety baseline

Historical P01 evidence. Later implementation prepared and activated a clean three-role demo while preserving this original DB/snapshot. See ACCEPTANCE.md and RUNBOOK.md for the current state and later 27-table restore evidence.

Verified on 31 October 2026. Authorization: the owner approved the P01 recommendation and requested `@lgl.com` email domains in seeders. This does not approve role migration or the rest of the roadmap. D08 remains unresolved for production hosting/data classification; the work below concerns the connected local database only.

## Results

- Source: local `lgl_system`, MariaDB 10.4.32. A transactional SQL dump was taken without locking or changing source business records.
- Backup folder: `storage/app/private/backups/baseline_20261031_113758_1469df/` (ignored by Git).
- SQL backup: `database.sql`; SHA-256 `211ce341fbd0f829d53ddc07efe0303c54b82a5b5a0661b870cfa8afddab0ee0`.
- Restored database: `lgl_baseline_restore_20261031_113758_1469df`. All 23 table schemas, row counts and order-independent row hashes matched the source after restoration.
- Fresh database: `lgl_baseline_fresh_20261031_113758_1469df`. All 21 migrations passed; the modified seeders passed and created 24 users, all with `@lgl.com` addresses. The institute contact seed is `info@lgl.com`.
- Existing-data upgrade on the restored copy: `Nothing to migrate.` This proves current migration history is fully applied; it does not prove future schema changes or rollback safety.
- A second source fingerprint matched after migration/seed checks on the disposable databases: source schema and all table contents remained unchanged during verification.
- Both disposable databases are intentionally retained for inspection. No databases were dropped and no seeders were run against the configured application database.
- `source-and-uploads-final.zip` in the backup folder preserves source, configuration and local application uploads; `files-manifest-final.json` records file hashes. Dependency directories and Git history are excluded; dependencies can be restored from lock files. A separate extraction check verifies every archived file against the manifest. This snapshot is of the current local checkout, including pre-existing edits and this task's changes, not a pristine pre-edit checkout.

Private logs and machine-readable evidence are in the same folder: `report.json`, `backup.log`, `restore.log`, `fresh-migrate.log`, `restored-upgrade.log` and `fresh-seed.log`. Seed output includes demo credentials; keep this folder private and never upload it to Git or a public directory. Temporary database-client credentials were removed when verification ended.

## Repeat verification

Run from the repository root using the installed MySQL/MariaDB client directory:

```powershell
php scripts/verify-baseline.php D:/xammppp/mysql/bin
php artisan test
```

The verification script creates a new private dump and two uniquely named databases per run. It never reuses or drops a database. Its only source operations are inspection and dumping; migrations and seeders use child-process environment overrides for the new databases. A failed step reports failure and preserves evidence. The client path is local-machine-specific; pass another directory on another machine. The script verifies the SQL restore; the file snapshot/extraction verification is a separate P01 step described above.

Run with source activity paused to obtain stable comparisons. A changed source fingerprint is a verification failure, not permission to overwrite source data. The database account must be able to create disposable databases. Do not run repeatedly without accounting for retained copies.

## Automated test isolation

`phpunit.xml` and `tests/bootstrap.php` force testing environment, SQLite `:memory:`, empty DB URL, array cache/session/mail and synchronous queue, even if parent-shell variables target MySQL. The bootstrap synchronizes environment values into `$_SERVER` as well as `$_ENV` and `getenv()` before Laravel boots. PHPUnit's `force` flag alone left inherited `$_SERVER` values unchanged; a hostile-environment check reproduced this issue before the bootstrap fix. A separate testing config-cache path bypasses the application's cached configuration; bootstrap refuses to run if a file exists at that reserved path.

`tests/Feature/TestEnvironmentTest.php` verifies those settings and opens a SQLite PDO connection. Routine PHPUnit uses in-memory storage. Compatible MariaDB migration/seed checks above are separate from the SQLite suite; this distinction prevents a false claim that SQLite verifies all database behavior.

## Remaining findings

- The seeder deliberately writes random enrollment `paid_amount` values and creates only the remaining pending installment; it creates no corresponding historical paid installments. This explains the observed mismatch for sample seed data. Do not assume operational records have the same provenance or automatically repair them.
- The user's pre-existing teacher-info migration edit allows fresh migration to pass, but its `down()` still drops columns created by another migration. P03 must address this separately; no migration file was changed here.
- Exactly three roles have not been implemented: the Accountant remains in seeders and the source database pending the approved account-mapping task.
- There is no deployed-host audit, production backup proof, business permission suite or financial cutover evidence yet. The roadmap estimate remains 13–23 engineer-days provisionally; fresh-install success removes one uncertainty but does not reduce financial/security scope.

Next recommended task: approve P02 access-control fixes. P01 was self-reviewed by Codex; no independent reviewer was used or claimed.

Final checks: php artisan test passed 3 tests / 11 assertions. Direct PHPUnit with inherited MySQL database variables and an invalid DB URL passed the isolation check (1 test / 9 assertions) after the bootstrap fix. The first file snapshot is retained; the final snapshot includes this fix.

