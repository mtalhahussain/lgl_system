<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\TeacherDue;
use App\Models\TeacherPayout;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PayrollService;
use App\Support\ListQuery;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    // Posted (non-reversed) payout total for a due, usable inside teacher_dues queries.
    private const PAID_SQL = '(SELECT COALESCE(SUM(p.amount), 0) FROM teacher_payouts p WHERE p.teacher_due_id = teacher_dues.id
        AND NOT EXISTS (SELECT 1 FROM teacher_payout_reversals r WHERE r.teacher_payout_id = p.id))';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin')->except(['index', 'show', 'slip']);
        $this->middleware('role:admin,teacher')->only(['index', 'show', 'slip']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $teachers = $isAdmin ? User::whereIn('role', User::PAID_ROLES)->orderBy('name')->pluck('name', 'id') : collect();
        $kinds = ['salary' => 'Salary', 'batch' => 'Per student (batch)'];
        $states = ['outstanding' => 'Outstanding', 'paid' => 'Fully paid', 'void' => 'Voided'];
        $year = (int) $request->integer('year');
        $month = (int) $request->integer('month');
        $hasPeriod = $year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12;
        $period = $hasPeriod ? sprintf('%04d-%02d', $year, $month) : null;
        $list = ListQuery::for(TeacherDue::with(['teacher:id,name', 'batch:id,name', 'payouts.reversal'])
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->when($period, fn ($query) => $query->where('period', $period)), $request)
            ->filter('teacher_id', fn ($query, $id) => $query->where('teacher_id', $id), $teachers->keys()->all())
            ->filter('kind', fn ($query, $kind) => $query->where('kind', $kind), array_keys($kinds))
            ->filter('state', fn ($query, $state) => match ($state) {
                'void' => $query->whereNotNull('voided_at'),
                'paid' => $query->whereNull('voided_at')->whereRaw('teacher_dues.amount <= '.self::PAID_SQL),
                'outstanding' => $query->whereNull('voided_at')->whereRaw('teacher_dues.amount > '.self::PAID_SQL),
            }, array_keys($states))
            ->sorts(['created' => 'created_at', 'amount' => 'amount',
                'teacher' => fn ($query, $direction) => $query->orderBy(User::select('name')->whereColumn('users.id', 'teacher_dues.teacher_id'), $direction)], '-created');
        if ($isAdmin) {
            $list->search(['teacher.name', 'teacher.email']);
        }
        if ($list->wantsCsv()) {
            return $list->csv('payroll', ['Person' => fn ($due) => $due->teacher?->name, 'For' => fn ($due) => $due->label(),
                'Students' => fn ($due) => $due->student_count, 'Amount' => fn ($due) => $due->amount,
                'Paid' => fn ($due) => Money::decimal($due->paidMinor()), 'Outstanding' => fn ($due) => Money::decimal($due->outstandingMinor()),
                'Status' => fn ($due) => $due->state(), 'Created' => fn ($due) => $due->created_at?->toDateString()]);
        }
        $open = $list->totalsQuery()->whereNull('voided_at')->get();
        $paidStart = $hasPeriod ? Carbon::create($year, $month, 1)->startOfMonth() : now()->startOfMonth();
        $paidEnd = $hasPeriod ? $paidStart->copy()->endOfMonth() : null;
        $paid = TeacherPayout::posted()->where('paid_at', '>=', $paidStart)
            ->when($paidEnd, fn ($query) => $query->where('paid_at', '<=', $paidEnd))
            ->when(! $isAdmin, fn ($query) => $query->whereHas('due', fn ($due) => $due->where('teacher_id', $user->id)))
            ->pluck('amount')->sum(fn ($amount) => Money::minor((string) $amount));
        $kpis = [['label' => 'Dues', 'value' => $list->counts()['total'], 'icon' => 'fa-file-invoice-dollar'],
            ['label' => 'Outstanding', 'value' => currency_format(Money::decimal($open->sum(fn ($due) => $due->outstandingMinor()))), 'icon' => 'fa-wallet'],
            ['label' => $hasPeriod ? 'Paid in '.$paidStart->format('M Y') : 'Paid this month',
                'value' => currency_format(Money::decimal($paid)), 'icon' => 'fa-coins', 'note' => 'Excluding reversed payouts']];
        $filters = array_values(array_filter([
            $isAdmin ? ['name' => 'teacher_id', 'label' => 'Person', 'options' => $teachers] : null,
            ['name' => 'year', 'label' => 'Year', 'options' => self::payrollYears()],
            ['name' => 'month', 'label' => 'Month', 'options' => FeeController::MONTHS],
            ['name' => 'kind', 'label' => 'Type', 'options' => $kinds],
            ['name' => 'state', 'label' => 'Status', 'options' => $states],
        ]));
        $eligibleBatches = $isAdmin ? Batch::with(['teacher:id,name', 'course:id,teacher_per_student_amount'])
            ->whereIn('status', ['ongoing', 'completed'])
            ->whereHas('teacher', fn ($query) => $query->where('salary_type', 'per_student'))
            // Batches with a pre-monthly whole-batch earning (no period) stay excluded until it is voided.
            ->whereNotIn('id', TeacherDue::whereNotNull('active_key')->where('kind', 'batch')->whereNull('period')->select('batch_id'))
            ->orderBy('name')->get(['id', 'name', 'teacher_id', 'course_id']) : collect();

        return view('payroll.index', ['dues' => $list->paginate(), 'kpis' => $kpis, 'filters' => $filters,
            'isAdmin' => $isAdmin, 'eligibleBatches' => $eligibleBatches]);
    }

    /** Year options for the payroll filter, drawn from the recorded due periods, newest first. */
    private static function payrollYears(): array
    {
        $years = TeacherDue::whereNotNull('period')->distinct()->pluck('period')
            ->map(fn ($period) => (int) substr($period, 0, 4))
            ->push((int) today()->year)->unique()->sortDesc()->values()->all();

        return array_combine($years, $years);
    }

    public function show(Request $request, TeacherDue $due)
    {
        $this->authorizeDue($request->user(), $due);
        $due->load(['teacher:id,name,employee_id', 'batch:id,name', 'payouts.reversal']);

        return view('payroll.show', ['due' => $due, 'methods' => PayrollService::METHODS]);
    }

    public function storeSalaries(Request $request, PayrollService $service)
    {
        $data = $request->validate(['period' => 'required|date_format:Y-m']);
        $result = $service->generateMonth($data['period'], $request->user());

        return redirect()->route('payroll.index')->with('success', "Salaries: {$result['salaries']['created']} created, {$result['salaries']['skipped']} already existed. "
            ."Per-student batch earnings: {$result['batches']['created']} created, {$result['batches']['skipped']} skipped (already generated, no students or no rate).");
    }

    public function storeBatch(Request $request, PayrollService $service)
    {
        $data = $request->validate(['batch_id' => 'required|integer|exists:batches,id', 'period' => 'required|date_format:Y-m']);
        $due = $service->generateBatchDue((int) $data['batch_id'], $data['period'], $request->user());

        return redirect()->route('payroll.show', $due)->with('success', 'Teacher earning generated for the batch month.');
    }

    public function void(Request $request, TeacherDue $due, PayrollService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $service->void($due, $request->user(), $data['reason']);

        return redirect()->route('payroll.show', $due)->with('success', 'Due voided. It can now be generated again.');
    }

    public function pay(Request $request, TeacherDue $due, PayrollService $service)
    {
        $data = $request->validate(['amount' => 'required|decimal:0,2|gt:0|max:99999999.99',
            'method' => 'required|in:'.implode(',', array_keys(PayrollService::METHODS)), 'reference' => 'nullable|string|max:255',
            'submission_key' => 'required|string|min:16|max:64']);
        $payout = $service->pay($due->id, (string) $data['amount'], $data['method'], $data['submission_key'], $request->user(), $data['reference'] ?? null);

        if ($due->teacher_id) {
            app(NotificationService::class)->toUser($due->teacher_id, [
                'type' => 'payout', 'icon' => 'fa-hand-holding-dollar', 'level' => 'success',
                'title' => 'Salary paid',
                'body' => 'A payout of '.currency_format($data['amount']).' has been recorded to you.',
                'url' => route('payroll.slip', $payout),
            ]);
        }

        return redirect()->route('payroll.slip', $payout)->with('success', 'Payout recorded.');
    }

    public function slip(Request $request, TeacherPayout $payout)
    {
        $payout->load(['due', 'reversal']);
        $this->authorizeDue($request->user(), $payout->due);

        return view('payroll.slip', compact('payout'));
    }

    public function reverse(Request $request, TeacherPayout $payout, PayrollService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $service->reverse($payout, $request->user(), $data['reason']);

        return redirect()->route('payroll.slip', $payout)->with('success', 'Payout reversed. Original slip retained.');
    }

    private function authorizeDue(User $user, TeacherDue $due): void
    {
        abort_unless($user->isAdmin() || ($user->isTeacher() && (int) $due->teacher_id === (int) $user->id), 403);
    }
}
