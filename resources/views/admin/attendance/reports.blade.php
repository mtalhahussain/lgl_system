@extends('layouts.admin')
@section('title', 'Attendance Report')
@section('content')
<h1>Attendance Report</h1>
<form method="get" class="mb-3">
    <label for="start_date">From</label><input id="start_date" name="start_date" type="date" value="{{ \Carbon\Carbon::parse($startDate)->format('Y-m-d') }}">
    <label for="end_date">To</label><input id="end_date" name="end_date" type="date" value="{{ \Carbon\Carbon::parse($endDate)->format('Y-m-d') }}">
    <button class="btn btn-primary">Apply</button>
</form>
<table class="table"><thead><tr><th>Student</th><th>Present</th><th>Late</th><th>Absent</th><th>Excused</th></tr></thead><tbody>
    @foreach($studentAttendance as $row)
        <tr><td>{{ $row['student']->name }}</td><td>{{ $row['present'] }}</td><td>{{ $row['late'] }}</td><td>{{ $row['absent'] }}</td><td>{{ $row['excused'] }}</td></tr>
    @endforeach
</tbody></table>
@endsection
