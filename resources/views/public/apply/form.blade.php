@extends('public.layout')

@section('title', 'Apply')

@section('content')
<div class="card">
    <div class="card-header">
        <h1>Apply for admission</h1>
        <p class="text-muted mb-0 mt-1">Fill the short form and our team will contact you to complete your registration.</p>
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

        <form method="POST" action="{{ route('apply.store') }}" novalidate>
            @csrf

            {{-- Honeypot: hidden from humans, bots fill it. --}}
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="mb-3">
                <label class="form-label" for="name">Full name <span class="req">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="father_name">Father's name</label>
                <input type="text" class="form-control" id="father_name" name="father_name" value="{{ old('father_name') }}" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="cnic">CNIC</label>
                <input type="text" class="form-control" id="cnic" name="cnic" value="{{ old('cnic') }}" inputmode="numeric" placeholder="3520112345678" maxlength="20">
                <div class="form-text">13-digit CNIC (dashes allowed). Helps us match your record.</div>
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
                <label class="form-label" for="program_interest">Course of interest</label>
                <input type="text" class="form-control" id="program_interest" name="program_interest" value="{{ old('program_interest') }}" placeholder="e.g. German A1" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="message">Message (optional)</label>
                <textarea class="form-control" id="message" name="message" rows="3" maxlength="1000">{{ old('message') }}</textarea>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-lgl btn-lg">Submit application</button>
            </div>
        </form>
    </div>
</div>
@endsection
