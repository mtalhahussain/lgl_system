@extends('layouts.admin')
@section('title', $classSession ? 'Edit Session' : 'Add Session')
@section('content')
<x-list.header :title="$classSession ? 'Edit Session' : 'Add Session'" eyebrow="Timetable" subtitle="Schedule a class for one batch" />
<form method="post" class="panel form-panel" action="{{ $formAction ?? ($classSession ? route('class-sessions.update', $classSession) : route('class-sessions.store')) }}">
    @csrf
    @if($classSession) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Session</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="batch_id">Batch</label>
                <select class="form-select" id="batch_id" name="batch_id" required>
                    @foreach($batches as $batch)
                        <option value="{{ $batch->id }}" @selected(old('batch_id', $classSession?->batch_id ?? ($selectedBatch->id ?? request('batch_id'))) == $batch->id)>{{ $batch->name }}</option>
                    @endforeach
                </select>
            </div>
            @foreach(['topic' => ['Topic', 'text'], 'session_date' => ['Date', 'date'], 'start_time' => ['Start time', 'time'], 'end_time' => ['End time', 'time']] as $field => [$label, $type])
                <div class="col-md-6">
                    <label class="form-label required" for="{{ $field }}">{{ $label }}</label>
                    <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $classSession ? ($field === 'session_date' ? $classSession->session_date->format('Y-m-d') : (in_array($field, ['start_time', 'end_time']) ? $classSession->$field->format('H:i') : $classSession->$field)) : '') }}" required>
                </div>
            @endforeach
            <div class="col-md-6">
                <label class="form-label" for="meeting_link">Meeting link (optional)</label>
                <input class="form-control" id="meeting_link" name="meeting_link" type="url" placeholder="https://" value="{{ old('meeting_link', $classSession?->meeting_link) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $classSession?->description) }}</textarea>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('class-sessions.index') }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
