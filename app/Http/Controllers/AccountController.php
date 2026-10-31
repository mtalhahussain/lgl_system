<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index()
    {
        return view('accounts.index', ['accounts' => User::orderBy('name')->paginate(25)]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)], 'is_active' => 'required|boolean']);
        DB::transaction(function () use ($request, $user, $data) {
            $admins = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless($admins->contains('id', $request->user()->id), 403);
            $target = User::lockForUpdate()->findOrFail($user->id);
            if ($target->isAdmin() && $target->is_active && ($data['role'] !== 'admin' || ! $data['is_active']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'The last active Admin must remain active.']);
            }
            if ($target->role !== $data['role'] && ($target->enrollments()->exists() || $target->taughtBatches()->exists())) {
                throw ValidationException::withMessages(['role' => 'Accounts with academic history must keep their role. Create a separate account for a new role.']);
            }
            $target->update($data);
        }, 3);

        return redirect()->route($user->id === $request->user()->id ? 'dashboard' : 'accounts.index')->with('success', 'Account access updated.');
    }
}
