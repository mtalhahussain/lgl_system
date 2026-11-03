<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin: non-teaching staff (receptionist, cleaner, security guard, …). Paid monthly, listed in Users, no sign-in. */
class StaffController extends Controller
{
    public const DESIGNATIONS = ['Receptionist', 'Cleaner', 'Security guard', 'Office assistant', 'Driver', 'Cook'];

    public function create()
    {
        return view('staff.form', ['person' => null, 'designations' => self::DESIGNATIONS]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $machineNo = $data['device_employee_no'] ?? null;
        unset($data['device_employee_no']);
        DB::transaction(function () use ($data, $machineNo) {
            // Email and password are required columns; staff never signs in, so both are unusable placeholders.
            $staff = User::create($data + ['role' => 'staff', 'salary_type' => 'monthly',
                'email' => 'staff-'.Str::uuid().'@staff.invalid', 'password' => Hash::make(Str::random(64))]);
            $staff->update(['employee_id' => 'STF'.str_pad((string) $staff->id, 4, '0', STR_PAD_LEFT)]);
            if ($machineNo !== null) {
                $staff->forceFill(['device_employee_no' => $machineNo])->save();
            }
        });

        return redirect()->route('accounts.index', ['role' => 'staff'])->with('success', 'Staff member added.');
    }

    public function edit(User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        return view('staff.form', ['person' => $staff, 'designations' => self::DESIGNATIONS]);
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        $data = $this->validated($request, $staff);
        $machineNo = $data['device_employee_no'] ?? null;
        unset($data['device_employee_no']);
        // Empty keeps the current (automatic) machine number.
        DB::transaction(fn () => $staff->forceFill(['device_employee_no' => $machineNo ?? $staff->device_employee_no])->fill($data)->save());

        return redirect()->route('accounts.index', ['role' => 'staff'])->with('success', 'Staff member updated.');
    }

    private function validated(Request $request, ?User $staff): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:60',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'monthly_salary' => 'required|decimal:0,2|min:0|max:99999999.99',
            'is_active' => 'sometimes|boolean',
            'device_employee_no' => ['nullable', 'regex:/^[A-Za-z0-9]{1,32}$/D',
                Rule::unique('users', 'device_employee_no')->ignore($staff?->id)],
        ]);
        $data['is_active'] = $request->boolean('is_active', $staff?->is_active ?? true);

        return $data;
    }
}
