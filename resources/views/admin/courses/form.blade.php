@extends('layouts.admin')
@section('title', $course ? 'Edit Course' : 'Add Course')
@section('content')
<h1>{{ $course ? 'Edit Course' : 'Add Course' }}</h1>
<form class="card card-body" method="post" action="{{ $course ? route('courses.update', $course) : route('courses.store') }}">
    @csrf
    @if($course) @method('PUT') @endif
    <label for="name" class="form-label">Name</label>
    <input class="form-control mb-3" id="name" name="name" value="{{ old('name', $course?->name) }}" required>
    <label for="level" class="form-label">Level</label>
    <select class="form-select mb-3" id="level" name="level">
        @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $level)
            <option @selected(old('level', $course?->level) === $level)>{{ $level }}</option>
        @endforeach
    </select>
    <label for="description" class="form-label">Description</label>
    <textarea class="form-control mb-3" id="description" name="description" required>{{ old('description', $course?->description) }}</textarea>
    @foreach(['total_fee' => ['Default fee', 0, '0.01'], 'duration_weeks' => ['Duration in weeks', 12, '1'], 'sessions_per_week' => ['Sessions per week', 2, '1'], 'session_duration_minutes' => ['Session duration in minutes', 90, '1']] as $field => [$label, $default, $step])
        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
        <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="number" min="0" step="{{ $step }}" value="{{ old($field, $course?->$field ?? $default) }}" required>
    @endforeach
    <p>Fee changes apply to future enrollments. Existing agreements remain unchanged.</p>
    <div><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="{{ route('courses.index') }}">Cancel</a></div>
</form>
@endsection
