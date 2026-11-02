@extends('layouts.admin')
@section('title', $course ? 'Edit Course' : 'Add Course')
@section('content')
<x-list.header :title="$course ? 'Edit Course' : 'Add Course'" eyebrow="Academics" :subtitle="$course ? $course->name.' · '.$course->level : 'Catalogue entry and default fee'" />
<form class="panel form-panel" method="post" action="{{ $course ? route('courses.update', $course) : route('courses.store') }}">
    @csrf
    @if($course) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Course</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label required">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $course?->name) }}" required>
            </div>
            <div class="col-md-6">
                <label for="level" class="form-label required">Level</label>
                <select class="form-select" id="level" name="level">
                    @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $level)
                        <option @selected(old('level', $course?->level) === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label for="description" class="form-label required">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required>{{ old('description', $course?->description) }}</textarea>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Fee and schedule</h2>
        <p class="form-section-note">Fee changes apply to future enrollments. Existing agreements remain unchanged.</p>
        <div class="row g-3">
            @foreach(['total_fee' => ['Default fee', 0, '0.01'], 'duration_weeks' => ['Duration in weeks', 12, '1'], 'sessions_per_week' => ['Sessions per week', 2, '1'], 'session_duration_minutes' => ['Session duration in minutes', 90, '1']] as $field => [$label, $default, $step])
                <div class="col-md-6">
                    <label for="{{ $field }}" class="form-label required">{{ $label }}</label>
                    <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="number" min="0" step="{{ $step }}" value="{{ old($field, $course?->$field ?? $default) }}" required>
                </div>
            @endforeach
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('courses.index') }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
@endsection
