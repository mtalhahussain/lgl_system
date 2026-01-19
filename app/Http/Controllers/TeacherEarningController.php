<?php

namespace App\Http\Controllers;

use App\Models\TeacherEarning;
use App\Services\SalaryCalculatorService;
use Illuminate\Http\Request;

class TeacherEarningController extends Controller
{
    private $salaryCalculator;

    public function __construct(SalaryCalculatorService $salaryCalculator)
    {
        $this->salaryCalculator = $salaryCalculator;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', TeacherEarning::class);
        
        $query = TeacherEarning::with(['teacher', 'batch.course']);
        
        // Filter by teacher (for teacher role)
        if (auth()->user()->role === 'teacher') {
            $query->where('teacher_id', auth()->id());
        }
        
        // Filter by teacher ID (for admin/accountant)
        if ($request->has('teacher_id') && auth()->user()->role !== 'teacher') {
            $query->where('teacher_id', $request->teacher_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by year and month
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        
        if ($request->has('month')) {
            $query->where('month', $request->month);
        }
        
        $earnings = $query->orderBy('year', 'desc')
                         ->orderBy('month', 'desc')
                         ->paginate(15);
        
        return response()->json($earnings);
    }

    public function show(TeacherEarning $earning)
    {
        $this->authorize('view', $earning);
        
        $earning->load(['teacher', 'batch.course']);
        
        return response()->json($earning);
    }

    public function calculateEarnings(Request $request)
    {
        $this->authorize('create', TeacherEarning::class);
        
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'batch_id' => 'required|exists:batches,id',
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        try {
            $calculation = $this->salaryCalculator->calculateBatchEarnings(
                $validated['teacher_id'],
                $validated['batch_id'],
                $validated['year'],
                $validated['month']
            );
            
            return response()->json($calculation);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function generateEarning(Request $request)
    {
        $this->authorize('create', TeacherEarning::class);
        
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'batch_id' => 'required|exists:batches,id',
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        try {
            $earning = $this->salaryCalculator->generateEarningsRecord(
                $validated['teacher_id'],
                $validated['batch_id'],
                $validated['year'],
                $validated['month']
            );
            
            return response()->json($earning, 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function processMonthlyPayroll(Request $request)
    {
        $this->authorize('generateReports', TeacherEarning::class);
        
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        $results = $this->salaryCalculator->processMonthlyPayroll(
            $validated['year'],
            $validated['month']
        );
        
        return response()->json([
            'message' => 'Monthly payroll processed successfully',
            'processed_teachers' => count($results),
            'results' => $results
        ]);
    }

    public function payEarning(Request $request, TeacherEarning $earning)
    {
        $this->authorize('processPayment', $earning);
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);
        
        if ($validated['amount'] > $earning->total_earning - $earning->paid_amount) {
            return response()->json([
                'message' => 'Payment amount exceeds remaining balance'
            ], 422);
        }
        
        if ($validated['amount'] == ($earning->total_earning - $earning->paid_amount)) {
            $earning->payFull($validated['notes'] ?? null);
            $message = 'Full payment processed successfully';
        } else {
            $earning->payPartial($validated['amount'], $validated['notes'] ?? null);
            $message = 'Partial payment processed successfully';
        }
        
        return response()->json([
            'message' => $message,
            'earning' => $earning->fresh()
        ]);
    }

    public function monthlyReport(Request $request)
    {
        $this->authorize('generateReports', TeacherEarning::class);
        
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        $report = $this->salaryCalculator->calculateMonthlyEarnings(
            $validated['teacher_id'],
            $validated['year'],
            $validated['month']
        );
        
        return response()->json($report);
    }

    public function earningsSummary(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'start_year' => 'required|integer|min:2020',
            'start_month' => 'required|integer|min:1|max:12',
            'end_year' => 'nullable|integer|min:2020',
            'end_month' => 'nullable|integer|min:1|max:12',
        ]);
        
        // Check authorization
        if (auth()->user()->role === 'teacher' && auth()->id() != $validated['teacher_id']) {
            abort(403, 'Unauthorized');
        } elseif (!in_array(auth()->user()->role, ['admin', 'accountant', 'teacher'])) {
            abort(403, 'Unauthorized');
        }
        
        $summary = $this->salaryCalculator->getEarningsSummary(
            $validated['teacher_id'],
            $validated['start_year'],
            $validated['start_month'],
            $validated['end_year'] ?? $validated['start_year'],
            $validated['end_month'] ?? $validated['start_month']
        );
        
        return response()->json($summary);
    }

    public function pendingPayments()
    {
        $this->authorize('generateReports', TeacherEarning::class);
        
        $pendingEarnings = TeacherEarning::with(['teacher', 'batch.course'])
            ->where('status', 'pending')
            ->orWhere('status', 'partially_paid')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
        
        $summary = [
            'total_pending_amount' => $pendingEarnings->sum(function ($earning) {
                return $earning->total_earning - $earning->paid_amount;
            }),
            'total_teachers' => $pendingEarnings->pluck('teacher_id')->unique()->count(),
            'by_teacher' => [],
        ];
        
        $groupedByTeacher = $pendingEarnings->groupBy('teacher_id');
        
        foreach ($groupedByTeacher as $teacherId => $earnings) {
            $teacher = $earnings->first()->teacher;
            $totalPending = $earnings->sum(function ($earning) {
                return $earning->total_earning - $earning->paid_amount;
            });
            
            $summary['by_teacher'][] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'total_pending' => $totalPending,
                'pending_earnings_count' => $earnings->count(),
                'earnings' => $earnings,
            ];
        }
        
        return response()->json($summary);
    }
}