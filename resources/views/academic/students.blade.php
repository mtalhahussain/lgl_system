@extends('layouts.admin')
@section('title', 'Assigned Students')
@section('content')
<h1>Assigned Students</h1>
<form method="get" class="mb-3">
    <label for="search">Student name</label>
    <input id="search" name="search" value="{{ request('search') }}" class="form-control">
    <button class="btn btn-primary mt-2">Search</button>
</form>
<ul class="list-group mb-3">
    @forelse($students as $student)
        <li class="list-group-item"><a href="{{ route('students.show', $student) }}">{{ $student->name }}</a></li>
    @empty
        <li class="list-group-item">No assigned students.</li>
    @endforelse
</ul>
{{ $students->links() }}
@endsection
