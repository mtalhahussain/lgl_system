@extends('layouts.admin')
@section('title', 'Create student from lead')
@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Create student — {{ $lead->name }}</h1>
    <a href="{{ route('leads.show', $lead) }}" class="btn btn-outline-secondary btn-sm">Back to lead</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if ($duplicates['students']->isNotEmpty())
    <div class="alert alert-warning">
        <strong><i class="fas fa-triangle-exclamation"></i> Possible duplicate student.</strong>
        <ul class="mb-0 mt-1 ps-3">
            @foreach ($duplicates['students'] as $u)
                <li><a href="{{ route('students.show', $u) }}">{{ $u->name }}</a> ({{ $u->phone }}{{ $u->cnic ? ', '.$u->cnic : '' }})</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('leads.convert.store', $lead) }}" class="card card-body" novalidate>
    @csrf
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $prefill['name'] ?? '') }}" required maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $prefill['email'] ?? '') }}" required maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="father_name">Father's name</label>
            <input type="text" class="form-control" id="father_name" name="father_name" value="{{ old('father_name', $prefill['father_name'] ?? '') }}" maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="cnic">CNIC</label>
            <input type="text" class="form-control" id="cnic" name="cnic" value="{{ old('cnic', $prefill['cnic'] ?? '') }}" inputmode="numeric" maxlength="20">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="phone" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" inputmode="tel" required maxlength="20">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="emergency_contact">Emergency contact</label>
            <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact', $prefill['emergency_contact'] ?? '') }}" inputmode="tel" maxlength="20">
        </div>
        <div class="col-12">
            <label class="form-label" for="address">Address <span class="text-danger">*</span></label>
            <textarea class="form-control" id="address" name="address" rows="2" required maxlength="500">{{ old('address', $prefill['address'] ?? '') }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="date_of_birth">Date of birth</label>
            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $prefill['date_of_birth'] ?? '') }}">
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                <label class="form-check-label" for="is_active">Active account</label>
            </div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-md-6">
            <label class="form-label" for="batch_id">Enroll in batch (optional)</label>
            <select class="form-select" id="batch_id" name="batch_id" data-native>
                <option value="">No enrollment now</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->id }}" @selected(old('batch_id')==$batch->id)>{{ $batch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="discount_percentage">Discount % (if enrolling)</label>
            <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" value="{{ old('discount_percentage') }}" min="0" max="100" step="0.01">
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-md-6">
            <label class="form-label" for="password">Password (optional)</label>
            <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password">
            <div class="form-text">Leave blank to set later. Min 8 characters if entered.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('leads.show', $lead) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-dark">Create student</button>
    </div>
</form>
@endsection
