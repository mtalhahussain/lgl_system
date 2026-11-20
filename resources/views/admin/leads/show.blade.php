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
    <div class="d-flex flex-wrap gap-2">
        @unless(in_array($lead->status, ['submitted', 'converted'], true))
            @if(can_route('leads.edit'))<a href="{{ route('leads.edit', $lead) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>@endif
        @endunless
        @unless($lead->status === 'converted')
            @if(can_route('leads.destroy'))<form method="POST" action="{{ route('leads.destroy', $lead) }}" class="d-inline" onsubmit="return confirm('Delete this lead permanently?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1" aria-hidden="true"></i>Delete</button>
            </form>@endif
        @endunless
        <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary btn-sm">Back to leads</a>
    </div>
</div>

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
                <dt class="col-sm-4">Level interest</dt><dd class="col-sm-8">{{ $lead->program_interest ?: '—' }}</dd>
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
            <h2 class="h6 text-uppercase text-muted">Inquiry follow-up</h2>
            @if(in_array($lead->status, ['new', 'contacted']))
                @if($lead->status === 'new')
                    @if(can_route('leads.contact'))<form method="post" action="{{ route('leads.contact', $lead) }}" class="mb-3">@csrf
                        <button class="btn btn-outline-primary w-100">Mark as contacted</button>
                    </form>@endif
                @endif
                <p class="small text-muted">Contact the person, confirm their interest and qualify them when ready.</p>
                @if(can_route('leads.qualify'))<form method="post" action="{{ route('leads.qualify', $lead) }}" class="mb-3">@csrf
                    <button class="btn btn-primary w-100">Qualify lead</button>
                </form>@endif
            @elseif(in_array($lead->status, ['qualified', 'submitted']))
                <p class="small text-muted">This lead is ready for review and student registration.</p>
                @if(can_route('leads.convert'))<a href="{{ route('leads.convert', $lead) }}" class="btn btn-primary w-100 mb-3">Convert to student</a>@endif
            @elseif($lead->status === 'converted')
                <div class="alert alert-success">Converted to a student. @if($lead->student)<a href="{{ route('students.show', $lead->student) }}">Open student</a>.@endif</div>
            @elseif($lead->status === 'rejected')
                <div class="alert alert-dark">Lead rejected: {{ $lead->rejected_reason }}</div>
            @endif
            @unless(in_array($lead->status, ['converted', 'rejected']))
                @if(can_route('leads.reject'))<form method="post" action="{{ route('leads.reject', $lead) }}">@csrf
                    <label class="form-label small" for="reason">Reject reason</label>
                    <textarea class="form-control mb-2" id="reason" name="reason" rows="2" minlength="5" maxlength="500" required></textarea>
                    <button class="btn btn-outline-danger w-100">Reject lead</button>
                </form>@endif
            @endunless
        </div>
    </div>
</div>
@endsection
