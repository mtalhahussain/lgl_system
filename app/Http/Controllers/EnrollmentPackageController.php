<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\EnrollmentPackage;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;

class EnrollmentPackageController extends Controller
{
    public function create()
    {
        $students = User::students()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $batches = Batch::with('course')->whereIn('status', ['upcoming', 'ongoing'])
            ->whereHas('course', fn ($query) => $query->where('is_active', true))
            ->orderBy('start_date')->get();

        return view('enrollment-packages.create', compact('students', 'batches'));
    }

    public function store(Request $request, EnrollmentService $service)
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'a1_batch_id' => 'required|integer|exists:batches,id',
            'a2_batch_id' => 'required|integer|different:a1_batch_id|exists:batches,id',
            'amount' => 'required|decimal:0,2', 'method' => 'required|in:cash,bank,card,jazzcash,easypaisa,online',
            'reference' => 'nullable|string|max:255', 'submission_key' => 'required|string|min:16|max:64',
            'installments' => 'prohibited', 'schedule' => 'prohibited',
        ]);
        $package = $service->enrollPackage((int) $data['student_id'], (int) $data['a1_batch_id'], (int) $data['a2_batch_id'],
            (string) $data['amount'], $data['method'], $data['submission_key'], $request->user(), $data['reference'] ?? null);

        return redirect()->route('enrollment-packages.show', $package)->with('success', 'A1 + A2 enrolled and full package payment recorded.');
    }

    public function show(Request $request, EnrollmentPackage $enrollmentPackage)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isStudent()
            && (int) $enrollmentPackage->student_id === (int) $request->user()->id), 403);
        $payments = $enrollmentPackage->payments()->with('reversal')->orderBy('id')->get();

        return view('enrollment-packages.show', ['package' => $enrollmentPackage, 'payments' => $payments]);
    }
}
