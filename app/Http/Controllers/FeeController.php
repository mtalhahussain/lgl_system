<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Services\OverviewService;
use App\Services\PaymentService;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin')->except('studentFees');
    }

    public function index(Request $request)
    {
        return $this->ledger($request);
    }

    public function studentFees(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);
        $this->authorize('viewFinancials', $student);

        return $this->ledger($request, $student);
    }

    public function batchFees(Request $request, Batch $batch)
    {
        return $this->ledger($request, null, $batch);
    }

    private function ledger(Request $request, ?User $student = null, ?Batch $batch = null)
    {
        abort_unless(Schema::hasTable('payments'), 503, 'Fee collection is temporarily unavailable. Contact the administrator.');
        $global = ! $student && ! $batch;
        $batches = $global ? Batch::orderBy('name')->pluck('name', 'id') : collect();
        $dueStatuses = ['due' => 'Has balance', 'overdue' => 'Overdue', 'paid' => 'Fully paid'];
        $list = ListQuery::for(Enrollment::with(['student:id,name,email', 'batch:id,name,course_id', 'batch.course:id,name,level', 'feeInstallments.allocations.payment.reversal'])
            ->when($student, fn ($query) => $query->where('student_id', $student->id))
            ->when($batch, fn ($query) => $query->where('batch_id', $batch->id)), $request)
            ->search(['student.name', 'student.email'])
            ->filter('batch_id', fn ($query, $id) => $query->where('batch_id', $id), $batches->keys()->all())
            ->filter('due', fn ($query, $due) => match ($due) {
                'overdue' => $query->whereHas('feeInstallments', fn ($row) => $row->outstanding()->whereDate('due_date', '<', today())),
                'due' => $query->whereHas('feeInstallments', fn ($row) => $row->outstanding()),
                'paid' => $query->has('feeInstallments')->whereDoesntHave('feeInstallments', fn ($row) => $row->outstanding()),
            }, array_keys($dueStatuses))
            ->sorts(['admitted' => 'enrollment_date',
                'student' => fn ($query, $direction) => $query->orderBy(User::select('name')->whereColumn('users.id', 'enrollments.student_id'), $direction),
                'balance' => fn ($query, $direction) => $query->orderByRaw('(enrollments.total_fee - enrollments.discount_amount - enrollments.paid_amount + enrollments.refunded_amount) '.$direction)], '-admitted');
        if ($list->wantsCsv()) {
            return $list->csv('fee-ledger', ['Student' => fn ($row) => $row->student?->name, 'Batch' => fn ($row) => $row->batch?->name,
                'Net agreed' => fn ($row) => Money::decimal(max(0, Money::minor($row->total_fee) - Money::minor($row->discount_amount))),
                'Paid' => fn ($row) => $row->net_paid, 'Outstanding' => fn ($row) => $row->remaining_fee,
                'Next due' => fn ($row) => $row->feeInstallments->sortBy('due_date')->first(fn ($item) => Money::minor($item->outstanding_amount) > 0)?->due_date?->toDateString()]);
        }
        $debt = app(OverviewService::class)->debtTotals($list->totalsQuery());
        $kpis = [['label' => 'Enrollments', 'value' => $list->counts()['total'], 'icon' => 'fa-user-graduate'],
            ['label' => 'Outstanding', 'value' => currency_format($debt['outstanding']), 'icon' => 'fa-wallet'],
            ['label' => 'Overdue', 'value' => currency_format($debt['overdue']), 'icon' => 'fa-triangle-exclamation']];
        if ($global) {
            $kpis[] = ['label' => 'Collected this month', 'value' => currency_format(app(OverviewService::class)->financialTotals()['This month net collected']),
                'icon' => 'fa-coins', 'note' => 'All enrollments, net of reversals'];
        }
        $filters = $global ? [['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches], ['name' => 'due', 'label' => 'Balance', 'options' => $dueStatuses]]
            : [['name' => 'due', 'label' => 'Balance', 'options' => $dueStatuses]];
        $enrollments = $list->paginate();
        $payments = Payment::with(['enrollment.student:id,name', 'reversal'])
            ->when($student, fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $student->id)))
            ->when($batch, fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('batch_id', $batch->id)))
            ->orderByDesc('id')->paginate(20, ['*'], 'payments_page')->withQueryString();

        $refunds = \App\Models\FeeRefund::with('enrollment.student:id,name')
            ->when($student, fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $student->id)))
            ->when($batch, fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('batch_id', $batch->id)))
            ->orderByDesc('id')->paginate(20, ['*'], 'refunds_page')->withQueryString();

        return view('fees.ledger', compact('enrollments', 'payments', 'refunds', 'student', 'batch', 'kpis', 'filters'));
    }

    public function pay(Request $request, FeeInstallment $installment, PaymentService $service)
    {
        $data = $request->validate(['amount' => 'nullable|decimal:0,2|gt:0',
            'payment_method' => 'required|in:cash,bank,card,jazzcash,easypaisa,online',
            'transaction_reference' => 'nullable|string|max:255', 'submission_key' => 'required|string|min:16|max:64']);
        $payment = $service->pay($installment->enrollment_id, (string) ($data['amount'] ?? $installment->outstanding_amount),
            $data['payment_method'], $data['submission_key'], $request->user(), $data['transaction_reference'] ?? null, $installment->id);

        return redirect()->route('payments.show', $payment)->with('success', 'Payment recorded.');
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'batch_id' => 'nullable|integer', 'course_id' => 'nullable|integer', 'method' => 'nullable|in:'.implode(',', array_keys(self::METHODS))]);
        $start = \Carbon\Carbon::parse($data['start_date'] ?? today()->startOfMonth())->startOfDay();
        $end = \Carbon\Carbon::parse($data['end_date'] ?? today())->endOfDay();
        $scope = fn ($query) => $query
            ->when(! empty($data['batch_id']), fn ($query) => $query->where('batch_id', $data['batch_id']))
            ->when(! empty($data['course_id']), fn ($query) => $query->whereHas('batch', fn ($batch) => $batch->where('course_id', $data['course_id'])));
        $paymentQuery = Payment::whereBetween('paid_at', [$start, $end])->whereHas('enrollment', $scope)
            ->when(! empty($data['method']), fn ($query) => $query->where('method', $data['method']));
        $reversalQuery = PaymentReversal::whereBetween('payment_reversals.created_at', [$start, $end])->whereHas('payment.enrollment', $scope)
            ->when(! empty($data['method']), fn ($query) => $query->whereHas('payment', fn ($payment) => $payment->where('method', $data['method'])));
        $refundQuery = \App\Models\FeeRefund::whereBetween('refunded_at', [$start, $end])->whereHas('enrollment', $scope)
            ->when(! empty($data['method']), fn ($query) => $query->where('method', $data['method']));
        if ($request->query('export') === 'csv') {
            $rows = (function () use ($paymentQuery, $reversalQuery, $refundQuery) {
                foreach ((clone $paymentQuery)->with('enrollment.student:id,name')->orderBy('paid_at')->lazy(500) as $payment) {
                    yield [$payment->paid_at?->format('Y-m-d H:i'), 'Payment', $payment->receipt_number, $payment->enrollment->student->name, $payment->method, $payment->amount];
                }
                foreach ((clone $reversalQuery)->with('payment.enrollment.student:id,name')->orderBy('payment_reversals.id')->lazy(500) as $reversal) {
                    yield [$reversal->created_at ? \Carbon\Carbon::parse($reversal->created_at)->format('Y-m-d H:i') : '', 'Reversal', $reversal->payment->receipt_number, $reversal->payment->enrollment->student->name,
                        $reversal->payment->method, '-'.$reversal->payment->amount];
                }
                foreach ((clone $refundQuery)->with('enrollment.student:id,name')->orderBy('refunded_at')->lazy(500) as $refund) {
                    yield [$refund->refunded_at?->format('Y-m-d H:i'), 'Refund', $refund->voucher_number, $refund->enrollment->student->name, $refund->method, '-'.$refund->amount];
                }
            })();

            return ListQuery::download('fee-collections', ['Date', 'Entry', 'Receipt', 'Student', 'Method', 'Amount'], $rows);
        }
        $paid = Money::minor((string) (clone $paymentQuery)->sum('amount'));
        $reversed = Money::minor((string) (clone $reversalQuery)->join('payments', 'payments.id', '=', 'payment_reversals.payment_id')->sum('payments.amount'));
        $refunded = Money::minor((string) (clone $refundQuery)->sum('amount'));
        $netMinor = $paid - $reversed - $refunded;
        $net = ($netMinor < 0 ? '-' : '').Money::decimal(abs($netMinor));
        $kpis = [['label' => 'Payments', 'value' => currency_format(Money::decimal($paid)), 'icon' => 'fa-arrow-down', 'note' => (clone $paymentQuery)->count().' receipts'],
            ['label' => 'Reversals', 'value' => currency_format(Money::decimal($reversed)), 'icon' => 'fa-rotate-left', 'note' => (clone $reversalQuery)->count().' reversed'],
            ['label' => 'Refunds', 'value' => currency_format(Money::decimal($refunded)), 'icon' => 'fa-hand-holding-dollar', 'note' => (clone $refundQuery)->count().' refunded'],
            ['label' => 'Net collected', 'value' => currency_format($net), 'icon' => 'fa-coins', 'note' => $start->format('d M Y').' – '.$end->format('d M Y')]];
        $payments = $paymentQuery->with(['enrollment.student:id,name', 'reversal'])->orderBy('paid_at')->paginate(50)->withQueryString();
        $reversals = $reversalQuery->with('payment.enrollment.student:id,name')->orderBy('payment_reversals.id')->paginate(50, ['*'], 'reversals_page')->withQueryString();
        $refunds = $refundQuery->with('enrollment.student:id,name')->orderBy('refunded_at')->paginate(50, ['*'], 'refunds_page')->withQueryString();
        $filters = array_merge(self::reportFilters(), [['name' => 'method', 'label' => 'Method', 'options' => self::METHODS]]);

        return view('fees.report', compact('payments', 'reversals', 'refunds', 'net', 'start', 'end', 'kpis', 'filters'));
    }

    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank', 'card' => 'Card', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'online' => 'Online'];

    /** Shared date/batch/course filters for the Admin reports. */
    public static function reportFilters(): array
    {
        return [['name' => 'start_date', 'label' => 'From', 'type' => 'date'], ['name' => 'end_date', 'label' => 'To', 'type' => 'date'],
            ['name' => 'batch_id', 'label' => 'Batch', 'options' => Batch::orderBy('name')->pluck('name', 'id')],
            ['name' => 'course_id', 'label' => 'Course', 'options' => \App\Models\Course::orderBy('name')->pluck('name', 'id')]];
    }
}
