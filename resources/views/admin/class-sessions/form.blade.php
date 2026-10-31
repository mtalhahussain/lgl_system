@extends('layouts.admin')
@section('title', $classSession ? 'Edit Session' : 'Add Session')
@section('content')
<h1>{{ $classSession ? 'Edit Session' : 'Add Session' }}</h1>
<form method="post" class="card card-body" action="{{ $formAction ?? ($classSession ? route('class-sessions.update', $classSession) : route('class-sessions.store')) }}">
    @csrf
    @if($classSession) @method('PUT') @endif
    <label class="form-label" for="batch_id">Batch</label>
    <select class="form-select mb-3" id="batch_id" name="batch_id" required>
        @foreach($batches as $batch)
            <option value="{{ $batch->id }}" @selected(old('batch_id', $classSession?->batch_id ?? ($selectedBatch->id ?? request('batch_id'))) == $batch->id)>{{ $batch->name }}</option>
        @endforeach
    </select>
    @foreach(['session_date' => ['Date', 'date'], 'start_time' => ['Start time', 'time'], 'end_time' => ['End time', 'time'], 'topic' => ['Topic', 'text']] as $field => [$label, $type])
        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
        <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $classSession ? ($field === 'session_date' ? $classSession->session_date->format('Y-m-d') : (in_array($field, ['start_time', 'end_time']) ? $classSession->$field->format('H:i') : $classSession->$field)) : '') }}" required>
    @endforeach
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control mb-3" id="description" name="description">{{ old('description', $classSession?->description) }}</textarea>
    <label class="form-label" for="meeting_link">Meeting link (optional)</label>
    <input class="form-control mb-3" id="meeting_link" name="meeting_link" type="url" value="{{ old('meeting_link', $classSession?->meeting_link) }}">
    <div><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="{{ route('class-sessions.index') }}">Cancel</a></div>
</form>
@endsection
