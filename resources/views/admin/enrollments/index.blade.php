@extends('layouts.admin')
@section('title', 'Enrollments')
@section('content')
<h1>Enrollments</h1>
<a class="btn btn-primary mb-3" href="{{ route('enrollments.create') }}">Enroll student</a>
<form method="get" class="mb-3"><label for="search">Student name or email</label><input id="search" name="search" value="{{ request('search') }}"><button class="btn btn-outline-primary">Search</button></form>
<table class="table"><thead><tr><th>Student</th><th>Course / batch</th><th>Status</th><th>Outstanding</th></tr></thead><tbody>
    @foreach($enrollments as $enrollment)
        <tr><td><a href="{{ route('enrollments.show', $enrollment) }}">{{ $enrollment->student->name }}</a></td><td>{{ $enrollment->batch->course->name }} / {{ $enrollment->batch->name }}</td><td>{{ $enrollment->status }}</td><td>{{ currency_format($enrollment->remaining_fee) }}</td></tr>
    @endforeach
</tbody></table>
{{ $enrollments->links() }}
@endsection