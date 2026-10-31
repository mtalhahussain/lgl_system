<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Services\FeeCalculatorService;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

    public function create()
    {
        $enrollments = Enrollment::with(['student', 'batch'])->whereDoesntHave('feeInstallments')
            ->where('status', 'active')->get();

        return view('admin.installments.plan', compact('enrollments'));
    }

    public function store(Request $request, FeeCalculatorService $calculator)
    {
        $data = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'number_of_installments' => 'required|integer|min:1|max:12',
            'start_date' => 'required|date',
            'first_payment_amount' => 'prohibited',
            'first_payment' => 'prohibited',
        ]);
        try {
            $calculator->createInstallments($data['enrollment_id'], $data['number_of_installments'], $data['start_date']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['enrollment_id' => $exception->getMessage()]);
        }

        return redirect()->route('fees.index')->with('success', 'Fee schedule created. Record payments separately.');
    }

    public function getEnrollmentDetails(Enrollment $enrollment)
    {
        $enrollment->load('batch.course', 'student');

        return response()->json([
            'student_name' => $enrollment->student->name,
            'course_name' => $enrollment->batch->course->name,
            'total_fees' => \App\Support\Money::decimal(\App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount)),
            'has_installments' => $enrollment->feeInstallments()->exists(),
        ]);
    }
}
