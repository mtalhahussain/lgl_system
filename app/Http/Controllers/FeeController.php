<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Services\PaymentService;
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
        return $this->ledger();
    }

    public function studentFees(User $student)
    {
        abort_unless($student->isStudent(), 404);
        $this->authorize('viewFinancials', $student);

        return $this->ledger($student);
    }

    public function batchFees(Batch $batch)
    {
        return $this->ledger(null, $batch);
    }

    private function ledger(?User $student = null, ?Batch $batch = null)
    {
        abort_unless(Schema::hasTable('payments'), 503, 'Fee collection is temporarily unavailable. Contact the administrator.');
        $enrollments = Enrollment::with(['student:id,name', 'batch:id,name', 'feeInstallments.allocations.payment.reversal'])
            ->when($student, fn ($query) => $query->where('student_id', $student->id))
            ->when($batch, fn ($query) => $query->where('batch_id', $batch->id))
            ->orderByDesc('id')->paginate(15);
        $payments = Payment::with(['enrollment.student:id,name', 'reversal'])
            ->when($student, fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $student->id)))
            ->when($batch, fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('batch_id', $batch->id)))
            ->orderByDesc('id')->paginate(20, ['*'], 'payments_page');

        return view('fees.ledger', compact('enrollments', 'payments', 'student', 'batch'));
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
            'batch_id' => 'nullable|integer', 'course_id' => 'nullable|integer']);
        $start = \Carbon\Carbon::parse($data['start_date'] ?? today()->startOfMonth())->startOfDay();
        $end = \Carbon\Carbon::parse($data['end_date'] ?? today())->endOfDay();
        $scope = fn ($query) => $query
            ->when(! empty($data['batch_id']), fn ($query) => $query->where('batch_id', $data['batch_id']))
            ->when(! empty($data['course_id']), fn ($query) => $query->whereHas('batch', fn ($batch) => $batch->where('course_id', $data['course_id'])));
        $paymentQuery = Payment::whereBetween('paid_at', [$start, $end])->whereHas('enrollment', $scope);
        $reversalQuery = PaymentReversal::whereBetween('payment_reversals.created_at', [$start, $end])->whereHas('payment.enrollment', $scope);
        $paid = Money::minor((string) (clone $paymentQuery)->sum('amount'));
        $reversed = Money::minor((string) (clone $reversalQuery)->join('payments', 'payments.id', '=', 'payment_reversals.payment_id')->sum('payments.amount'));
        $net = ($paid < $reversed ? '-' : '').Money::decimal(abs($paid - $reversed));
        $payments = $paymentQuery->with(['enrollment.student:id,name', 'reversal'])->orderBy('paid_at')->paginate(50)->withQueryString();
        $reversals = $reversalQuery->with('payment.enrollment.student:id,name')->orderBy('payment_reversals.id')->paginate(50, ['*'], 'reversals_page')->withQueryString();
        $batches = Batch::select('id', 'name')->orderBy('name')->get();
        $courses = \App\Models\Course::select('id', 'name')->orderBy('name')->get();

        return view('fees.report', compact('payments', 'reversals', 'net', 'start', 'end', 'batches', 'courses'));
    }
}
