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
    <h1 class="h4 mb-0">Leads &amp; applications</h1>
</div>

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
                @foreach (['new','contacted','qualified','submitted','converted','rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small mb-1" for="source">Source</label>
            <select class="form-select" id="source" name="source" data-native>
                <option value="">All</option>
                @foreach (['public','meta','manual'] as $src)
                    <option value="{{ $src }}" @selected(request('source')===$src)>{{ ucfirst($src) }}</option>
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
                        <td class="text-end"><a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-outline-dark">View</a></td>
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
