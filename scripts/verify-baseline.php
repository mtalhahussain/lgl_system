<?php

// Local safety verification only. Never runs migrations or seeders on the source database.
require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mysqlBin = $argv[1] ?? '';
$suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
$dumpBinary = $mysqlBin.'/mysqldump'.$suffix;
$mysqlBinary = $mysqlBin.'/mysql'.$suffix;
if (! is_file($dumpBinary) || ! is_file($mysqlBinary)) {
    fwrite(STDERR, "Usage: php scripts/verify-baseline.php <mysql-bin-directory>\n");
    exit(1);
}

$source = config('database.connections.'.config('database.default'));
if (($source['driver'] ?? null) !== 'mysql' || ! empty($source['url'])) {
    throw new RuntimeException('Requires an explicit MySQL connection without DB_URL.');
}
$stamp = gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$restoreName = 'lgl_baseline_restore_'.$stamp;
$freshName = 'lgl_baseline_fresh_'.$stamp;
$directory = storage_path('app/private/backups/baseline_'.$stamp);
if (! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create private backup directory.');
}
$credentials = $directory.'/client.cnf';
$dump = $directory.'/database.sql';
$report = ['source_database' => $source['database'], 'restore_database' => $restoreName,
    'fresh_database' => $freshName, 'directory' => $directory, 'started_utc' => gmdate(DATE_ATOM)];

function runProcess(array $command, string $log, ?string $input = null, ?array $environment = null): int
{
    $process = proc_open($command, [0 => ['file', $input ?? (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'), 'r'],
        1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__), $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start verification process.');
    }

    return proc_close($process);
}

function databaseFingerprint(PDO $pdo): array
{
    $result = [];
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $table) {
        if ($table[1] !== 'BASE TABLE') {
            throw new RuntimeException('Views require a separate restore verification design.');
        }
        $quoted = '`'.str_replace('`', '``', $table[0]).'`';
        $hashes = [];
        foreach ($pdo->query('SELECT * FROM '.$quoted) as $row) {
            $hashes[] = hash('sha256', serialize($row));
        }
        sort($hashes);
        $result[$table[0]] = ['count' => count($hashes), 'hash' => hash('sha256', implode('', $hashes)),
            'schema_hash' => hash('sha256', $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1])];
    }
    ksort($result);

    return $result;
}

try {
    $ini = "[client]\n";
    foreach (['host' => $source['host'], 'port' => $source['port'], 'user' => $source['username'], 'password' => $source['password']] as $key => $value) {
        $ini .= $key.'="'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value)."\"\n";
    }
    if (! empty($source['unix_socket'])) {
        $ini .= 'socket="'.str_replace('"', '\\"', $source['unix_socket'])."\"\n";
    }
    file_put_contents($credentials, $ini);
    chmod($credentials, 0600);
    $backupExit = runProcess([$dumpBinary, '--defaults-extra-file='.$credentials, '--single-transaction',
        '--skip-lock-tables', '--routines', '--events', '--triggers', '--hex-blob', '--default-character-set=utf8mb4',
        '--result-file='.$dump, $source['database']], $directory.'/backup.log');
    if ($backupExit !== 0 || ! is_file($dump) || filesize($dump) === 0) {
        throw new RuntimeException('Backup failed; inspect the private backup log.');
    }
    $report['dump_sha256'] = hash_file('sha256', $dump);
    $sourceConnection = Illuminate\Support\Facades\DB::connection();
    $report['server_version'] = $sourceConnection->selectOne('SELECT VERSION() AS version')->version;
    foreach ([$restoreName, $freshName] as $name) {
        if (! preg_match('/^lgl_baseline_(restore|fresh)_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D', $name) || $name === $source['database']) {
            throw new RuntimeException('Unsafe disposable database name.');
        }
        // No IF NOT EXISTS: never reuse a database from another run.
        $sourceConnection->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    if (runProcess([$mysqlBinary, '--defaults-extra-file='.$credentials, '--default-character-set=utf8mb4', $restoreName],
        $directory.'/restore.log', $dump) !== 0) {
        throw new RuntimeException('Restore failed; inspect the private restore log.');
    }
    $restored = $source;
    $restored['database'] = $restoreName;
    config(['database.connections.baseline_restore' => $restored]);
    $sourceFingerprint = databaseFingerprint($sourceConnection->getPdo());
    $restoreFingerprint = databaseFingerprint(Illuminate\Support\Facades\DB::connection('baseline_restore')->getPdo());
    $report['restored_tables_match'] = $sourceFingerprint === $restoreFingerprint;
    $report['source_tables'] = $sourceFingerprint;
    if (! $report['restored_tables_match']) {
        throw new RuntimeException('Source and restored fingerprints differ; check activity or restore compatibility.');
    }
    // Child-only environment prevents any change to .env or the source connection.
    $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => $freshName, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'APP_CONFIG_CACHE' => $directory.'/absent-config.php']);
    $report['fresh_migrate_exit'] = runProcess([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'],
        $directory.'/fresh-migrate.log', null, $environment);
    $environment['DB_DATABASE'] = $restoreName;
    $report['restored_upgrade_exit'] = runProcess([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'],
        $directory.'/restored-upgrade.log', null, $environment);
    $environment['DB_DATABASE'] = $freshName;
    if ($report['fresh_migrate_exit'] === 0) {
        $report['fresh_seed_exit'] = runProcess([PHP_BINARY, 'artisan', 'db:seed', '--force', '--no-interaction'],
            $directory.'/fresh-seed.log', null, $environment);
        $fresh = $source;
        $fresh['database'] = $freshName;
        config(['database.connections.baseline_fresh' => $fresh]);
        $report['seed_users'] = Illuminate\Support\Facades\DB::connection('baseline_fresh')->table('users')->count();
        $report['seed_non_lgl_emails'] = Illuminate\Support\Facades\DB::connection('baseline_fresh')->table('users')->where('email', 'not like', '%@lgl.com')->count();
    }
    $report['source_unchanged'] = $sourceFingerprint === databaseFingerprint($sourceConnection->getPdo());
    $report['success'] = $report['source_unchanged'] && $report['fresh_migrate_exit'] === 0
        && $report['restored_upgrade_exit'] === 0 && ($report['fresh_seed_exit'] ?? 1) === 0
        && ($report['seed_users'] ?? 0) === 23 && ($report['seed_non_lgl_emails'] ?? -1) === 0;
} catch (Throwable $exception) {
    $report['success'] = false;
    // Keep credentials out of terminal output and the shareable report.
    $report['failure'] = 'Verification stopped: '.$exception::class.'; inspect private logs.';
} finally {
    if (is_file($credentials)) {
        unlink($credentials);
    }
    file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
echo json_encode(array_diff_key($report, ['source_tables' => true]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(($report['success'] ?? false) ? 0 : 1);
