@extends('layouts.admin')
@section('title', $kind === 'students' && !$person ? 'Student admission' : ($person ? 'Edit ' : 'Add ').($kind === 'students' ? 'Student' : 'Teacher'))
@section('content')
@php $noun = $kind === 'students' ? 'Student' : 'Teacher'; @endphp
<x-list.header :icon="$kind === 'students' ? 'fa-users' : 'fa-chalkboard-teacher'" :title="$kind === 'students' && !$person ? 'Student admission' : ($person ? 'Edit' : 'Add').' '.$noun" eyebrow="People" :subtitle="$person ? $person->email : ($kind === 'students' ? 'Enter student details, choose a batch and set the fee plan in one form' : 'Create the account and contact profile')" />
@if(isset($lead))
    <p class="alert alert-info">Registering from lead: {{ $lead->name }}. Review the prefilled details before saving.</p>
    @if($duplicates['students']->isNotEmpty())
        <div class="alert alert-warning">Possible existing student. Open their profile to avoid registering them twice:
            @foreach($duplicates['students'] as $duplicate)<a href="{{ route('students.show', $duplicate) }}">{{ $duplicate->name }}</a>@endforeach
        </div>
    @endif
@endif
@if($kind === 'students' && !$person && !isset($lead))
@if(can_route('students.create'))<form method="get" action="{{ route('students.create') }}" class="panel admission-student-picker">
    @if(request()->filled('batch_id'))<input type="hidden" name="batch_id" value="{{ request('batch_id') }}">@endif
    <label class="form-label" for="choose_student">Student</label>
    <div class="admission-student-picker-row">
        <select class="form-select" name="student_id" id="choose_student">
            <option value="">New student</option>
            @foreach($availableStudents ?? [] as $candidate)<option value="{{ $candidate->id }}" @selected(old('student_id', $selectedStudent->id ?? null) == $candidate->id)>{{ $candidate->name }} — {{ $candidate->email }}</option>@endforeach
        </select>
        <button class="btn btn-outline-primary" type="submit">Choose</button>
    </div>
</form>@endif
@endif
<form method="post" action="{{ $formAction ?? ($person ? route($kind.'.update', $person) : route($kind.'.store')) }}" class="panel form-panel">
    @csrf
    @if($person) @method('PUT') @endif
    @if(isset($selectedStudent))
        <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">
        <div class="form-section">
            <p class="form-section-note mb-0">{{ $selectedStudent->name }} · {{ $selectedStudent->email }}. Saved details are protected. You can complete any empty fields below.</p>
        </div>
    @endif
    <div class="form-section">
        <h2 class="form-section-title">Contact details</h2>
        <p class="form-section-note">Email is the login name.</p>
        <div class="row g-3">
            @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label required" for="{{ $field }}">{{ $label }}</label>
                    <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" @if($field === 'phone') inputmode="tel" pattern="\+?[0-9]+" maxlength="20" title="Digits only, with an optional leading +" @endif value="{{ isset($selectedStudent) && filled($selectedStudent->$field) ? $selectedStudent->$field : old($field, $prefill[$field] ?? $person?->$field) }}" @readonly(isset($selectedStudent) && filled($selectedStudent->$field)) @required(!isset($selectedStudent))>
                </div>
            @endforeach
            <div class="col-md-6">
                <label class="form-label" for="emergency_contact">Emergency contact</label>
                <input class="form-control" id="emergency_contact" name="emergency_contact" type="tel" inputmode="tel" pattern="\+?[0-9]+" maxlength="20" title="Digits only, with an optional leading +" value="{{ isset($selectedStudent) && filled($selectedStudent->emergency_contact) ? $selectedStudent->emergency_contact : old('emergency_contact', $prefill['emergency_contact'] ?? $person?->emergency_contact) }}" @readonly(isset($selectedStudent) && filled($selectedStudent->emergency_contact))>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="date_of_birth">Date of birth</label>
                <input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="{{ isset($selectedStudent) && filled($selectedStudent->date_of_birth) ? $selectedStudent->date_of_birth?->format('Y-m-d') : old('date_of_birth', $prefill['date_of_birth'] ?? $person?->date_of_birth?->format('Y-m-d')) }}" @readonly(isset($selectedStudent) && filled($selectedStudent->date_of_birth))>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="address">Address</label>
                <input class="form-control" id="address" name="address" value="{{ isset($selectedStudent) && filled($selectedStudent->address) ? $selectedStudent->address : old('address', $prefill['address'] ?? $person?->address) }}" @readonly(isset($selectedStudent) && filled($selectedStudent->address)) @required(!isset($selectedStudent))>
            </div>
            @if($kind === 'students' && !$person)
                @foreach(['father_name' => 'Father name', 'cnic' => 'CNIC'] as $field => $label)
                    <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label>
                        <input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ isset($selectedStudent) && filled($selectedStudent->$field) ? $selectedStudent->$field : old($field, $prefill[$field] ?? '') }}" maxlength="{{ $field === 'cnic' ? 20 : 255 }}" @readonly(isset($selectedStudent) && filled($selectedStudent->$field))>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
    @if($kind === 'teachers')
        <div class="form-section">
            <h2 class="form-section-title">Teaching profile</h2>
            <div class="row g-3">
                @foreach(['qualification' => 'Qualification', 'specialization' => 'Specialization', 'experience_years' => 'Experience in years'] as $field => $label)
                    @php
                        $options = config('teaching.'.$field);
                        $existing = $person?->$field;
                        if ($existing !== null && $existing !== '' && !in_array($existing, $options)) {
                            $options[] = $existing;
                        }
                        $selected = old($field, $existing);
                    @endphp
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                        <select class="form-select teaching-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" data-placeholder="Select {{ strtolower($label) }}">
                            <option value="">Select {{ strtolower($label) }}</option>
                            @foreach($options as $option)
                                <option value="{{ $option }}" @selected((string) $selected === (string) $option)>{{ $field === 'experience_years' ? ($option == 0 ? '0 years (New teacher)' : $option.' '.\Illuminate\Support\Str::plural('year', $option)) : $option }}</option>
                            @endforeach
                        </select>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="col-md-6">
                    <label class="form-label" for="hire_date">Hire date</label>
                    <input class="form-control" type="date" id="hire_date" name="hire_date" value="{{ old('hire_date', $person ? $person->hire_date?->format('Y-m-d') : today()->toDateString()) }}">
                </div>
            </div>
        </div>
        <div class="form-section">
            <h2 class="form-section-title">Pay</h2>
            <p class="form-section-note">Per-student pay is this teacher's rate × students who completed each assigned batch, paid once when the batch is completed. The same rate applies to all their batches. Changes apply only to dues generated afterwards.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="salary_type">Pay type</label>
                    <select class="form-select" id="salary_type" name="salary_type" required>
                        @foreach(['monthly' => 'Fixed monthly salary', 'per_student' => 'Per student per batch'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('salary_type', $person?->salary_type === 'per_student' ? 'per_student' : 'monthly') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6" id="monthly_salary_group" @if(old('salary_type', $person?->salary_type === 'per_student' ? 'per_student' : 'monthly') !== 'monthly') hidden @endif>
                    <label class="form-label" for="monthly_salary">Monthly salary</label>
                    <input class="form-control" id="monthly_salary" name="monthly_salary" type="number" min="0" step="0.01" max="99999999.99" value="{{ old('monthly_salary', $person?->monthly_salary) }}" aria-describedby="monthly_salary_help">
                    <div class="form-text" id="monthly_salary_help">Used only for fixed-salary teachers.</div>
                </div>
                <div class="col-md-6" id="per_student_amount_group" @if(old('salary_type', $person?->salary_type) !== 'per_student') hidden @endif>
                    <label class="form-label required" for="per_student_amount">Amount per student per batch</label>
                    <input class="form-control @error('per_student_amount') is-invalid @enderror" id="per_student_amount" name="per_student_amount" type="number" min="0.01" step="0.01" max="99999999.99" value="{{ old('per_student_amount', $person?->per_student_amount) }}" aria-describedby="per_student_amount_help" @required(old('salary_type', $person?->salary_type) === 'per_student') @disabled(old('salary_type', $person?->salary_type) !== 'per_student')>
                    <div class="form-text" id="per_student_amount_help">Batch pay = rate × students in the batch, generated once the batch is completed.</div>
                    @error('per_student_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @elseif(!$person)
        <div class="form-section">
            <h2 class="form-section-title">Batch & fee plan</h2>
            <p class="form-section-note">Select a batch to register and enroll together. The fee plan is created on save; payments are recorded separately. Choose no batch only when admission is pending.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="batch_id">Enroll in batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" @required(isset($selectedStudent))>
                        <option value="">No batch yet — register only (admission pending)</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" @selected(old('batch_id', request('batch_id')) == $batch->id)>{{ $batch->name }} — {{ $batch->course->name }} ({{ $batch->course->level }}) · {{ currency_format($batch->course->total_fee) }} · {{ ucfirst($batch->status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="installments">Fee installments</label>
                    <select class="form-select" id="installments" name="installments">
                        @for($number = 1; $number <= 12; $number++)<option value="{{ $number }}" @selected(old('installments', 1) == $number)>{{ $number === 1 ? '1 — full fee due' : $number.' monthly installments' }}</option>@endfor
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="discount_amount">Discount amount ({{ setting('currency_code', 'PKR') }})</label>
                    <input class="form-control" id="discount_amount" name="discount_amount" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('discount_amount', 0) }}">
                </div>
            </div>
            @include('partials.admission-schedule')
        </div>
    @endif
    @unless(isset($selectedStudent))
    <div class="form-section">
        <h2 class="form-section-title">Sign-in</h2>
        <p class="form-section-note">{{ $person ? 'Password is optional. Leave blank to keep the current password.' : 'Password is optional. Leave blank to set it later before the first sign-in.' }} If entered, use at least 8 characters and confirm it.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="password">{{ $person ? 'New password' : 'Password' }} (optional)</label>
                <input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirmation">Confirm password (if entered)</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
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
    @endunless
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route($kind.'.index') }}">Cancel</a>
        <button class="btn btn-primary">{{ $kind === 'students' && !$person ? 'Save student admission' : 'Save' }}</button>
    </div>
</form>
@include('partials.machine-sync')
@endsection

@if($kind === 'teachers')
    @push('scripts')
        <template data-page-module="admin-people-form"></template>
    @endpush
@endif
