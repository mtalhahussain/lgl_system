@extends('layouts.admin')
@section('title', 'Mark Attendance')
@section('content')
<h1>Attendance: {{ $classSession->batch->name }}</h1>
<p>{{ $classSession->session_date->format('d M Y') }}</p>
<form method="post" action="{{ route('class-sessions.attendance.store', $classSession) }}">
    @csrf
    @foreach($eligibleEnrollments as $enrollment)
        <div class="card card-body mb-2">
            <label for="attendance_{{ $enrollment->student_id }}">{{ $enrollment->student->name }}</label>
            <select class="form-select" id="attendance_{{ $enrollment->student_id }}" name="attendance[{{ $enrollment->student_id }}]">
                <option value="">Unmarked / unchanged</option>
                @foreach(['present', 'absent', 'late', 'excused'] as $status)
                    <option @selected(old('attendance.'.$enrollment->student_id, $classSession->attendances->firstWhere('student_id', $enrollment->student_id)?->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <label for="notes_{{ $enrollment->student_id }}">Notes</label>
            <input class="form-control" id="notes_{{ $enrollment->student_id }}" name="notes[{{ $enrollment->student_id }}]" value="{{ old('notes.'.$enrollment->student_id, $classSession->attendances->firstWhere('student_id', $enrollment->student_id)?->notes) }}">
        </div>
    @endforeach
    <label for="reason">Reason (required when correcting an existing entry)</label>
    <input class="form-control mb-3" id="reason" name="reason" value="{{ old('reason') }}" minlength="5" maxlength="1000">
    <button class="btn btn-primary">Save attendance</button>
</form>
@endsection
