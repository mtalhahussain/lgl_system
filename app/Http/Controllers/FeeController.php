<?php

namespace App\Http\Controllers;

use App\Models\FeeInstallment;
use App\Models\User;
use App\Models\Batch;
use App\Models\Course;
use App\Services\FeeCalculatorService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    protected $feeCalculatorService;

    public function __construct(FeeCalculatorService $feeCalculatorService)
    {
        $this->middleware('auth');
        $this->middleware('role:admin,accountant');
        $this->feeCalculatorService = $feeCalculatorService;
    }

    public function index(Request $request)
    {
        $query = FeeInstallment::with(['enrollment.student', 'enrollment.batch.course']);
        
        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('student_search')) {
            $query->whereHas('enrollment.student', function($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->student_search}%")
                  ->orWhere('email', 'LIKE', "%{$request->student_search}%")
                  ->orWhere('student_id', 'LIKE', "%{$request->student_search}%");
            });
        }
        
        if ($request->filled('course_id')) {
            $query->whereHas('enrollment.batch.course', function($q) use ($request) {
                $q->where('id', $request->course_id);
            });
        }
        
        if ($request->filled('overdue')) {
            $query->where('status', 'pending')
                  ->where('due_date', '<', now());
        }

        $installments = $query->orderBy('due_date', 'asc')->paginate(20);
        $courses = Course::all();
        
        // Calculate statistics
        $stats = [
            'total_pending' => FeeInstallment::where('status', 'pending')->sum('amount'),
            'total_paid' => FeeInstallment::where('status', 'paid')->sum('amount'),
            'overdue_count' => FeeInstallment::where('status', 'pending')
                ->where('due_date', '<', now())->count(),
            'overdue_amount' => FeeInstallment::where('status', 'pending')
                ->where('due_date', '<', now())->sum('amount'),
            'monthly_collection' => FeeInstallment::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount')
        ];

        return view('admin.fees.index', compact('installments', 'courses', 'stats'));
    }

    public function pay(Request $request, FeeInstallment $installment)
    {
        $request->validate([
            'amount_paid' => 'required|numeric|min:0|max:' . $installment->amount,
            'payment_method' => 'required|in:cash,bank_transfer,card,online',
            'notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $installment->update([
                'status' => 'paid',
                'amount_paid' => $request->amount_paid,
                'paid_at' => now(),
                'payment_method' => $request->payment_method,
                'notes' => $request->notes
            ]);

            // Create partial installment if not fully paid
            if ($request->amount_paid < $installment->amount) {
                $remainingAmount = $installment->amount - $request->amount_paid;
                FeeInstallment::create([
                    'enrollment_id' => $installment->enrollment_id,
                    'installment_number' => $installment->installment_number + 0.1,
                    'amount' => $remainingAmount,
                    'due_date' => now()->addDays(7), // 1 week grace period
                    'status' => 'pending'
                ]);
            }

            DB::commit();
            
            return back()->with('success', 'Payment recorded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to record payment: ' . $e->getMessage()]);
        }
    }

    public function reports(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        
        // Fee collection report
        $dailyCollection = FeeInstallment::where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(paid_at) as date, SUM(amount_paid) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Course-wise collection
        $courseCollection = FeeInstallment::where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->join('enrollments', 'fee_installments.enrollment_id', '=', 'enrollments.id')
            ->join('batches', 'enrollments.batch_id', '=', 'batches.id')
            ->join('courses', 'batches.course_id', '=', 'courses.id')
            ->selectRaw('courses.name as course_name, courses.level, SUM(fee_installments.amount_paid) as total')
            ->groupBy('courses.id', 'courses.name', 'courses.level')
            ->get();

        // Outstanding dues
        $outstandingDues = FeeInstallment::where('status', 'pending')
            ->with(['enrollment.student', 'enrollment.batch.course'])
            ->orderBy('due_date')
            ->get();

        // Monthly trends
        $monthlyTrends = FeeInstallment::where('status', 'paid')
            ->whereYear('paid_at', $request->get('year', now()->year))
            ->selectRaw('MONTH(paid_at) as month, SUM(amount_paid) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('admin.fees.reports', compact(
            'dailyCollection', 'courseCollection', 'outstandingDues', 'monthlyTrends', 'startDate', 'endDate'
        ));
    }

    public function studentFees(User $student)
    {
        $student->load(['enrollments.feeInstallments', 'enrollments.batch.course']);
        
        $feeStats = [
            'total_fees' => $student->feeInstallments()->sum('amount'),
            'paid_fees' => $student->feeInstallments()->where('status', 'paid')->sum('amount_paid'),
            'pending_fees' => $student->feeInstallments()->where('status', 'pending')->sum('amount'),
            'overdue_fees' => $student->feeInstallments()
                ->where('status', 'pending')
                ->where('due_date', '<', now())
                ->sum('amount')
        ];

        return view('admin.fees.student', compact('student', 'feeStats'));
    }

    public function batchFees(Batch $batch)
    {
        $batch->load(['enrollments.feeInstallments', 'enrollments.student', 'course']);
        
        $feeStats = [
            'total_expected' => $batch->enrollments->sum(function($enrollment) {
                return $enrollment->feeInstallments->sum('amount');
            }),
            'total_collected' => $batch->enrollments->sum(function($enrollment) {
                return $enrollment->feeInstallments->where('status', 'paid')->sum('amount_paid');
            }),
            'total_pending' => $batch->enrollments->sum(function($enrollment) {
                return $enrollment->feeInstallments->where('status', 'pending')->sum('amount');
            }),
            'collection_rate' => 0
        ];

        if ($feeStats['total_expected'] > 0) {
            $feeStats['collection_rate'] = round(($feeStats['total_collected'] / $feeStats['total_expected']) * 100, 2);
        }

        return view('admin.fees.batch', compact('batch', 'feeStats'));
    }
}