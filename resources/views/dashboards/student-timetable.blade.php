@extends('layouts.admin')
@section('title', 'My Timetable')
@section('content')
<div class="page-heading"><div><div class="eyebrow">YOUR WORKSPACE</div><h1>My Timetable</h1><p class="page-subtitle">Your weekly class days, times and join links.</p></div><span class="status-pill">{{ now()->format('d M Y') }}</span></div>

<section class="panel tw:mb-6" aria-labelledby="weekly-heading">
    <div class="panel-heading"><h2 id="weekly-heading">Weekly classes</h2></div>
    <ul class="list-group list-group-flush">
        @forelse($batches as $batch)
            @php
                $sched = $batch->orderedSchedules();
                $online = in_array($batch->meeting_platform, ['zoom', 'google_meet'], true);
                $platform = ['zoom' => 'Zoom', 'google_meet' => 'Google Meet', 'in_person' => 'In person'][$batch->meeting_platform] ?? 'In person';
            @endphp
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <strong>{{ $batch->course->name }} ({{ $batch->course->level }})</strong>
                        <span class="text-muted">· {{ $batch->name }}</span>
                        <div class="small text-muted">
                            <i class="fas {{ $online ? 'fa-video' : 'fa-building' }} me-1" aria-hidden="true"></i>{{ $platform }}
                            @if($batch->teacher) · {{ $batch->teacher->name }}@endif
                        </div>
                        @if($sched->isNotEmpty())
                            <ul class="profile-facts mb-0 mt-2">
                                @foreach($sched as $s)
                                    <li><i class="fas fa-clock" aria-hidden="true"></i><div><small>{{ $s->weekdayLabel() }}</small>
                                        {{ \Illuminate\Support\Carbon::parse($s->getRawOriginal('start_time'))->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($s->getRawOriginal('end_time'))->format('h:i A') }}</div></li>
                                @endforeach
                            </ul>
                        @else
                            <div class="small fst-italic text-muted mt-1">Class days not set yet — check Upcoming classes below.</div>
                        @endif
                    </div>
                    @if($online && $batch->meeting_link)
                        <a class="btn btn-success btn-sm align-self-center" href="{{ $batch->meeting_link }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-video me-1" aria-hidden="true"></i>Join class</a>
                    @endif
                </div>
            </li>
        @empty
            <li class="list-group-item text-muted">You have no active classes right now.</li>
        @endforelse
    </ul>
</section>

<section class="panel" aria-labelledby="upcoming-heading">
    <div class="panel-heading"><h2 id="upcoming-heading">Upcoming classes</h2></div>
    <ul class="list-group list-group-flush">
        @forelse($sessions as $session)
            @php
                $online = in_array($session->batch->meeting_platform, ['zoom', 'google_meet'], true);
                $link = $session->meeting_link ?: $session->batch->meeting_link;
                $hasStarted = now()->greaterThan(\Illuminate\Support\Carbon::parse($session->session_date->toDateString().' '.\Illuminate\Support\Carbon::parse($session->getRawOriginal('start_time'))->format('H:i')));
            @endphp
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>{{ $session->session_date->format('D, d M Y') }}</strong>
                    · {{ \Illuminate\Support\Carbon::parse($session->getRawOriginal('start_time'))->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($session->getRawOriginal('end_time'))->format('h:i A') }}
                    <div class="small text-muted">{{ $session->batch->name }} — {{ $session->topic }}</div>
                </div>
                @if($online && $link && ! $hasStarted)
                    <a class="btn btn-outline-success btn-sm" href="{{ $link }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-video me-1" aria-hidden="true"></i>Join</a>
                @endif
            </li>
        @empty
            <li class="list-group-item text-muted">No upcoming classes scheduled yet.</li>
        @endforelse
    </ul>
</section>
@endsection
