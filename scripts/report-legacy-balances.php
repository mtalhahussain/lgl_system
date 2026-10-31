<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = Illuminate\Support\Facades\DB::select(
    "SELECT e.id AS enrollment_id, e.student_id, e.batch_id, e.status, e.total_fee, e.discount_amount, e.paid_amount,
        COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END), 0) AS paid_installments,
        COALESCE(SUM(f.amount), 0) AS scheduled_amount
     FROM enrollments e LEFT JOIN fee_installments f ON f.enrollment_id = e.id
     GROUP BY e.id, e.student_id, e.batch_id, e.status, e.total_fee, e.discount_amount, e.paid_amount"
);
$directory = storage_path('app/private/reconciliation');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$path = $directory.'/legacy-'.gmdate('Ymd_His').'-'.bin2hex(random_bytes(3)).'.json';
$mismatches = array_filter($rows, fn ($row) => \App\Support\Money::minor((string) $row->paid_amount)
    !== \App\Support\Money::minor((string) $row->paid_installments));
$mode = 'legacy';
if (\Illuminate\Support\Facades\Schema::hasTable('payments')) {
    $mode = 'ledger';
    $mismatches = [];
    foreach ($rows as $row) {
        try {
            app(\App\Services\PaymentService::class)->assertReconciled(\App\Models\Enrollment::findOrFail($row->enrollment_id));
            $row->reconciled = true;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $row->reconciled = false;
            $mismatches[] = $row;
        }
    }
}
file_put_contents($path, json_encode(['database' => config('database.connections.'.config('database.default').'.database'),
    'generated_at' => gmdate(DATE_ATOM), 'mode' => $mode, 'rows' => $rows, 'mismatches' => count($mismatches)], JSON_PRETTY_PRINT));
echo json_encode(['enrollments' => count($rows), 'mode' => $mode, 'mismatches' => count($mismatches), 'private_report' => $path,
    'data_changed' => false], JSON_PRETTY_PRINT).PHP_EOL;
