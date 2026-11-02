@extends('layouts.admin')
@section('title', $batch ? 'Edit Batch' : 'Add Batch')
@section('content')
<x-list.header :title="$batch ? 'Edit Batch' : 'Add Batch'" eyebrow="Academics" :subtitle="$batch ? $batch->name : 'Course, teacher, dates and class delivery'" />
<form method="post" class="panel form-panel" action="{{ $batch ? route('batches.update', $batch) : route('batches.store') }}">
    @csrf
    @if($batch) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Batch</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $batch?->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" aria-describedby="status-help">
                    <option value="auto" @selected(old('status', $batch?->status) !== 'cancelled')>Automatic from dates</option>
                    <option value="cancelled" @selected(old('status', $batch?->status) === 'cancelled')>Cancelled</option>
                </select>
                <div class="form-text" id="status-help">Upcoming, ongoing and completed follow the start and end dates.@if($batch && $batch->status !== 'cancelled') Current: {{ ucfirst($batch->status) }}.@endif</div>
            </div>
            @foreach(['course_id' => ['Course', $courses], 'teacher_id' => ['Teacher', $teachers]] as $field => [$label, $options])
                <div class="col-md-6">
                    <label class="form-label required" for="{{ $field }}">{{ $label }}</label>
                    <select class="form-select" id="{{ $field }}" name="{{ $field }}" required>
                        @foreach($options as $option)
                            <option value="{{ $option->id }}" @selected(old($field, $batch?->$field ?? ($field === 'course_id' ? request('course_id') : null)) == $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Dates and capacity</h2>
        <div class="row g-3">
            @foreach(['start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label {{ $field === 'start_date' ? 'required' : '' }}" for="{{ $field }}">{{ $label }}</label>
                    <input class="form-control" type="date" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $batch?->$field?->format('Y-m-d')) }}" @required($field === 'start_date')>
                </div>
            @endforeach
            <div class="col-md-6">
                <label class="form-label required" for="max_students">Capacity</label>
                <input class="form-control" id="max_students" name="max_students" type="number" min="1" max="500" value="{{ old('max_students', $batch?->max_students ?? 20) }}" required>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Class delivery</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="meeting_platform">Delivery</label>
                <select class="form-select" id="meeting_platform" name="meeting_platform">
                    @foreach(['in_person' => 'In person', 'zoom' => 'Zoom', 'google_meet' => 'Google Meet'] as $platform => $label)
                        <option value="{{ $platform }}" @selected(old('meeting_platform', $batch?->meeting_platform ?? 'in_person') === $platform)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="meeting_link">Meeting link (optional)</label>
                <input class="form-control" type="url" id="meeting_link" name="meeting_link" placeholder="https://" value="{{ old('meeting_link', $batch?->meeting_link) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $batch?->notes) }}</textarea>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('batches.index') }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
