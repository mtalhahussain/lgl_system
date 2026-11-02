<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Services\OverviewService;
use App\Support\ListQuery;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

    public function index()
    {
        $reportTypes = [];
        foreach (['enrollments' => ['Enrollments', 'Student, course and batch roster'],
            'collections' => ['Fee Collections', 'Payments with dated reversals and refunds'],
            'dues' => ['Outstanding Dues', 'Remaining scheduled balances'],
            'attendance' => ['Attendance', 'Manual class attendance records']] as $key => [$title, $description]) {
            $reportTypes[$key] = ['title' => $title, 'description' => $description, 'icon' => 'fas fa-list', 'color' => 'primary'];
        }

        return view('admin.reports.index', compact('reportTypes'));
    }

    public function generate(Request $request, string $type)
    {
        $filters = $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'batch_id' => 'nullable|integer', 'course_id' => 'nullable|integer']);
        if (in_array($type, ['collections', 'financial'], true)) {
            return redirect()->route('fees.reports', $filters);
        }
        if ($type === 'attendance') {
            return redirect()->route('attendance.reports', $filters);
        }
        $reportFilters = FeeController::reportFilters();
        if (in_array($type, ['enrollments', 'student'], true)) {
            $list = ListQuery::for(Enrollment::with(['student:id,name', 'batch:id,name,course_id', 'batch.course:id,name,level'])
                ->when(! empty($filters['start_date']), fn ($query) => $query->whereDate('enrollment_date', '>=', $filters['start_date']))
                ->when(! empty($filters['end_date']), fn ($query) => $query->whereDate('enrollment_date', '<=', $filters['end_date']))
                ->when(! empty($filters['batch_id']), fn ($query) => $query->where('batch_id', $filters['batch_id']))
                ->when(! empty($filters['course_id']), fn ($query) => $query->whereHas('batch', fn ($batch) => $batch->where('course_id', $filters['course_id']))), $request)
                ->search(['student.name'])
                ->filter('status', fn ($query, $status) => $query->where('status', $status), ['active', 'completed', 'dropped', 'transferred'])
                ->sorts(['date' => 'enrollment_date', 'student' => fn ($query, $direction) => $query->orderBy(\App\Models\User::select('name')->whereColumn('users.id', 'enrollments.student_id'), $direction)], '-date');
            if ($list->wantsCsv()) {
                return $list->csv('enrollment-report', ['Student' => fn ($row) => $row->student?->name, 'Course' => fn ($row) => $row->batch?->course?->name,
                    'Batch' => fn ($row) => $row->batch?->name, 'Date' => fn ($row) => $row->enrollment_date?->toDateString(), 'Status' => fn ($row) => $row->status]);
            }
            $counts = $list->counts(['active' => "enrollments.status = 'active'", 'completed' => "enrollments.status = 'completed'", 'dropped' => "enrollments.status = 'dropped'"]);
            $kpis = [['label' => 'Enrollments', 'value' => $counts['total'], 'icon' => 'fa-list'], ['label' => 'Active', 'value' => $counts['active'], 'icon' => 'fa-user-graduate'],
                ['label' => 'Completed', 'value' => $counts['completed'], 'icon' => 'fa-check'], ['label' => 'Withdrawn', 'value' => $counts['dropped'], 'icon' => 'fa-user-minus']];
            $reportFilters[] = ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Withdrawn', 'transferred' => 'Transferred']];

            return view('admin.reports.enrollments', ['rows' => $list->paginate(25), 'kpis' => $kpis, 'filters' => $reportFilters]);
        }
        abort_unless($type === 'dues', 404);
        $list = ListQuery::for(FeeInstallment::outstanding()->with(['enrollment.student:id,name', 'enrollment.batch:id,name', 'allocations.payment.reversal'])
            ->when(! empty($filters['start_date']), fn ($query) => $query->whereDate('due_date', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn ($query) => $query->whereDate('due_date', '<=', $filters['end_date']))
            ->when(! empty($filters['batch_id']), fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('batch_id', $filters['batch_id'])))
            ->when(! empty($filters['course_id']), fn ($query) => $query->whereHas('enrollment.batch', fn ($batch) => $batch->where('course_id', $filters['course_id']))), $request)
            ->search(['enrollment.student.name'])
            ->filter('overdue', fn ($query) => $query->whereDate('due_date', '<', today()), ['1'])
            ->sorts(['due' => 'due_date', 'amount' => 'amount'], 'due');
        if ($list->wantsCsv()) {
            return $list->csv('dues-report', ['Student' => fn ($row) => $row->enrollment?->student?->name, 'Batch' => fn ($row) => $row->enrollment?->batch?->name,
                'Due date' => fn ($row) => $row->due_date?->toDateString(), 'Scheduled' => fn ($row) => $row->amount, 'Outstanding' => fn ($row) => $row->outstanding_amount]);
        }
        $debt = app(OverviewService::class)->installmentTotals($list->totalsQuery());
        $kpis = [['label' => 'Open installments', 'value' => $list->counts()['total'], 'icon' => 'fa-list'],
            ['label' => 'Enrollments', 'value' => $list->totalsQuery()->distinct()->count('enrollment_id'), 'icon' => 'fa-users', 'note' => 'With a matching balance'],
            ['label' => 'Outstanding', 'value' => currency_format($debt['outstanding']), 'icon' => 'fa-wallet'],
            ['label' => 'Overdue', 'value' => currency_format($debt['overdue']), 'icon' => 'fa-triangle-exclamation']];
        $reportFilters[] = ['name' => 'overdue', 'label' => 'Due', 'options' => ['1' => 'Overdue only']];

        return view('admin.reports.dues', ['rows' => $list->paginate(25), 'kpis' => $kpis, 'filters' => $reportFilters]);
    }
}
