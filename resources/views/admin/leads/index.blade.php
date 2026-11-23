@extends('layouts.admin')
@section('title', 'Leads')
@section('content')
@php
    $badges = [
        'new' => 'secondary', 'contacted' => 'info', 'qualified' => 'primary',
        'submitted' => 'warning', 'converted' => 'success', 'rejected' => 'dark',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0"><i class="fas fa-user-tag me-2 text-primary" aria-hidden="true"></i>Leads &amp; inquiries</h1>
    @if(can_route('leads.create'))<a href="{{ route('leads.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add lead</a>@endif
</div>

<section class="accordion mb-3" id="inquiry-link-tools">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#inquiry-link-panel" aria-expanded="false" aria-controls="inquiry-link-panel">Link validity</button>
        </h2>
        <div id="inquiry-link-panel" class="accordion-collapse collapse">
            <div class="accordion-body">
    <form method="get" action="{{ route('leads.index') }}" class="mb-3">
        @foreach(request()->only(['search', 'status', 'source']) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
        <label class="form-label" for="link_expiry">Link validity</label>
        <div class="admission-student-picker-row">
            <select class="form-select" id="link_expiry" name="link_expiry">
                @foreach(['permanent' => 'No expiry — recommended for ads', '24h' => 'Expires in 24 hours', '7d' => 'Expires in 7 days'] as $value => $label)
                    <option value="{{ $value }}" @selected($linkExpiry === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-primary">Generate link</button>
        </div>
    </form>
    <label class="form-label" for="inquiry_link">Public inquiry link — share with interested students</label>
    <div class="lead-inquiry-link-controls">
        <input class="form-control" id="inquiry_link" value="{{ $inquiryUrl }}" readonly>
        <button class="btn btn-primary" type="button" data-copy-target="inquiry_link">Copy link</button>
        <a class="btn btn-outline-primary" href="{{ $inquiryUrl }}" target="_blank" rel="noopener">Open inquiry form</a>
    </div>
    <p class="form-text mb-0" data-copy-status role="status" aria-live="polite"></p>
    <p class="form-text mb-0">Submissions arrive as New leads. Contact, qualify and convert them, or reject with a reason.</p>
    <p class="form-text mb-0">Each phone number can submit one public inquiry. Link expiry or generating a new link does not allow a repeat submission.</p>
            </div>
        </div>
    </div>
</section>
<form method="GET" action="{{ route('leads.index') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label small mb-1" for="search">Search</label>
            <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Name, phone or CNIC">
        </div>
        <div class="col-sm-3">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status" data-native>
                <option value="">All</option>
                @foreach (['new','contacted','qualified','converted','rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small mb-1" for="source">Source</label>
            <select class="form-select" id="source" name="source" data-native>
                <option value="">All</option>
                @foreach (\App\Models\Lead::FILTER_SOURCES as $src => $label)
                    <option value="{{ $src }}" @selected(request('source')===$src)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2 d-grid">
            <button type="submit" class="btn btn-dark">Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th><th>Phone</th><th>CNIC</th><th>Source</th><th>Status</th><th>Received</th><th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td class="fw-semibold">{{ $lead->name }}</td>
                        <td>{{ $lead->phone }}</td>
                        <td>{{ $lead->cnic ?: '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst($lead->source) }}</span></td>
                        <td><span class="badge bg-{{ $badges[$lead->status] ?? 'secondary' }}">{{ ucfirst($lead->status) }}</span></td>
                        <td class="text-muted small">{{ $lead->created_at->format('d-m-Y h:i A') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-outline-dark">View</a>
                            @unless(in_array($lead->status, ['submitted', 'converted'], true))
                                @if(can_route('leads.edit'))<a href="{{ route('leads.edit', $lead) }}" class="btn btn-sm btn-outline-secondary" aria-label="Edit {{ $lead->name }}"><i class="fas fa-pen" aria-hidden="true"></i></a>@endif
                            @endunless
                            @unless($lead->status === 'converted')
                                @if(can_route('leads.destroy'))<form method="POST" action="{{ route('leads.destroy', $lead) }}" class="d-inline" data-confirm="Delete this lead permanently?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $lead->name }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                </form>@endif
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No leads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leads->links() }}</div>
@endsection
