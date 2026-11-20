@extends('layouts.admin')
@section('title', 'Add User')
@section('content')
@php $role = old('role', request('role', 'teacher')); @endphp
<x-list.header title="Add user" eyebrow="Administration" subtitle="Create an account of any role by hand. Admin, Teacher and Student can sign in; Staff are paid and use the attendance machine but never sign in." />
<form method="post" action="{{ route('accounts.store') }}" class="panel form-panel" id="accountCreateForm">
    @csrf
    <div class="form-section">
        <h2 class="form-section-title">Role and details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="role">Role</label>
                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required data-native>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Menu access for Teacher and Student follows Users &gt; Role permissions.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="255" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="email" data-required-for="admin teacher student">Email (sign-in)</label>
                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" aria-describedby="email-help" @required($role !== 'staff')>
                <div class="form-text" id="email-help" data-role-section="staff">Optional for staff. With an email and password the staff member can sign in and use the modules Admin grants to Staff (Users &gt; Role permissions). Leave blank for staff who never sign in.</div>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone" data-required-for="teacher staff">Phone</label>
                <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" inputmode="tel" pattern="\+?[0-9]+" maxlength="20" title="Digits only, with an optional leading +" value="{{ old('phone') }}">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address">Address</label>
                <input class="form-control @error('address') is-invalid @enderror" id="address" name="address" maxlength="500" value="{{ old('address') }}">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6" data-role-section="teacher staff">
                <label class="form-label" for="hire_date">Joining date</label>
                <input class="form-control @error('hire_date') is-invalid @enderror" type="date" id="hire_date" name="hire_date" value="{{ old('hire_date', today()->toDateString()) }}">
                @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6" data-role-section="staff">
                <label class="form-label required" for="designation">Job</label>
                <input class="form-control @error('designation') is-invalid @enderror" id="designation" name="designation" list="designations" maxlength="60" value="{{ old('designation') }}" placeholder="Receptionist">
                <datalist id="designations">@foreach($designations as $designation)<option value="{{ $designation }}">@endforeach</datalist>
                @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-section" data-role-section="teacher staff">
        <h2 class="form-section-title">Pay</h2>
        <p class="form-section-note">Fixed monthly salaries are created by Payroll &gt; Generate month. Per-student teachers are paid once per completed batch.</p>
        <div class="row g-3">
            <div class="col-md-6" data-role-section="teacher">
                <label class="form-label required" for="salary_type">Pay type</label>
                <select class="form-select @error('salary_type') is-invalid @enderror" id="salary_type" name="salary_type" data-native>
                    @foreach(['monthly' => 'Fixed monthly salary', 'per_student' => 'Per student per batch'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('salary_type', 'monthly') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('salary_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6" data-pay-field="monthly">
                <label class="form-label" for="monthly_salary" data-required-for="staff">Monthly salary</label>
                <input class="form-control @error('monthly_salary') is-invalid @enderror" type="number" id="monthly_salary" name="monthly_salary" min="0" step="0.01" max="99999999.99" value="{{ old('monthly_salary') }}">
                @error('monthly_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6" data-role-section="teacher" data-pay-field="per_student">
                <label class="form-label required" for="per_student_amount">Amount per student per batch</label>
                <input class="form-control @error('per_student_amount') is-invalid @enderror" type="number" id="per_student_amount" name="per_student_amount" min="0.01" step="0.01" max="99999999.99" value="{{ old('per_student_amount') }}">
                @error('per_student_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <h2 class="form-section-title">Password (optional)</h2>
        <p class="form-section-note">Leave blank to create the account without a usable password; set one later from the person's edit page.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="password">Password</label>
                <input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password" minlength="8" autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
            </div>
        </div>
    </div>

    <div class="form-section">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('accounts.index') }}">Cancel</a>
        <button class="btn btn-primary">Create user</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('accountCreateForm');
    const role = document.getElementById('role');
    const payType = document.getElementById('salary_type');
    if (!form || !role) return;
    const has = (el, attr, value) => (el.getAttribute(attr) || '').split(' ').includes(value);
    function sync() {
        const current = role.value;
        // Hide and disable other roles' sections so they are neither shown nor submitted.
        form.querySelectorAll('[data-role-section]').forEach(section => {
            const show = has(section, 'data-role-section', current);
            section.hidden = !show;
            section.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = !show; });
        });
        const perStudent = current === 'teacher' && payType.value === 'per_student';
        form.querySelectorAll('[data-pay-field]').forEach(field => {
            const show = field.dataset.payField === 'monthly' ? !perStudent : perStudent;
            const inRole = !field.hasAttribute('data-role-section') || has(field, 'data-role-section', current);
            field.hidden = !(show && inRole);
            field.querySelectorAll('input').forEach(input => { input.disabled = field.hidden; });
        });
        document.getElementById('email').required = current !== 'staff';
        document.getElementById('designation').required = current === 'staff';
        document.getElementById('phone').required = ['teacher', 'staff'].includes(current);
        document.getElementById('monthly_salary').required = current === 'staff';
        document.getElementById('per_student_amount').required = perStudent;
        form.querySelectorAll('[data-required-for]').forEach(label => {
            label.classList.toggle('required', has(label, 'data-required-for', current));
        });
    }
    role.addEventListener('change', sync);
    payType.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
