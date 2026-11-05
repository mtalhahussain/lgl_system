@extends('layouts.admin')
@section('title', $batch->name)
@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $fill = $batch->max_students ? min(100, round($students->count() / $batch->max_students * 100)) : 0;
    $platform = ['zoom' => 'Zoom', 'google_meet' => 'Google Meet', 'in_person' => 'In person'][$batch->meeting_platform] ?? 'In person';
@endphp
<x-show.hero icon="fa-layer-group" :eyebrow="'Batch · '.$batch->course->name.' ('.$batch->course->level.')'" :title="$batch->name" :status="$batch->status">
    <x-slot:meta>
        <span><i class="fas fa-calendar" aria-hidden="true"></i>{{ $batch->start_date->format('d M Y') }} – {{ $batch->end_date?->format('d M Y') ?? 'open' }}</span>
        @if($batch->teacher)<span><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i>{{ $batch->teacher->name }}</span>@endif
        <span><i class="fas {{ $batch->meeting_platform === 'in_person' ? 'fa-building' : 'fa-video' }}" aria-hidden="true"></i>{{ $platform }}</span>
    </x-slot:meta>
    @if($batch->meeting_link)
        <a class="btn btn-success" href="{{ $batch->meeting_link }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-video me-1" aria-hidden="true"></i>Join class</a>
    @endif
    <a class="btn btn-primary" href="{{ route('attendance.create', ['batch_id' => $batch->id]) }}"><i class="fas fa-clipboard-check me-1" aria-hidden="true"></i>Mark attendance</a>
    @if($isAdmin)
        <a class="btn btn-outline-primary" href="{{ route('batches.edit', $batch) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>
        <a class="btn btn-outline-primary" href="{{ route('fees.batch', $batch) }}"><i class="fas fa-money-bill me-1" aria-hidden="true"></i>Fees</a>
    @endif
</x-show.hero>

<x-list.kpis :items="[
    ['label' => $batch->status === 'completed' ? 'Students' : 'Active students', 'value' => $students->count().' / '.$batch->max_students, 'icon' => 'fa-user-graduate', 'note' => $fill.'% of capacity'],
    ['label' => 'Sessions held', 'value' => $held, 'icon' => 'fa-check-double'],
    ['label' => 'Next session', 'value' => $upcoming->first()?->session_date->format('d M') ?? '—', 'icon' => 'fa-clock', 'note' => $upcoming->first()?->topic],
]" />

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3">
    <section class="panel tw:xl:col-span-2" aria-labelledby="roster-heading">
        <div class="panel-heading"><h2 id="roster-heading">Students</h2><span class="page-subtitle">{{ $students->count() }} {{ $batch->status === 'completed' ? 'enrolled' : 'active' }}</span></div>
        <div class="table-responsive"><table class="table list-table">
            <thead><tr><th scope="col">Student</th><th scope="col">Enrolled</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead>
            <tbody>
            @forelse($students as $row)
                <tr>
                    <td><div class="person-cell"><span class="user-avatar" aria-hidden="true">{{ mb_substr($row->student->name, 0, 1) }}</span><strong>{{ $row->student->name }}</strong></div></td>
                    <td>{{ $row->enrollment_date?->format('d M Y') }}</td>
                    <td><x-list.status :value="$row->status" /></td>
                    <td class="text-end"><a class="btn btn-icon" href="{{ route('students.show', $row->student) }}" aria-label="Open {{ $row->student->name }}"><i class="fas fa-arrow-right" aria-hidden="true"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty-state">No enrolled students.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="panel" aria-labelledby="upcoming-heading">
        <div class="panel-heading"><h2 id="upcoming-heading">Upcoming sessions</h2><a href="{{ route('class-sessions.index', ['batch_id' => $batch->id]) }}" style="font-size:11px;color:var(--accent)">All <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
        @php $sched = $batch->orderedSchedules(); @endphp
        @if($sched->isNotEmpty())
            <div class="px-4 pt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="small text-uppercase text-muted">Weekly schedule</strong>
                    <span class="badge bg-primary">{{ $batch->weeklyHours() }} h / week</span>
                </div>
                <ul class="profile-facts mb-0">
                    @foreach($sched as $s)
                        <li><i class="fas fa-clock" aria-hidden="true"></i><div><small>{{ $s->weekdayLabel() }}</small>
                            {{ \Illuminate\Support\Carbon::parse($s->getRawOriginal('start_time'))->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($s->getRawOriginal('end_time'))->format('H:i') }}</div></li>
                    @endforeach
                </ul>
            </div>
        @endif
        <ul class="profile-facts">
            @forelse($upcoming as $session)
                <li><i class="fas fa-calendar-day" aria-hidden="true"></i><div><small>{{ $session->session_date->format('D, d M') }} · {{ $session->start_time?->format('H:i') }}–{{ $session->end_time?->format('H:i') }}</small>
                    <a href="{{ route('class-sessions.show', $session) }}">{{ $session->topic }}</a></div></li>
            @empty
                <li class="page-subtitle">No upcoming sessions scheduled.</li>
            @endforelse
        </ul>
        @if(in_array($batch->status, ['upcoming', 'ongoing'], true) && (auth()->user()->isAdmin() || $batch->teacher_id === auth()->id()))
            <details class="px-4 pb-4" @if($errors->hasAny(['days', 'start_time', 'end_time', 'from', 'to', 'topic', 'schedule'])) open @endif>
                <summary class="fw-semibold">Generate sessions</summary>
                @if($batch->schedules->isNotEmpty())
                    <form method="post" action="{{ route('class-sessions.generate-from-schedule', $batch) }}" class="row g-2 mt-1 mb-2">
                        @csrf
                        <div class="col-6"><label class="form-label" for="sch_from">From</label><input class="form-control" type="date" id="sch_from" name="from" min="{{ today()->toDateString() }}" value="{{ max(today(), $batch->start_date)->toDateString() }}"></div>
                        <div class="col-6"><label class="form-label" for="sch_to">To</label><input class="form-control" type="date" id="sch_to" name="to" value="{{ $batch->end_date?->toDateString() }}"></div>
                        <div class="col-12"><button class="btn btn-success btn-sm"><i class="fas fa-calendar-plus me-1" aria-hidden="true"></i>Generate from weekly schedule</button></div>
                        <p class="form-text mb-0">Uses the batch's weekly days and times above. Existing sessions are skipped.</p>
                    </form>
                    <hr class="my-2">
                    <p class="form-text mb-1 fw-semibold">Or pick days manually:</p>
                @endif
                <form method="post" action="{{ route('class-sessions.generate', $batch) }}" class="row g-2 mt-1">
                    @csrf
                    <fieldset class="col-12">
                        <legend class="form-label mb-1">Class days</legend>
                        @foreach(\App\Http\Controllers\ClassSessionController::WEEKDAYS as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="day_{{ $value }}" name="days[]" value="{{ $value }}" @checked(in_array($value, old('days', []), true))>
                                <label class="form-check-label" for="day_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <div class="col-6"><label class="form-label" for="gen_start">Start time</label><input class="form-control" type="time" id="gen_start" name="start_time" value="{{ old('start_time') }}" required></div>
                    <div class="col-6"><label class="form-label" for="gen_end">End time</label><input class="form-control" type="time" id="gen_end" name="end_time" value="{{ old('end_time') }}" required></div>
                    <div class="col-6"><label class="form-label" for="gen_from">From</label><input class="form-control" type="date" id="gen_from" name="from" min="{{ today()->toDateString() }}" value="{{ old('from', max(today(), $batch->start_date)->toDateString()) }}" required></div>
                    <div class="col-6"><label class="form-label" for="gen_to">To</label><input class="form-control" type="date" id="gen_to" name="to" value="{{ old('to', $batch->end_date?->toDateString()) }}" required></div>
                    <div class="col-12"><label class="form-label" for="gen_topic">Topic</label><input class="form-control" id="gen_topic" name="topic" maxlength="255" value="{{ old('topic', 'Class') }}" required></div>
                    <div class="col-12"><button class="btn btn-primary btn-sm">Create sessions</button></div>
                    <p class="form-text mb-0">One session per chosen day inside the batch dates. Dates that already have a session are skipped. Cancel holidays from the session page.</p>
                </form>
            </details>
        @endif
    </section>
</div>
@endsection
