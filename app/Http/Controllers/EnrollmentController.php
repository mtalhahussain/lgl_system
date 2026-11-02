<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Services\EnrollmentService;
use App\Services\FeeCalculatorService;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    private $enrollmentService;
    private $feeCalculator;

    public function __construct(EnrollmentService $enrollmentService, FeeCalculatorService $feeCalculator)
    {
        $this->enrollmentService = $enrollmentService;
        $this->feeCalculator = $feeCalculator;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class);
        
        // Route middleware limits this page to Admin.
        $statuses = ['active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Withdrawn', 'transferred' => 'Transferred'];
        $courses = \App\Models\Course::orderBy('name')->pluck('name', 'id');
        $batches = \App\Models\Batch::orderBy('name')->pluck('name', 'id');
        $list = ListQuery::for(Enrollment::with(['student:id,name,email', 'batch:id,name,course_id', 'batch.course:id,name']), $request)
            ->search(['student.name', 'student.email'])
            ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
            ->filter('course_id', fn ($query, $id) => $query->whereHas('batch', fn ($batch) => $batch->where('course_id', $id)), $courses->keys()->all())
            ->filter('batch_id', fn ($query, $id) => $query->where('batch_id', $id), $batches->keys()->all())
            ->dateRange('enrollment_date')
            ->sorts(['admitted' => 'enrollment_date',
                'student' => fn ($query, $direction) => $query->orderBy(\App\Models\User::select('name')->whereColumn('users.id', 'enrollments.student_id'), $direction)], '-admitted');
        if ($list->wantsCsv()) {
            return $list->csv('enrollments', ['Student' => fn ($row) => $row->student?->name, 'Email' => fn ($row) => $row->student?->email,
                'Course' => fn ($row) => $row->batch?->course?->name, 'Batch' => fn ($row) => $row->batch?->name,
                'Admitted' => fn ($row) => $row->enrollment_date?->toDateString(), 'Status' => fn ($row) => $statuses[$row->status] ?? $row->status,
                'Discount %' => fn ($row) => $row->discount_percentage, 'Discount' => fn ($row) => $row->discount_amount,
                'Net fee' => fn ($row) => Money::decimal(max(0, Money::minor($row->total_fee) - Money::minor($row->discount_amount))),
                'Outstanding' => fn ($row) => $row->remaining_fee]);
        }
        $counts = $list->counts(['active' => "enrollments.status = 'active'", 'completed' => "enrollments.status = 'completed'",
            'dropped' => "enrollments.status = 'dropped'", 'transferred' => "enrollments.status = 'transferred'"]);
        $kpis = [['label' => 'Active', 'value' => $counts['active'], 'icon' => 'fa-user-graduate'],
            ['label' => 'Completed', 'value' => $counts['completed'], 'icon' => 'fa-check'],
            ['label' => 'Withdrawn', 'value' => $counts['dropped'], 'icon' => 'fa-user-minus'],
            ['label' => 'Transferred', 'value' => $counts['transferred'], 'icon' => 'fa-right-left']];
        $filters = [['name' => 'status', 'label' => 'Status', 'options' => $statuses], ['name' => 'course_id', 'label' => 'Course', 'options' => $courses],
            ['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches],
            ['name' => 'from', 'label' => 'Admitted from', 'type' => 'date'], ['name' => 'to', 'label' => 'Admitted to', 'type' => 'date']];

        return view('admin.enrollments.index', ['enrollments' => $list->paginate(), 'kpis' => $kpis, 'filters' => $filters, 'statuses' => $statuses]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Enrollment::class);
        
        // Get available students (users with student role who are not already enrolled in the batch)
        $students = \App\Models\User::where('role', 'student')
            ->when($request->batch_id, function ($query) use ($request) {
                // Exclude students already enrolled in this batch
                $query->whereDoesntHave('enrollments', function ($q) use ($request) {
                    $q->where('batch_id', $request->batch_id)
                      ->where('status', 'active');
                });
            })
            ->get();

        // Get available batches
        $batches = \App\Models\Batch::with('course')
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->get();

        // Pre-select batch if batch_id is provided
        $selectedBatch = null;
        if ($request->batch_id) {
            $selectedBatch = \App\Models\Batch::with('course')->find($request->batch_id);
        }

        return view('admin.enrollments.create', compact('students', 'batches', 'selectedBatch'));
    }

    public function show(Enrollment $enrollment)
    {
        $this->authorize('view', $enrollment);
        
        $enrollment->load([
            'student',
            'batch.course',
            'feeInstallments',
            'transferredToBatch',
            'payments.reversal',
            'refunds',
        ]);
        
        $enrollment->remaining_fee = $enrollment->remaining_fee;
        $enrollment->payment_progress = $enrollment->payment_progress_percentage;
        
        $transferBatches = \App\Models\Batch::where('course_id', $enrollment->batch->course_id)
            ->where('id', '!=', $enrollment->batch_id)->whereIn('status', ['upcoming', 'ongoing'])->get(['id', 'name']);
        return view('admin.enrollments.show', compact('enrollment', 'transferBatches'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Enrollment::class);
        if (is_array($request->input('schedule'))) {
            $request->merge(['schedule' => array_values(array_filter($request->input('schedule'), fn ($row) =>
                !is_array($row) || (($row['amount'] ?? '') !== '' && ($row['amount'] ?? null) !== null)
                || (($row['due_date'] ?? '') !== '' && ($row['due_date'] ?? null) !== null)))]);
        }
        
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'batch_id' => 'required|exists:batches,id',
            'discount_percentage' => 'nullable|decimal:0,2|min:0|max:100',
            'installments' => 'integer|min:1|max:12',
            'schedule' => 'nullable|array|min:1|max:12',
            'schedule.*.amount' => 'required|decimal:0,2|min:0|max:99999999.99',
            'schedule.*.due_date' => 'required|date_format:Y-m-d',
        ]);
        
        try {
            $enrollment = $this->enrollmentService->enrollStudent(
                $validated['student_id'],
                $validated['batch_id'],
                $validated['discount_percentage'] ?? 0,
                $validated['installments'] ?? 1,
                $validated['schedule'] ?? null
            );
            
            return redirect()->route('enrollments.index')
                ->with('success', 'Student enrolled successfully!');
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function transfer(Request $request, Enrollment $enrollment)
    {
        $this->authorize('transfer', $enrollment);
        
        $validated = $request->validate([
            'new_batch_id' => 'required|exists:batches,id',
            'transfer_date' => 'nullable|date',
        ]);
        
        try {
            $newEnrollment = $this->enrollmentService->transferStudent(
                $enrollment->id,
                $validated['new_batch_id'],
                $validated['transfer_date'] ?? null
            );
            if (!$request->expectsJson()) {
                return redirect()->route('enrollments.show', $newEnrollment)->with('success', 'Student transferred. Fee schedule preserved.');
            }
            
            return response()->json([
                'message' => 'Student transferred successfully',
                'new_enrollment' => $newEnrollment
            ]);
            
        } catch (\Exception $e) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['transfer' => $e->getMessage()]);
            }
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function drop(Request $request, Enrollment $enrollment)
    {
        $this->authorize('drop', $enrollment);
        
        $validated = $request->validate([
            'dropout_date' => 'nullable|date',
            'dropout_reason' => 'required|string|max:500',
            'refund_amount' => 'numeric|min:0',
        ]);
        
        try {
            $this->enrollmentService->dropStudent(
                $enrollment->id,
                $validated['dropout_date'] ?? null,
                $validated['dropout_reason'],
                $validated['refund_amount'] ?? 0
            );
            if (!$request->expectsJson()) {
                return redirect()->route('enrollments.show', $enrollment)->with('success', 'Enrollment withdrawn. Existing debt and payment history preserved.');
            }
            
            return response()->json([
                'message' => 'Student dropped successfully'
            ]);
            
        } catch (\Exception $e) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['withdrawal' => $e->getMessage()]);
            }
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function complete(Enrollment $enrollment)
    {
        $this->authorize('complete', $enrollment);
        
        try {
            $this->enrollmentService->completeEnrollment($enrollment->id);
            
            return response()->json([
                'message' => 'Enrollment completed successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function paymentSummary(Enrollment $enrollment)
    {
        $this->authorize('viewFinancials', $enrollment);
        
        $summary = $this->feeCalculator->getPaymentSummary($enrollment->id);
        
        return response()->json($summary);
    }

    public function processPayment(Request $request, Enrollment $enrollment)
    {
        $this->authorize('managePayments', $enrollment);
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank,card',
            'transaction_reference' => 'nullable|string|max:255',
        ]);
        
        try {
            $result = $this->feeCalculator->processPayment(
                $enrollment->id,
                $validated['amount'],
                $validated['payment_method'],
                $validated['transaction_reference'] ?? null
            );
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function updateDiscount(Request $request, Enrollment $enrollment, \App\Services\PaymentService $service)
    {
        $data = $request->validate(['discount_percentage' => 'required|decimal:0,2|min:0|max:100', 'reason' => 'required|string|min:5|max:1000',
            'refund_method' => 'nullable|in:'.implode(',', array_keys(\App\Services\PaymentService::REFUND_METHODS)),
            'refund_reference' => 'nullable|string|max:255', 'submission_key' => 'nullable|string|min:16|max:64']);
        $refund = filled($data['refund_method'] ?? null) ? ['method' => $data['refund_method'], 'reference' => $data['refund_reference'] ?? null,
            'submission_key' => $data['submission_key'] ?? null] : null;
        $refundsBefore = $enrollment->refunds()->count();
        $service->changeDiscount($enrollment, (string) $data['discount_percentage'], $request->user(), $data['reason'], $refund);
        $newRefund = $enrollment->refunds()->count() > $refundsBefore ? $enrollment->refunds()->latest('id')->first() : null;

        return $newRefund
            ? redirect()->route('refunds.show', $newRefund)->with('success', 'Discount updated and refund recorded.')
            : redirect()->route('enrollments.show', $enrollment)->with('success', 'Discount updated; unpaid installments adjusted.');
    }

    public function applyDiscount(Request $request, Enrollment $enrollment)
    {
        $this->authorize('applyDiscount', $enrollment);
        
        $validated = $request->validate([
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|max:255',
        ]);
        
        $originalFee = $enrollment->total_fee;
        $additionalDiscount = ($validated['discount_percentage'] / 100) * $originalFee;
        $newDiscountAmount = $enrollment->discount_amount + $additionalDiscount;
        
        // Ensure total discount doesn't exceed total fee
        if ($newDiscountAmount > $originalFee) {
            return response()->json([
                'message' => 'Discount cannot exceed total fee'
            ], 422);
        }
        
        $enrollment->update([
            'discount_amount' => $newDiscountAmount,
            'notes' => $enrollment->notes . "\nDiscount applied: {$validated['discount_percentage']}% - {$validated['reason']}"
        ]);
        
        return response()->json([
            'message' => 'Discount applied successfully',
            'enrollment' => $enrollment->fresh()
        ]);
    }
}
