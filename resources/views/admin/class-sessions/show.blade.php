@extends('layouts.admin')
@section('title', 'Class Session')
@section('content')
<h1>{{ $classSession->topic }}</h1>
<p>{{ $classSession->batch->name }} — {{ $classSession->session_date->format('d M Y') }}, {{ $classSession->start_time->format('H:i') }}–{{ $classSession->end_time->format('H:i') }}</p>
<p>Status: {{ $classSession->status }}</p>
<p>{{ $classSession->description }}</p>
<a class="btn btn-primary" href="{{ route('class-sessions.attendance', $classSession) }}">Mark attendance</a>
<a class="btn btn-outline-primary" href="{{ route('class-sessions.edit', $classSession) }}">Edit session</a>
@if($classSession->meeting_link)
    <a class="btn btn-outline-primary" href="{{ $classSession->meeting_link }}" rel="noopener noreferrer">Meeting</a>
@endif
<form class="d-inline" method="post" action="{{ route('class-sessions.destroy', $classSession) }}" onsubmit="return confirm('Cancel this session? Attendance history will be preserved.');">
    @csrf @method('DELETE') <button class="btn btn-outline-danger">Cancel session</button>
</form>
<h2 class="mt-4">Attendance</h2>
<ul class="list-group">
    @forelse($classSession->attendances as $record)
        <li class="list-group-item">{{ $record->student->name }} — {{ $record->status }}</li>
    @empty
        <li class="list-group-item">No attendance marked.</li>
    @endforelse
</ul>
@endsection
