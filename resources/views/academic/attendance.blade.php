@extends('layouts.admin')
@section('title', 'Assigned Attendance')
@section('content')
<h1>{{ auth()->user()->isStudent() ? 'My Attendance' : 'Attendance' }}</h1>
@if(isset($stats))
    <p>Attendance: {{ $stats['attendance_rate'] === null ? 'No marked classes' : $stats['attendance_rate'].'%' }}. Excused: {{ $stats['excused'] }}.</p>
@endif
<form method="get" class="mb-3 d-print-none">
    <label for="start_date">From</label><input type="date" id="start_date" name="start_date" value="{{ request('start_date') }}">
    <label for="end_date">To</label><input type="date" id="end_date" name="end_date" value="{{ request('end_date') }}">
    <button class="btn btn-outline-primary">Apply</button>
</form>
<div class="table-responsive">
    <table class="table">
        <thead><tr><th>Student</th><th>Batch</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($records as $record)
            <tr><td>{{ $record->student->name }}</td><td>{{ $record->classSession->batch->name }}</td><td>{{ $record->classSession->session_date->format('d M Y') }}</td><td>{{ $record->status }}</td></tr>
        @empty
            <tr><td colspan="4">No attendance records.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $records->links() }}
@endsection
