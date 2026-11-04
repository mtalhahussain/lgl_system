@extends('layouts.admin')
@section('title', 'Teacher Dashboard')
@section('content')
@php $weeklyHours = $batches->sum(fn ($b) => $b->weeklyHours()); @endphp
<div class="page-heading"><div><div class="eyebrow">YOUR WORKSPACE</div><h1>Teacher Dashboard</h1><p class="page-subtitle">Your classes, students and teaching day, organized.</p></div><span class="status-pill">{{ now()->format('d M Y') }}</span></div>
<x-list.kpis :items="[
    ['label' => 'Active batches', 'value' => $batches->count(), 'icon' => 'fa-layer-group'],
    ['label' => 'Teaching load', 'value' => rtrim(rtrim(number_format($weeklyHours, 1), '0'), '.').' h / week', 'icon' => 'fa-clock', 'note' => 'Planned from batch schedules'],
    ['label' => 'Upcoming sessions', 'value' => $sessions->count(), 'icon' => 'fa-calendar-day'],
]" />
<section class="panel tw:mb-6"><div class="panel-heading"><h2>Assigned Batches</h2></div><ul class="list-group list-group-flush">
    @forelse($batches as $batch)
        @php $sched = $batch->orderedSchedules(); @endphp
        <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div><a href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a> — {{ $batch->course->name }}; {{ $batch->active_enrollments_count }} enrolled
                    @if($sched->isNotEmpty())
                        <div class="small text-muted">{{ $sched->map(fn ($s) => $s->weekdayLabel().' '.\Illuminate\Support\Carbon::parse($s->getRawOriginal('start_time'))->format('H:i').'–'.\Illuminate\Support\Carbon::parse($s->getRawOriginal('end_time'))->format('H:i'))->implode(' · ') }}</div>
                    @else
                        <div class="small text-muted fst-italic">No weekly schedule set.</div>
                    @endif
                </div>
                @if($batch->weeklyHours() > 0)<span class="badge bg-primary align-self-center">{{ $batch->weeklyHours() }} h/wk</span>@endif
            </div>
        </li>
    @empty<li class="list-group-item">No assigned batches.</li>@endforelse
</ul></section>
<section class="panel tw:mb-6"><div class="panel-heading"><h2>Upcoming Sessions</h2></div><ul class="list-group list-group-flush">
    @forelse($sessions as $session)<li class="list-group-item"><a href="{{ route('class-sessions.show', $session) }}">{{ $session->batch->name }} — {{ $session->topic }}</a> {{ $session->session_date->format('d M Y') }} {{ $session->start_time->format('H:i') }}</li>
    @empty<li class="list-group-item">No upcoming sessions.</li>@endforelse
</ul></section>
<a class="btn btn-primary" href="{{ route('attendance.index') }}">Review attendance</a>
@endsection
