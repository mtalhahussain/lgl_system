@extends('layouts.admin')
@section('title', $person ? 'Edit Staff' : 'Add Staff')
@section('content')
<x-list.header :title="$person ? 'Edit staff member' : 'Add staff member'" eyebrow="People" subtitle="Receptionist, cleaner, security guard and other non-teaching staff. Staff are paid monthly and do not sign in." />
<form method="post" action="{{ $person ? route('staff.update', $person) : route('staff.store') }}" class="panel form-panel">
    @csrf
    @if($person) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $person?->name) }}" maxlength="255" required>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="designation">Job</label>
                <input class="form-control" id="designation" name="designation" list="designations" maxlength="60" value="{{ old('designation', $person?->designation) }}" placeholder="Receptionist" required>
                <datalist id="designations">@foreach($designations as $designation)<option value="{{ $designation }}">@endforeach</datalist>
                <div class="form-text">Pick from the list or type another job.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" pattern="\+?[0-9]+" maxlength="20" title="Digits only, with an optional leading +" value="{{ old('phone', $person?->phone) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emergency_contact">Emergency contact</label>
                <input class="form-control" id="emergency_contact" name="emergency_contact" type="tel" inputmode="tel" pattern="\+?[0-9]+" maxlength="20" title="Digits only, with an optional leading +" value="{{ old('emergency_contact', $person?->emergency_contact) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="hire_date">Joining date</label>
                <input class="form-control" type="date" id="hire_date" name="hire_date" value="{{ old('hire_date', $person ? $person->hire_date?->format('Y-m-d') : today()->toDateString()) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address">Address</label>
                <input class="form-control" id="address" name="address" maxlength="500" value="{{ old('address', $person?->address) }}">
            </div>
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Pay and attendance machine</h2>
        <p class="form-section-note">Payroll &gt; Generate month creates this salary every month.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="monthly_salary">Monthly salary</label>
                <input class="form-control" type="number" id="monthly_salary" name="monthly_salary" min="0" step="0.01" value="{{ old('monthly_salary', $person?->monthly_salary) }}" required>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $person?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active (switch off when the person leaves; history and slips are kept)</label>
                </div>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('accounts.index', ['role' => 'staff']) }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@include('partials.machine-sync')
@endsection
