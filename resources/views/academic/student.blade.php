@extends('layouts.admin')
@section('title', 'Student Enrollment')
@section('content')
<h1>{{ $student->name }}</h1>
<h2>Assigned batch enrollments</h2>
<ul class="list-group">
    @foreach($enrollments as $enrollment)
        <li class="list-group-item">
            <a href="{{ route('batches.show', $enrollment->batch) }}">{{ $enrollment->batch->name }}</a>
            — {{ $enrollment->status }}
        </li>
    @endforeach
</ul>
<a class="btn btn-outline-primary mt-3" href="{{ route('attendance.student', $student) }}">Attendance</a>
@endsection
