@extends('layouts.admin')
@section('title', 'Teacher Dashboard')
@section('content')
<div class="page-heading"><div><div class="eyebrow">YOUR WORKSPACE</div><h1>Teacher Dashboard</h1><p class="page-subtitle">Your classes, students and teaching day, organized.</p></div><span class="status-pill">{{ now()->format('d M Y') }}</span></div>
<section class="panel tw:mb-6"><div class="panel-heading"><h2>Assigned Batches</h2></div><ul class="list-group list-group-flush">
    @forelse($batches as $batch)<li class="list-group-item"><a href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a> — {{ $batch->course->name }}; {{ $batch->active_enrollments_count }} enrolled</li>
    @empty<li class="list-group-item">No assigned batches.</li>@endforelse
</ul></section>
<section class="panel tw:mb-6"><div class="panel-heading"><h2>Upcoming Sessions</h2></div><ul class="list-group list-group-flush">
    @forelse($sessions as $session)<li class="list-group-item"><a href="{{ route('class-sessions.show', $session) }}">{{ $session->batch->name }} — {{ $session->topic }}</a> {{ $session->session_date->format('d M Y') }} {{ $session->start_time->format('H:i') }}</li>
    @empty<li class="list-group-item">No upcoming sessions.</li>@endforelse
</ul></section>
<a class="btn btn-primary" href="{{ route('attendance.index') }}">Review attendance</a>
@endsection
