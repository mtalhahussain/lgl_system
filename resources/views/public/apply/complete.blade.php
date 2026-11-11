@extends('public.layout')

@section('title', 'Complete your registration')

@section('content')
<div class="card">
    <div class="card-header">
        <h1>Complete your registration</h1>
        <p class="text-muted mb-0 mt-1">Please confirm your details. Your CNIC is locked from our verified record.</p>
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label">CNIC</label>
                <input type="text" class="form-control" value="{{ $lead->cnic ?: 'Not on record' }}" readonly disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="name">Full name <span class="req">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $prefill['name'] ?? '') }}" required maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="father_name">Father's name</label>
                <input type="text" class="form-control" id="father_name" name="father_name" value="{{ old('father_name', $prefill['father_name'] ?? '') }}" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="phone">Phone / WhatsApp <span class="req">*</span></label>
                <input type="tel" class="form-control" id="phone" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" inputmode="tel" required maxlength="20">
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $prefill['email'] ?? '') }}" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="address">Address <span class="req">*</span></label>
                <textarea class="form-control" id="address" name="address" rows="2" required maxlength="500">{{ old('address', $prefill['address'] ?? '') }}</textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="date_of_birth">Date of birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $prefill['date_of_birth'] ?? '') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="emergency_contact">Emergency contact</label>
                    <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact', $prefill['emergency_contact'] ?? '') }}" inputmode="tel" maxlength="20">
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-lgl btn-lg">Submit registration</button>
            </div>
        </form>
    </div>
</div>
@endsection
