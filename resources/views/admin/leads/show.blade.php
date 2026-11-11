@extends('layouts.admin')
@section('title', $lead->name)
@section('content')
@php
    $badges = [
        'new' => 'secondary', 'contacted' => 'info', 'qualified' => 'primary',
        'submitted' => 'warning', 'converted' => 'success', 'rejected' => 'dark',
    ];
    $hasDupes = $duplicates['students']->isNotEmpty() || $duplicates['leads']->isNotEmpty();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <h1 class="h4 mb-1">{{ $lead->name }}</h1>
        <span class="badge bg-{{ $badges[$lead->status] ?? 'secondary' }}">{{ ucfirst($lead->status) }}</span>
        <span class="badge bg-light text-dark border">{{ ucfirst($lead->source) }}</span>
    </div>
    <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary btn-sm">Back to leads</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if ($hasDupes)
    <div class="alert alert-warning">
        <strong><i class="fas fa-triangle-exclamation"></i> Possible duplicate.</strong> This CNIC/phone already appears:
        <ul class="mb-0 mt-1 ps-3">
            @foreach ($duplicates['students'] as $u)
                <li>Existing student: <a href="{{ route('students.show', $u) }}">{{ $u->name }}</a> ({{ $u->phone }}{{ $u->cnic ? ', '.$u->cnic : '' }})</li>
            @endforeach
            @foreach ($duplicates['leads'] as $l)
                <li>Open lead: <a href="{{ route('leads.show', $l) }}">{{ $l->name }}</a> ({{ ucfirst($l->status) }})</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-body">
            <h2 class="h6 text-uppercase text-muted">Applicant details</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Father's name</dt><dd class="col-sm-8">{{ $lead->father_name ?: '—' }}</dd>
                <dt class="col-sm-4">CNIC</dt><dd class="col-sm-8">{{ $lead->cnic ?: '—' }}</dd>
                <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $lead->phone }}</dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $lead->email ?: '—' }}</dd>
                <dt class="col-sm-4">Course interest</dt><dd class="col-sm-8">{{ $lead->program_interest ?: '—' }}</dd>
                <dt class="col-sm-4">Message</dt><dd class="col-sm-8">{{ $lead->message ?: '—' }}</dd>
                <dt class="col-sm-4">Received</dt><dd class="col-sm-8">{{ $lead->created_at->format('d-m-Y h:i A') }}</dd>
                @if ($lead->verifiedBy)
                    <dt class="col-sm-4">Reviewed by</dt><dd class="col-sm-8">{{ $lead->verifiedBy->name }} · {{ optional($lead->verified_at)->format('d-m-Y h:i A') }}</dd>
                @endif
                @if ($lead->status === 'rejected')
                    <dt class="col-sm-4">Reject reason</dt><dd class="col-sm-8">{{ $lead->rejected_reason }}</dd>
                @endif
                @if ($lead->student)
                    <dt class="col-sm-4">Student</dt><dd class="col-sm-8"><a href="{{ route('students.show', $lead->student) }}">{{ $lead->student->name }}</a></dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-body">
            <h2 class="h6 text-uppercase text-muted">Pipeline</h2>

            @if (in_array($lead->status, ['new','contacted']))
                <form method="POST" action="{{ route('leads.qualify', $lead) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-lgl w-100" style="background:#c8102e;border-color:#c8102e;color:#fff;">
                        <i class="fas fa-check"></i> Qualify &amp; generate application link
                    </button>
                </form>

                <form method="POST" action="{{ route('leads.reject', $lead) }}">
                    @csrf
                    <label class="form-label small" for="reason">Reject reason</label>
                    <textarea class="form-control mb-2" id="reason" name="reason" rows="2" minlength="5" maxlength="500" placeholder="Why this lead is rejected (min 5 chars)"></textarea>
                    <button type="submit" class="btn btn-outline-dark w-100">Reject lead</button>
                </form>

            @elseif ($lead->status === 'qualified')
                <p class="small text-muted">Share this one-time link with the applicant (valid 48 hours). They confirm their details; CNIC stays locked.</p>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="prefill" value="{{ $prefillUrl }}" readonly>
                    <button class="btn btn-dark" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('prefill').value)">Copy</button>
                </div>
                <p class="small text-muted mb-0">Waiting for the applicant to complete their registration.</p>

            @elseif ($lead->status === 'submitted')
                <div class="alert alert-success small">Applicant completed their registration on {{ optional($lead->submitted_at)->format('d-m-Y h:i A') }}.</div>
                <a href="{{ route('leads.convert', $lead) }}" class="btn btn-lgl w-100" style="background:#c8102e;border-color:#c8102e;color:#fff;">
                    <i class="fas fa-user-plus"></i> Review &amp; create student
                </a>

            @elseif ($lead->status === 'converted')
                <div class="alert alert-success mb-0">Converted to a student account.
                    @if ($lead->student)<a href="{{ route('students.show', $lead->student) }}">Open student</a>.@endif
                </div>

            @elseif ($lead->status === 'rejected')
                <div class="alert alert-dark mb-0">This lead was rejected.</div>
            @endif
        </div>
    </div>
</div>
@endsection
