@extends('layouts.admin')
@section('title', $batch ? 'Edit Batch' : 'Add Batch')
@section('content')
<h1>{{ $batch ? 'Edit Batch' : 'Add Batch' }}</h1>
<form method="post" class="card card-body" action="{{ $batch ? route('batches.update', $batch) : route('batches.store') }}">
    @csrf
    @if($batch) @method('PUT') @endif
    <label class="form-label" for="name">Name</label>
    <input class="form-control mb-3" id="name" name="name" value="{{ old('name', $batch?->name) }}" required>
    @foreach(['course_id' => ['Course', $courses], 'teacher_id' => ['Teacher', $teachers]] as $field => [$label, $options])
        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
        <select class="form-select mb-3" id="{{ $field }}" name="{{ $field }}" required>
            @foreach($options as $option)
                <option value="{{ $option->id }}" @selected(old($field, $batch?->$field) == $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endforeach
    @foreach(['start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label)
        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
        <input class="form-control mb-3" type="date" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $batch?->$field?->format('Y-m-d')) }}" @required($field === 'start_date')>
    @endforeach
    <label class="form-label" for="max_students">Capacity</label>
    <input class="form-control mb-3" id="max_students" name="max_students" type="number" min="1" max="500" value="{{ old('max_students', $batch?->max_students ?? 20) }}" required>
    <label class="form-label" for="status">Status</label>
    <select class="form-select mb-3" id="status" name="status">
        @foreach(['upcoming', 'ongoing', 'completed', 'cancelled'] as $status)
            <option @selected(old('status', $batch?->status ?? 'upcoming') === $status)>{{ $status }}</option>
        @endforeach
    </select>
    <label class="form-label" for="meeting_platform">Delivery</label>
    <select class="form-select mb-3" id="meeting_platform" name="meeting_platform">
        @foreach(['in_person', 'zoom', 'google_meet'] as $platform)
            <option @selected(old('meeting_platform', $batch?->meeting_platform ?? 'in_person') === $platform)>{{ $platform }}</option>
        @endforeach
    </select>
    <label class="form-label" for="meeting_link">Meeting link (optional)</label>
    <input class="form-control mb-3" type="url" id="meeting_link" name="meeting_link" value="{{ old('meeting_link', $batch?->meeting_link) }}">
    <label class="form-label" for="notes">Notes</label>
    <textarea class="form-control mb-3" id="notes" name="notes">{{ old('notes', $batch?->notes) }}</textarea>
    <div><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="{{ route('batches.index') }}">Cancel</a></div>
</form>
@endsection
