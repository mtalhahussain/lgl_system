<?php

namespace App\Http\Controllers;

use App\Models\FeeInstallment;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Course;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InstallmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,accountant');
    }

    public function create(Request $request)
    {
        $students = User::where('role', 'student')
            ->with(['enrollments.batch.course'])
            ->get()
            ->filter(function($student) {
                return $student->enrollments->isNotEmpty();
            });

        $courses = Course::with('batches')->get();

        return view('admin.installments.create', compact('students', 'courses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'installment_type' => 'required|in:full,monthly,quarterly,custom',
            'number_of_installments' => 'nullable|integer|min:1|max:12',
            'first_payment' => 'boolean',
            'first_payment_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'payment_method' => 'nullable|in:cash,bank,card,online',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $enrollment = Enrollment::with('batch.course')->findOrFail($request->enrollment_id);
            $totalFee = $enrollment->batch->course->fees;
            
            // Check for existing installments
            $existingInstallments = FeeInstallment::where('enrollment_id', $enrollment->id)->count();
            if ($existingInstallments > 0) {
                return back()->withErrors(['enrollment_id' => 'Installments already created for this enrollment.']);
            }

            $installments = [];

            if ($request->installment_type === 'full') {
                // Full payment in one go
                $installments[] = [
                    'enrollment_id' => $enrollment->id,
                    'amount' => $totalFee,
                    'due_date' => $request->start_date,
                    'status' => $request->first_payment ? 'paid' : 'pending',
                    'paid_date' => $request->first_payment ? now() : null,
                    'payment_method' => $request->first_payment ? $request->payment_method : null,
                    'transaction_reference' => $request->first_payment ? $request->transaction_reference : null,
                    'notes' => $request->first_payment ? $request->notes : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } else {
                // Installment-based payments
                $numberOfInstallments = $request->number_of_installments ?: ($request->installment_type === 'monthly' ? 3 : 2);
                
                if ($request->first_payment && $request->first_payment_amount) {
                    // First payment given
                    $remainingAmount = $totalFee - $request->first_payment_amount;
                    $installmentAmount = $remainingAmount / ($numberOfInstallments - 1);
                    
                    // First installment (already paid)
                    $installments[] = [
                        'enrollment_id' => $enrollment->id,
                        'amount' => $request->first_payment_amount,
                        'due_date' => $request->start_date,
                        'status' => 'paid',
                        'paid_date' => now(),
                        'payment_method' => $request->payment_method,
                        'transaction_reference' => $request->transaction_reference,
                        'notes' => $request->notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Remaining installments
                    for ($i = 1; $i < $numberOfInstallments; $i++) {
                        $dueDate = Carbon::parse($request->start_date);
                        
                        if ($request->installment_type === 'monthly') {
                            $dueDate->addMonths($i);
                        } elseif ($request->installment_type === 'quarterly') {
                            $dueDate->addMonths($i * 3);
                        } else {
                            $dueDate->addDays($i * 30); // Custom: every 30 days
                        }

                        $installments[] = [
                            'enrollment_id' => $enrollment->id,
                            'amount' => $installmentAmount,
                            'due_date' => $dueDate->format('Y-m-d'),
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                } else {
                    // No first payment, divide equally
                    $installmentAmount = $totalFee / $numberOfInstallments;

                    for ($i = 0; $i < $numberOfInstallments; $i++) {
                        $dueDate = Carbon::parse($request->start_date);
                        
                        if ($request->installment_type === 'monthly') {
                            $dueDate->addMonths($i);
                        } elseif ($request->installment_type === 'quarterly') {
                            $dueDate->addMonths($i * 3);
                        } else {
                            $dueDate->addDays($i * 30); // Custom: every 30 days
                        }

                        $installments[] = [
                            'enrollment_id' => $enrollment->id,
                            'amount' => $installmentAmount,
                            'due_date' => $dueDate->format('Y-m-d'),
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            FeeInstallment::insert($installments);

            DB::commit();
            
            $message = 'Installments created successfully! ';
            if ($request->first_payment) {
                $message .= 'First payment of ' . currency_format($request->first_payment_amount ?: $totalFee) . ' recorded.';
            }

            return redirect()->route('fees.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create installments: ' . $e->getMessage()]);
        }
    }

    public function getEnrollmentDetails(Enrollment $enrollment)
    {
        $enrollment->load('batch.course', 'student');
        
        $existingInstallments = FeeInstallment::where('enrollment_id', $enrollment->id)->count();
        
        return response()->json([
            'student_name' => $enrollment->student->name,
            'course_name' => $enrollment->batch->course->name,
            'total_fees' => $enrollment->batch->course->fees,
            'formatted_fees' => currency_format($enrollment->batch->course->fees),
            'has_installments' => $existingInstallments > 0
        ]);
    }
}