<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\FeeInstallment;
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
            'collections' => ['Fee Collections', 'Payments and dated reversals'],
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
        $filterOptions = ['batches' => \App\Models\Batch::select('id', 'name')->orderBy('name')->get(),
            'courses' => \App\Models\Course::select('id', 'name')->orderBy('name')->get()];
        if (in_array($type, ['enrollments', 'student'], true)) {
            $rows = Enrollment::with(['student:id,name', 'batch.course:id,name,level'])
                ->when(! empty($filters['start_date']), fn ($query) => $query->whereDate('enrollment_date', '>=', $filters['start_date']))
                ->when(! empty($filters['end_date']), fn ($query) => $query->whereDate('enrollment_date', '<=', $filters['end_date']))
                ->when(! empty($filters['batch_id']), fn ($query) => $query->where('batch_id', $filters['batch_id']))
                ->when(! empty($filters['course_id']), fn ($query) => $query->whereHas('batch', fn ($batch) => $batch->where('course_id', $filters['course_id'])))
                ->orderByDesc('id')->paginate(30)->withQueryString();

            return view('admin.reports.enrollments', compact('rows') + $filterOptions);
        }
        abort_unless($type === 'dues', 404);
        $rows = FeeInstallment::outstanding()->with(['enrollment.student:id,name', 'enrollment.batch:id,name', 'allocations.payment.reversal'])
            ->when(! empty($filters['start_date']), fn ($query) => $query->whereDate('due_date', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn ($query) => $query->whereDate('due_date', '<=', $filters['end_date']))
            ->when(! empty($filters['batch_id']), fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('batch_id', $filters['batch_id'])))
            ->when(! empty($filters['course_id']), fn ($query) => $query->whereHas('enrollment.batch', fn ($batch) => $batch->where('course_id', $filters['course_id'])))
            ->orderBy('due_date')->paginate(30)->withQueryString();

        return view('admin.reports.dues', compact('rows') + $filterOptions);
    }
}
