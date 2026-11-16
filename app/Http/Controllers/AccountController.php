<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $roles = array_combine(User::ALL_ROLES, array_map(fn ($role) => $role === 'staff' ? 'Staff' : ucfirst($role), User::ALL_ROLES));
        $list = ListQuery::for(User::query(), $request)
            ->search(['name', 'email', 'designation'])
            ->filter('role', fn ($query, $role) => $query->where('role', $role), User::ALL_ROLES)
            ->filter('status', fn ($query, $status) => $query->where('is_active', $status === 'active'), ['active', 'inactive'])
            ->sorts(['name' => 'name', 'role' => 'role', 'created' => 'created_at'], 'name');
        if ($list->wantsCsv()) {
            return $list->csv('accounts', ['Name' => fn ($user) => $user->name, 'Email' => fn ($user) => $user->email,
                'Role' => fn ($user) => $user->role, 'Job' => fn ($user) => $user->designation, 'Status' => fn ($user) => $user->is_active ? 'Active' : 'Inactive',
                'Created' => fn ($user) => $user->created_at?->toDateString()]);
        }
        $counts = $list->counts(['teacher' => "users.role = 'teacher'",
            'student' => "users.role = 'student'", 'staff' => "users.role = 'staff'", 'inactive' => 'users.is_active = 0']);
        $kpis = [['label' => 'Teachers', 'value' => $counts['teacher'], 'icon' => 'fa-chalkboard-teacher'],
            ['label' => 'Students', 'value' => $counts['student'], 'icon' => 'fa-users'],
            ['label' => 'Staff', 'value' => $counts['staff'], 'icon' => 'fa-id-badge'],
            ['label' => 'Inactive', 'value' => $counts['inactive'], 'icon' => 'fa-user-slash']];
        $filters = [['name' => 'role', 'label' => 'Role', 'options' => array_map(fn ($label) => $label === 'Staff' ? $label : $label.'s', array_diff_key($roles, ['admin' => true]))],
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]];

        $permissions = RolePermission::query()->get()->mapWithKeys(fn ($row) => [$row->role.'.'.$row->module => $row->allowed])->all();

        return view('accounts.index', ['accounts' => $list->paginate(), 'roles' => $roles, 'kpis' => $kpis, 'filters' => $filters,
            'permissionRoles' => collect(RolePermission::managedRoles())->mapWithKeys(fn ($role) => [$role => RolePermission::modulesFor($role)])->all(),
            'permissions' => $permissions]);
    }

    public const ROLE_LABELS = ['admin' => 'Admin', 'teacher' => 'Teacher', 'student' => 'Student', 'staff' => 'Staff (no sign-in)'];

    public function create()
    {
        return view('accounts.create', ['roles' => self::ROLE_LABELS, 'designations' => StaffController::DESIGNATIONS]);
    }

    /** Admin creates a user of any role by hand; role-specific fields are validated for the chosen role. */
    public function store(Request $request)
    {
        $role = (string) $request->input('role');
        $signsIn = $role !== 'staff';
        $paid = in_array($role, User::PAID_ROLES, true);
        // Fields of other roles' sections are excluded (ignored), so a form left filled in for another role cannot leak values.
        $data = $request->validate([
            'role' => ['required', Rule::in(User::ALL_ROLES)],
            'name' => 'required|string|max:255',
            'email' => ['exclude_if:role,staff', 'required', 'email', 'max:255', 'unique:users,email'],
            'phone' => [$paid ? 'required' : 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
            'address' => 'nullable|string|max:500',
            'password' => ['exclude_if:role,staff', 'nullable', 'required_with:password_confirmation', 'string', 'min:8', 'confirmed'],
            'designation' => ['exclude_unless:role,staff', 'required', 'string', 'max:60'],
            'hire_date' => ['exclude_unless:role,teacher,staff', 'nullable', 'date'],
            'salary_type' => ['exclude_unless:role,teacher', 'required', 'in:monthly,per_student'],
            'monthly_salary' => ['exclude_unless:role,teacher,staff', $role === 'staff' ? 'required' : 'nullable', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'per_student_amount' => ['exclude_unless:role,teacher', 'required_if:salary_type,per_student', 'nullable', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'is_active' => 'sometimes|boolean',
        ]);
        $user = DB::transaction(function () use ($data, $request, $role, $signsIn) {
            $attributes = collect($data)->except(['password', 'per_student_amount'])->all();
            $attributes['is_active'] = $request->boolean('is_active', true);
            // No shared/default password: blank stores a random, undisclosed hash; a usable one can be set later.
            $attributes['password'] = Hash::make(filled($data['password'] ?? null) ? $data['password'] : Str::random(64));
            if (! $signsIn) {
                // Staff never signs in; email is a required column, so it gets an unusable placeholder.
                $attributes['email'] = 'staff-'.Str::uuid().'@staff.invalid';
                $attributes['salary_type'] = 'monthly';
            }
            if ($role === 'teacher') {
                $attributes['per_student_amount'] = $data['salary_type'] === 'per_student' ? $data['per_student_amount'] : null;
                $attributes['experience_years'] = 0;
            }
            $user = User::create($attributes);
            if ($prefix = ['teacher' => 'TCH', 'staff' => 'STF'][$role] ?? null) {
                $user->update(['employee_id' => $prefix.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT)]);
            }

            return $user;
        }, 3);

        return redirect()->route('accounts.index', $role === 'admin' ? [] : ['role' => $role])
            ->with('success', ucfirst($role)." account for {$user->name} created.");
    }

    /** Admin shows or hides Teacher/Student modules; hidden modules leave the menu and their pages return 403. */
    public function updatePermissions(Request $request)
    {
        $request->validate(['permissions' => 'sometimes|array', 'permissions.*' => 'array']);
        $submitted = $request->input('permissions', []);
        DB::transaction(function () use ($submitted, $request) {
            $changes = [];
            foreach (RolePermission::managedRoles() as $role) {
                foreach (array_keys(RolePermission::modulesFor($role)) as $module) {
                    $allowed = ! empty($submitted[$role][$module]);
                    $row = RolePermission::firstOrNew(['role' => $role, 'module' => $module]);
                    if (! $row->exists || $row->allowed !== $allowed) {
                        $changes["{$role}.{$module}"] = $allowed;
                        $row->allowed = $allowed;
                        $row->save();
                    }
                }
            }
            if ($changes) {
                AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'role_permissions.updated',
                    'subject_type' => 'role_permissions', 'subject_id' => $request->user()->id, 'changes' => $changes]);
            }
        });

        return redirect()->to(route('accounts.index').'#role-permissions')->with('success', 'Role permissions saved.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ALL_ROLES)], 'is_active' => 'required|boolean']);
        // Staff never signs in, so a staff record cannot become a login account (or the reverse); create a new account instead.
        if (($data['role'] === 'staff') !== $user->isStaff()) {
            throw ValidationException::withMessages(['role' => 'Staff records cannot change to or from a sign-in role.']);
        }
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
            if ($target->isTeacher() && $target->is_active && ! $data['is_active'] && $target->hasOpenBatches()) {
                throw ValidationException::withMessages(['is_active' => "Reassign this teacher's active and upcoming batches before deactivating them."]);
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
