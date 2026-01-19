<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Services\EnrollmentService;
use App\Services\FeeCalculatorService;
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
        
        $query = Enrollment::with(['student', 'batch.course']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by batch
        if ($request->has('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }
        
        // Filter by student (for student role)
        if (auth()->user()->role === 'student') {
            $query->where('student_id', auth()->id());
        }
        
        // Filter by teacher's batches (for teacher role)
        if (auth()->user()->role === 'teacher') {
            $query->whereHas('batch', function ($q) {
                $q->where('teacher_id', auth()->id());
            });
        }
        
        $enrollments = $query->paginate(15);
        
        // Add payment progress to each enrollment
        $enrollments->getCollection()->transform(function ($enrollment) {
            $enrollment->remaining_fee = $enrollment->remaining_fee;
            $enrollment->payment_progress = $enrollment->payment_progress_percentage;
            return $enrollment;
        });
        
        return response()->json($enrollments);
    }

    public function show(Enrollment $enrollment)
    {
        $this->authorize('view', $enrollment);
        
        $enrollment->load([
            'student',
            'batch.course',
            'feeInstallments',
            'transferredToBatch'
        ]);
        
        $enrollment->remaining_fee = $enrollment->remaining_fee;
        $enrollment->payment_progress = $enrollment->payment_progress_percentage;
        
        return response()->json($enrollment);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Enrollment::class);
        
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'batch_id' => 'required|exists:batches,id',
            'discount_percentage' => 'numeric|min:0|max:100',
            'installments' => 'integer|min:1|max:12',
        ]);
        
        try {
            $enrollment = $this->enrollmentService->enrollStudent(
                $validated['student_id'],
                $validated['batch_id'],
                $validated['discount_percentage'] ?? 0,
                $validated['installments'] ?? 1
            );
            
            return response()->json($enrollment, 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
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
            
            return response()->json([
                'message' => 'Student transferred successfully',
                'new_enrollment' => $newEnrollment
            ]);
            
        } catch (\Exception $e) {
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
            
            return response()->json([
                'message' => 'Student dropped successfully'
            ]);
            
        } catch (\Exception $e) {
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