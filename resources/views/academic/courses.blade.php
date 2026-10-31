@extends('layouts.admin')
@section('title', 'Assigned Courses')
@section('content')
<h1>Assigned Courses</h1>
<ul class="list-group">
    @forelse($courses as $course)
        <li class="list-group-item"><a href="{{ route('courses.show', $course) }}">{{ $course->name }} ({{ $course->level }})</a></li>
    @empty
        <li class="list-group-item">No assigned courses.</li>
    @endforelse
</ul>
@endsection
