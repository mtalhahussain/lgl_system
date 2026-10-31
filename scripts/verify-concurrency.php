<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

if (($argv[1] ?? null) === '--worker') {
    $database = config('database.connections.'.config('database.default').'.database');
    if (! app()->environment('testing') || ! preg_match('/^lgl_baseline_concurrency_[0-9_]+[a-f0-9]{6}$/D', $database)) {
        throw new RuntimeException('Worker requires a newly created concurrency test database.');
    }
    $deadline = microtime(true) + 15;
    while (! is_file($argv[2])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timed out.');
        }
        usleep(20000);
    }
    try {
        if ($argv[3] === 'admit') {
            $result = app(EnrollmentService::class)->enrollStudent((int) $argv[4], (int) $argv[5]);
        } elseif ($argv[3] === 'reverse') {
            $result = app(PaymentService::class)->reverse(Payment::findOrFail($argv[4]), User::findOrFail($argv[5]), $argv[6]);
        } else {
            $result = app(PaymentService::class)->pay((int) $argv[4], $argv[5], 'cash', $argv[6], User::findOrFail($argv[7]));
        }
        echo json_encode(['outcome' => 'accepted', 'id' => $result->id]);
        exit(0);
    } catch (InvalidArgumentException|Illuminate\Validation\ValidationException $exception) {
        echo json_encode(['outcome' => 'rejected', 'type' => $exception::class]);
        exit(0);
    }
}

$source = config('database.connections.'.config('database.default'));
if ($source['driver'] !== 'mysql' || ! empty($source['url'])) {
    throw new RuntimeException('Explicit MySQL connection required.');
}
$database = 'lgl_baseline_concurrency_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$directory = storage_path('app/private/concurrency/'.$database);
mkdir($directory, 0700, true);
DB::connection()->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
    'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'APP_CONFIG_CACHE' => $directory.'/absent-config.php']);
$process = proc_open([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'],
    [0 => ['pipe', 'r'], 1 => ['file', $directory.'/migrate.log', 'a'], 2 => ['file', $directory.'/migrate.log', 'a']],
    $pipes, dirname(__DIR__), $environment);
fclose($pipes[0]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Disposable migrations failed; inspect private migrate.log.');
}
$source['database'] = $database;
config(['database.connections.concurrency' => $source, 'database.default' => 'concurrency']);
$admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
$teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
$students = User::factory()->count(2)->create(['role' => 'student', 'is_active' => true]);
$course = Course::create(['name' => 'Concurrency', 'level' => 'A1', 'total_fee' => '100.00',
    'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
$batch = Batch::create(['name' => 'Capacity one', 'teacher_id' => $teacher->id, 'course_id' => $course->id,
    'start_date' => today(), 'status' => 'ongoing', 'max_students' => 1]);

function race(array $operations, string $directory, array $environment, string $name): array
{
    $barrier = $directory.'/'.$name.'.start';
    $processes = [];
    foreach ($operations as $index => $operation) {
        $output = $directory.'/'.$name.'-'.$index.'.json';
        $process = proc_open(array_merge([PHP_BINARY, 'scripts/verify-concurrency.php', '--worker', $barrier], $operation),
            [0 => ['pipe', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $directory.'/workers.log', 'a']],
            $pipes, dirname(__DIR__), $environment);
        fclose($pipes[0]);
        $processes[] = [$process, $output];
    }
    touch($barrier);
    $results = [];
    foreach ($processes as [$process, $output]) {
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Worker failed; inspect private workers.log.');
        }
        $result = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        $results[] = $result;
    }

    return $results;
}

$admissions = race([['admit', $students[0]->id, $batch->id], ['admit', $students[1]->id, $batch->id]], $directory, $environment, 'admissions');
if (Enrollment::count() !== 1 || count(array_filter($admissions, fn ($row) => $row['outcome'] === 'accepted')) !== 1) {
    throw new RuntimeException('Concurrent admission capacity check failed.');
}
$enrollment = Enrollment::sole();
$payments = race([['pay', $enrollment->id, '80.00', (string) Illuminate\Support\Str::uuid(), $admin->id],
    ['pay', $enrollment->id, '80.00', (string) Illuminate\Support\Str::uuid(), $admin->id]], $directory, $environment, 'payments');
if (Payment::count() !== 1 || $enrollment->fresh()->paid_amount !== '80.00') {
    throw new RuntimeException('Concurrent overpayment check failed.');
}
$key = (string) Illuminate\Support\Str::uuid();
$replays = race([['pay', $enrollment->id, '10.00', $key, $admin->id], ['pay', $enrollment->id, '10.00', $key, $admin->id]],
    $directory, $environment, 'replays');
if (Payment::count() !== 2 || $enrollment->fresh()->paid_amount !== '90.00'
    || $replays[0]['outcome'] !== 'accepted' || $replays[1]['outcome'] !== 'accepted' || $replays[0]['id'] !== $replays[1]['id']) {
    throw new RuntimeException('Concurrent replay check failed.');
}
$another = Batch::create(['name' => 'Second account', 'teacher_id' => $teacher->id, 'course_id' => $course->id,
    'start_date' => today(), 'status' => 'ongoing', 'max_students' => 10]);
$otherStudent = $students->first(fn ($student) => $student->id !== $enrollment->student_id);
$otherEnrollment = app(EnrollmentService::class)->enrollStudent($otherStudent->id, $another->id);
$crossKey = (string) Illuminate\Support\Str::uuid();
$crossAccount = race([['pay', $enrollment->id, '10.00', $crossKey, $admin->id],
    ['pay', $otherEnrollment->id, '10.00', $crossKey, $admin->id]], $directory, $environment, 'cross-account');
if (Payment::count() !== 3 || count(array_filter($crossAccount, fn ($row) => $row['outcome'] === 'accepted')) !== 1) {
    throw new RuntimeException('Cross-account submission-key collision check failed.');
}
$firstPayment = Payment::orderBy('id')->firstOrFail();
$reversals = race([['reverse', $firstPayment->id, $admin->id, 'Duplicate reversal race'],
    ['reverse', $firstPayment->id, $admin->id, 'Duplicate reversal race']], $directory, $environment, 'reversals');
if (App\Models\PaymentReversal::count() !== 1 || count(array_filter($reversals, fn ($row) => $row['outcome'] === 'accepted')) !== 1) {
    throw new RuntimeException('Concurrent reversal check failed.');
}
foreach (Enrollment::all() as $account) {
    app(PaymentService::class)->assertReconciled($account);
}
$report = ['database' => $database, 'admissions' => $admissions, 'payments' => $payments, 'replays' => $replays,
    'cross_account_collision' => $crossAccount, 'reversals' => $reversals,
    'source_business_data_changed' => false, 'success' => true];
file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
