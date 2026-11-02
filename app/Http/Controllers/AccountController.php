<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $roles = array_combine(User::ROLES, array_map('ucfirst', User::ROLES));
        $list = ListQuery::for(User::query(), $request)
            ->search(['name', 'email'])
            ->filter('role', fn ($query, $role) => $query->where('role', $role), User::ROLES)
            ->filter('status', fn ($query, $status) => $query->where('is_active', $status === 'active'), ['active', 'inactive'])
            ->sorts(['name' => 'name', 'role' => 'role', 'created' => 'created_at'], 'name');
        if ($list->wantsCsv()) {
            return $list->csv('accounts', ['Name' => fn ($user) => $user->name, 'Email' => fn ($user) => $user->email,
                'Role' => fn ($user) => $user->role, 'Status' => fn ($user) => $user->is_active ? 'Active' : 'Inactive',
                'Created' => fn ($user) => $user->created_at?->toDateString()]);
        }
        $counts = $list->counts(['admin' => "users.role = 'admin'", 'teacher' => "users.role = 'teacher'",
            'student' => "users.role = 'student'", 'inactive' => 'users.is_active = 0']);
        $kpis = [['label' => 'Admins', 'value' => $counts['admin'], 'icon' => 'fa-user-shield'],
            ['label' => 'Teachers', 'value' => $counts['teacher'], 'icon' => 'fa-chalkboard-teacher'],
            ['label' => 'Students', 'value' => $counts['student'], 'icon' => 'fa-users'],
            ['label' => 'Inactive', 'value' => $counts['inactive'], 'icon' => 'fa-user-slash']];
        $filters = [['name' => 'role', 'label' => 'Role', 'options' => array_map(fn ($label) => $label.'s', $roles)],
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]];

        return view('accounts.index', ['accounts' => $list->paginate(), 'roles' => $roles, 'kpis' => $kpis, 'filters' => $filters]);
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

        $self = $user->id === $request->user()->id;
        if ($request->expectsJson()) {
            $target = $user->fresh();

            return response()->json(['message' => "{$target->name}: ".ucfirst($target->role).', '.($target->is_active ? 'active' : 'inactive').'.',
                'role' => $target->role, 'is_active' => $target->is_active,
                'redirect' => $self && ($target->role !== 'admin' || ! $target->is_active) ? route('dashboard') : null]);
        }

        return redirect()->route($self ? 'dashboard' : 'accounts.index')->with('success', 'User access updated.');
    }
}
