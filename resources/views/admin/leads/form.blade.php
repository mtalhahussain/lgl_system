@extends('layouts.admin')
@section('title', $lead ? 'Edit Lead' : 'Add Lead')
@section('content')
<x-list.header icon="fa-user-tag" :title="$lead ? 'Edit lead' : 'Add lead'" eyebrow="Leads" subtitle="Walk-in, phone call or referral enquiries entered by hand. Qualify, reject and convert from the lead page as usual." />
<form method="post" action="{{ $lead ? route('leads.update', $lead) : route('leads.store') }}" class="panel form-panel">
    @csrf
    @if($lead) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Applicant</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $lead?->name) }}" maxlength="255" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="father_name">Father's name</label>
                <input class="form-control @error('father_name') is-invalid @enderror" id="father_name" name="father_name" value="{{ old('father_name', $lead?->father_name) }}" maxlength="255">
                @error('father_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="phone">Phone</label>
                <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" inputmode="tel" pattern="\+?[0-9]+" maxlength="20" title="Digits only, with an optional leading +" value="{{ old('phone', $lead?->phone) }}" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="cnic">CNIC</label>
                <input class="form-control @error('cnic') is-invalid @enderror" id="cnic" name="cnic" inputmode="numeric" maxlength="20" placeholder="3520112345678" value="{{ old('cnic', $lead?->cnic) }}">
                @error('cnic')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" maxlength="255" value="{{ old('email', $lead?->email) }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="program_interest">Level interest</label>
                <input class="form-control @error('program_interest') is-invalid @enderror" id="program_interest" name="program_interest" maxlength="255" placeholder="A1 German" value="{{ old('program_interest', $lead?->program_interest) }}">
                @error('program_interest')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if(! $lead || in_array($lead->status, $statuses, true))
                <div class="col-md-6">
                    <label class="form-label required" for="status">Status</label>
                    <select class="form-select" id="status" name="status" required data-native>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $lead?->status ?? 'new') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12">
                <label class="form-label" for="message">Notes</label>
                <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="3" maxlength="1000">{{ old('message', $lead?->message) }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ $lead ? route('leads.show', $lead) : route('leads.index') }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
