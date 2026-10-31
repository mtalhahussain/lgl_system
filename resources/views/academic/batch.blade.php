@extends('layouts.admin')
@section('title', 'Batch Roster')
@section('content')
<h1>{{ $batch->name }}</h1>
<p>{{ $batch->course->name }} ({{ $batch->course->level }}) — {{ $batch->status }}</p>
<p>{{ $batch->start_date->format('d M Y') }} – {{ $batch->end_date?->format('d M Y') }}</p>
@if($batch->meeting_link)
    <a class="btn btn-outline-primary mb-3" href="{{ $batch->meeting_link }}" rel="noopener noreferrer">Meeting</a>
@endif
<h2>Students</h2>
@if(auth()->user()->isAdmin())
    <p><a href="{{ route('batches.edit', $batch) }}">Edit batch</a> · <a href="{{ route('fees.batch', $batch) }}">Batch fees</a></p>
@endif
<ul class="list-group">
    @forelse($students as $student)
        <li class="list-group-item"><a href="{{ route('students.show', $student) }}">{{ $student->name }}</a></li>
    @empty
        <li class="list-group-item">No active enrollments.</li>
    @endforelse
</ul>
<a class="btn btn-primary mt-3" href="{{ route('attendance.create', ['batch_id' => $batch->id]) }}">Mark attendance</a>
@endsection
