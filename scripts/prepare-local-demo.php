<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment('local')) {
    throw new RuntimeException('Local demo preparation requires APP_ENV=local.');
}
$source = config('database.connections.'.config('database.default'));
if ($source['driver'] !== 'mysql' || ! empty($source['url'])) {
    throw new RuntimeException('An explicit local MySQL connection is required.');
}
$sourceConnection = Illuminate\Support\Facades\DB::connection();
function fingerprints(PDO $pdo): array
{
    $tables = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $quoted = '`'.str_replace('`', '``', $table).'`';
        $rows = [];
        foreach ($pdo->query('SELECT * FROM '.$quoted) as $row) {
            $rows[] = hash('sha256', serialize($row));
        }
        sort($rows);
        $tables[$table] = hash('sha256', implode('', $rows).$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1]);
    }
    ksort($tables);

    return $tables;
}
$before = fingerprints($sourceConnection->getPdo());
$database = 'lgl_system_demo_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$directory = storage_path('app/private/demo-setup/'.$database);
mkdir($directory, 0700, true);
if (! copy(base_path('.env'), $directory.'/environment-before.env')) {
    throw new RuntimeException('Cannot back up local environment.');
}
$sourceConnection->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'APP_TIMEZONE' => 'Asia/Karachi', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
    'DB_HOST' => $source['host'], 'DB_PORT' => (string) $source['port'], 'DB_USERNAME' => $source['username'],
    'DB_PASSWORD' => $source['password'], 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'APP_CONFIG_CACHE' => $directory.'/absent-config.php']);
foreach ([['migrate', '--force', '--no-interaction'], ['db:seed', '--force', '--no-interaction']] as $command) {
    $process = proc_open(array_merge([PHP_BINARY, 'artisan'], $command), [0 => ['pipe', 'r'],
        1 => ['file', $directory.'/setup.log', 'a'], 2 => ['file', $directory.'/setup.log', 'a']], $pipes, dirname(__DIR__), $environment);
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Demo preparation failed; .env has not changed. Inspect private setup.log.');
    }
}
$demo = $source;
$demo['database'] = $database;
config(['database.connections.demo' => $demo, 'database.default' => 'demo']);
$roles = App\Models\User::selectRaw('role, COUNT(*) AS total')->groupBy('role')->pluck('total', 'role')->all();
if ((int) ($roles['admin'] ?? 0) !== 1 || (int) ($roles['teacher'] ?? 0) !== 2 || (int) ($roles['student'] ?? 0) !== 20 || count($roles) !== 3
    || App\Models\User::where('email', 'not like', '%@lgl.com')->exists()) {
    throw new RuntimeException('Unexpected demo account data; .env has not changed.');
}
foreach (App\Models\Enrollment::all() as $enrollment) {
    app(App\Services\PaymentService::class)->assertReconciled($enrollment);
}
if ($before !== fingerprints($sourceConnection->getPdo())) {
    throw new RuntimeException('Original DB changed during setup. Review concurrent activity before activation; .env has not changed.');
}
$env = file_get_contents(base_path('.env'));
$updated = preg_replace('/^DB_DATABASE=[^\r\n]*/m', 'DB_DATABASE='.$database, $env, -1, $matches);
if (preg_match('/^APP_TIMEZONE=/m', $updated)) {
    $updated = preg_replace('/^APP_TIMEZONE=[^\r\n]*/m', 'APP_TIMEZONE=Asia/Karachi', $updated);
} else {
    $updated .= PHP_EOL.'APP_TIMEZONE=Asia/Karachi'.PHP_EOL;
}
if ($matches !== 1 || file_put_contents(base_path('.env'), $updated) === false) {
    throw new RuntimeException('Could not activate demo database. Environment backup is preserved.');
}
$report = ['original_database' => $source['database'], 'active_demo_database' => $database, 'roles' => $roles,
    'enrollments' => App\Models\Enrollment::count(), 'payments' => App\Models\Payment::count(), 'source_tables_unchanged' => true,
    'all_demo_balances_reconciled' => true, 'environment_backup' => $directory.'/environment-before.env'];
file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
