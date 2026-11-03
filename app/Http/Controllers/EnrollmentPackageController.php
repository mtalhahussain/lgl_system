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
        $batches = Batch::with(['course', 'teacher:id,name'])->withCount('activeEnrollments')
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->whereHas('course', fn ($query) => $query->where('is_active', true))
            ->orderBy('start_date')->get();
        $levels = array_values(array_filter(EnrollmentService::LEVELS, fn ($level) => $batches->contains(fn ($batch) => $batch->course->level === $level)));

        return view('enrollment-packages.create', compact('students', 'batches', 'levels'));
    }

    public function store(Request $request, EnrollmentService $service)
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'batch_ids' => 'required|array|max:6', 'batch_ids.*' => 'nullable|integer|exists:batches,id',
            'price' => 'required|decimal:0,2|gt:0|max:99999999.99', 'plan' => 'required|in:full,installments',
            'installments' => 'exclude_unless:plan,installments|required|integer|min:2|max:12',
            'method' => 'exclude_unless:plan,full|required|in:cash,bank,card,jazzcash,easypaisa,online',
            'reference' => 'nullable|string|max:255', 'submission_key' => 'required|string|min:16|max:64',
        ]);
        $full = $data['plan'] === 'full';
        $package = $service->enrollPackage((int) $data['student_id'], array_values(array_filter($data['batch_ids'])),
            (string) $data['price'], $data['plan'], $full ? 1 : (int) $data['installments'], $full ? $data['method'] : null,
            $data['submission_key'], $request->user(), $full ? ($data['reference'] ?? null) : null);

        return redirect()->route('enrollment-packages.show', $package)->with('success', $full
            ? 'Package enrolled and full payment recorded.'
            : 'Package enrolled. Installments were scheduled for each level; record payments from the fee ledger.');
    }

    public function show(Request $request, EnrollmentPackage $enrollmentPackage)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isStudent()
            && (int) $enrollmentPackage->student_id === (int) $request->user()->id), 403);
        $payments = $enrollmentPackage->payments()->with('reversal')->orderBy('id')->get();

        return view('enrollment-packages.show', ['package' => $enrollmentPackage, 'payments' => $payments]);
    }
}
