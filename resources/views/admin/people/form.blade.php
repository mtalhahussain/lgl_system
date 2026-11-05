@extends('layouts.admin')
@section('title', ($person ? 'Edit ' : 'Add ').($kind === 'students' ? 'Student' : 'Teacher'))
@section('content')
@php $noun = $kind === 'students' ? 'Student' : 'Teacher'; @endphp
<x-list.header :title="($person ? 'Edit' : 'Add').' '.$noun" eyebrow="People" :subtitle="$person ? $person->email : 'Create the account and contact profile'" />
<form method="post" action="{{ $person ? route($kind.'.update', $person) : route($kind.'.store') }}" class="panel form-panel">
    @csrf
    @if($person) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Contact details</h2>
        <p class="form-section-note">Email is the login name.</p>
        <div class="row g-3">
            @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label required" for="{{ $field }}">{{ $label }}</label>
                    <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" value="{{ old($field, $person?->$field) }}" required>
                </div>
            @endforeach
            <div class="col-md-6">
                <label class="form-label" for="emergency_contact">Emergency contact</label>
                <input class="form-control" id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact', $person?->emergency_contact) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="date_of_birth">Date of birth</label>
                <input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $person?->date_of_birth?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="address">Address</label>
                <input class="form-control" id="address" name="address" value="{{ old('address', $person?->address) }}" required>
            </div>
        </div>
    </div>
    @include('partials.machine-sync')
    @if($kind === 'teachers')
        <div class="form-section">
            <h2 class="form-section-title">Teaching profile</h2>
            <div class="row g-3">
                @foreach(['qualification' => 'Qualification', 'specialization' => 'Specialization'] as $field => $label)
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                        <input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $person?->$field) }}">
                    </div>
                @endforeach
                <div class="col-md-6">
                    <label class="form-label" for="experience_years">Experience in years</label>
                    <input class="form-control" id="experience_years" name="experience_years" type="number" value="{{ old('experience_years', $person?->experience_years) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="hire_date">Hire date</label>
                    <input class="form-control" type="date" id="hire_date" name="hire_date" value="{{ old('hire_date', $person?->hire_date?->format('Y-m-d')) }}">
                </div>
            </div>
        </div>
        <div class="form-section">
            <h2 class="form-section-title">Pay</h2>
            <p class="form-section-note">Per-student teachers earn the course's teacher amount for each student in each batch, every month. Changes apply to dues generated afterwards.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="salary_type">Pay type</label>
                    <select class="form-select" id="salary_type" name="salary_type" required>
                        @foreach(['monthly' => 'Fixed monthly salary', 'per_student' => 'Per student per month'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('salary_type', $person?->salary_type === 'per_student' ? 'per_student' : 'monthly') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="monthly_salary">Monthly salary</label>
                    <input class="form-control" id="monthly_salary" name="monthly_salary" type="number" min="0" step="0.01" max="99999999.99" value="{{ old('monthly_salary', $person?->monthly_salary) }}" aria-describedby="monthly_salary_help">
                    <div class="form-text" id="monthly_salary_help">Used only for fixed-salary teachers.</div>
                </div>
            </div>
        </div>
    @elseif(!$person)
        <div class="form-section">
            <h2 class="form-section-title">Admission (optional)</h2>
            <p class="form-section-note">Choosing a batch admits the student and creates a one-installment fee plan.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="batch_id">Enroll in batch</label>
                    <select class="form-select" id="batch_id" name="batch_id">
                        <option value="">Register without enrollment</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" @selected(old('batch_id') == $batch->id)>{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="discount_percentage">Discount percentage</label>
                    <input class="form-control" id="discount_percentage" name="discount_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('discount_percentage', 0) }}">
                </div>
            </div>
        </div>
    @endif
    <div class="form-section">
        <h2 class="form-section-title">Sign-in</h2>
        <p class="form-section-note">{{ $person ? 'Leave both password fields blank to keep the current password. A new password needs at least 8 characters.' : 'At least 8 characters.' }}</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label {{ $person ? '' : 'required' }}" for="password">{{ $person ? 'New password' : 'Password' }}</label>
                <input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(!$person)>
            </div>
            <div class="col-md-6">
                <label class="form-label {{ $person ? '' : 'required' }}" for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$person)>
            </div>
            <div class="col-md-6">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch form-switch-row">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $person?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active account</label>
                </div>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route($kind.'.index') }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
