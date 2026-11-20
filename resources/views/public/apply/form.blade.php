@extends('public.layout')

@section('title', 'Course inquiry')

@section('content')
<div class="card">
    <div class="card-header">
        <h1>Your German journey starts here</h1>
        <p class="text-muted mb-0 mt-1">Ready to take your first step—or build on what you already know? Tell us a little about yourself. Our team will contact you to discuss the right German level, class timings and fees, and help you plan your next step.</p>
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

        <form method="POST" action="{{ $inquiryAction ?? route('apply.store') }}">
            @csrf

            {{-- Honeypot: hidden from humans, bots fill it. --}}
            <div class="hp-field" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="mb-3">
                <label class="form-label" for="name">Full name <span class="req">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
            </div>



            <div class="mb-3">
                <label class="form-label" for="phone">Phone / WhatsApp <span class="req">*</span></label>
                <input type="tel" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" required maxlength="20">
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="program_interest">Level of interest <span class="req">*</span></label>
                <select class="form-select" id="program_interest" name="program_interest" required>
                    <option value="">Select a level</option>
                    @foreach(\App\Services\EnrollmentService::LEVELS as $code)
                        <option value="{{ $code }}" @selected(old('program_interest') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="message">Message (optional)</label>
                <textarea class="form-control" id="message" name="message" rows="3" maxlength="1000">{{ old('message') }}</textarea>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-lgl btn-lg">Send inquiry</button>
            </div>
        </form>
    </div>
</div>
@endsection
