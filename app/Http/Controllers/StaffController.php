<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Admin: non-teaching staff (receptionist, cleaner, security guard, …). Paid monthly, listed in Users; signs in only when given an email. */
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
        DB::transaction(function () use ($data) {
            // Email and password are required columns; staff without sign-in get unusable placeholders.
            $staff = User::create($data + ['role' => 'staff', 'salary_type' => 'monthly']);
            $staff->update(['employee_id' => 'STF'.str_pad((string) $staff->id, 4, '0', STR_PAD_LEFT)]);
        });

        return redirect()->route('accounts.index', ['role' => 'staff'])->with('success', 'Staff member added.');
    }

    public function show(User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        return view('staff.show', ['person' => $staff]);
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
        DB::transaction(fn () => $staff->update($data));

        return redirect()->route('accounts.index', ['role' => 'staff'])->with('success', 'Staff member updated.');
    }

    private function validated(Request $request, ?User $staff): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:60',
            'phone' => 'required|string|max:20|regex:/^\+?[0-9]+$/D',
            'address' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:20|regex:/^\+?[0-9]+$/D',
            'hire_date' => 'nullable|date',
            'monthly_salary' => 'required|decimal:0,2|min:0|max:99999999.99',
            'is_active' => 'sometimes|boolean',
            'email' => ['nullable', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($staff?->id)],
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        $data['is_active'] = $request->boolean('is_active', $staff?->is_active ?? true);
        // Blank email = no sign-in (unusable placeholder). A new password is set only when typed.
        $data['email'] = filled($data['email'] ?? null) ? $data['email']
            : ($staff && str_ends_with($staff->email, '@staff.invalid') ? $staff->email : 'staff-'.Str::uuid().'@staff.invalid');
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } elseif (! $staff) {
            $data['password'] = Hash::make(Str::random(64));
        } else {
            unset($data['password']);
        }

        return $data;
    }
}
