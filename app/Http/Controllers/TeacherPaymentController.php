<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchTeacherEarning;
use App\Models\TeacherPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = TeacherPayment::with(['batchTeacherEarning.batch', 'batchTeacherEarning.teacher']);
        if ($request->filled('teacher_id')) {
            $query->whereHas('batchTeacherEarning', function ($q) use ($request) {
                $q->where('teacher_id', $request->teacher_id);
            });
        }
        if ($request->filled('batch_id')) {
            $query->whereHas('batchTeacherEarning', function ($q) use ($request) {
                $q->where('batch_id', $request->batch_id);
            });
        }
        $payments = $query->orderBy('payment_date', 'desc')->paginate(20);
        return view('admin.teacher_payments.index', compact('payments'));
    }

    public function create(Request $request)
    {
        $batches = Batch::with('teacher')->get();
        $batchEarnings = BatchTeacherEarning::with('batch', 'teacher')->get();
        return view('admin.teacher_payments.create', compact('batches', 'batchEarnings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_teacher_earning_id' => 'required|exists:batch_teacher_earnings,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        TeacherPayment::create($request->all());
        return redirect()->route('teacher_payments.index')->with('success', 'Payment recorded successfully!');
    }
}
