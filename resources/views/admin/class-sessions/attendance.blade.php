@extends('layouts.admin')
@section('title', 'Mark Attendance')
@section('content')
<h1><i class="fas fa-clipboard-check me-2 text-primary" aria-hidden="true"></i>Attendance: {{ $classSession->batch->name }}</h1>
<p>{{ $classSession->session_date->format('d M Y') }}</p>
@if($eligibleEnrollments->isEmpty())
    <div class="alert alert-info">No eligible students for this class date. Students must be enrolled in this batch on or before the class date. Ask Admin to check the batch enrollments and admission dates.</div>
@elseif(can_route('class-sessions.attendance.store'))<form method="post" action="{{ route('class-sessions.attendance.store', $classSession) }}">
    @csrf
    @foreach($eligibleEnrollments as $enrollment)
        <div class="card card-body mb-2">
            @php $existing = $classSession->attendances->firstWhere('student_id', $enrollment->student_id); @endphp
            <label for="attendance_{{ $enrollment->student_id }}">{{ $enrollment->student->name }}</label>
            @if($existing?->auto_marked)<span class="cell-sub"><i class="fas fa-fingerprint" aria-hidden="true"></i> Machine {{ $existing->check_in_time?->format('h:i A') }}</span>@endif
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
    @if($classSession->attendances->isNotEmpty())
    <label for="reason">Correction reason (only when changing saved attendance)</label>
    <input class="form-control mb-3" id="reason" name="reason" value="{{ old('reason') }}" minlength="5" maxlength="1000">
    <p class="text-muted">Leave blank for new or unchanged entries. Changes to saved status or notes require a reason of 5–1000 characters for the audit history.</p>
    @endif
    <button class="btn btn-primary">Save attendance</button>
</form>@endif
@endsection
