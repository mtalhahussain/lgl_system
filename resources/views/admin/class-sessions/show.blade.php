@extends('layouts.admin')
@section('title', $classSession->topic)
@section('content')
@php
    $records = $classSession->attendances->sortBy(fn ($record) => $record->student?->name);
    $counts = $records->countBy('status');
    $meeting = $classSession->meeting_link ?: $classSession->batch->meeting_link;
    $cancelled = $classSession->status === 'cancelled';
@endphp
<x-show.hero icon="fa-chalkboard" :eyebrow="'Class session · '.$classSession->batch->name" :title="$classSession->topic" :status="$classSession->teaching_status === 'missed' ? 'missed' : $classSession->status">
    <x-slot:meta>
        <span><i class="fas fa-calendar" aria-hidden="true"></i>{{ $classSession->session_date->format('D, d M Y') }}</span>
        <span><i class="fas fa-clock" aria-hidden="true"></i>{{ $classSession->start_time?->format('H:i') }}–{{ $classSession->end_time?->format('H:i') }}</span>
        @if($classSession->batch->teacher)<span><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i>{{ $classSession->batch->teacher->name }}</span>@endif
    </x-slot:meta>
    @if($meeting && ! $cancelled)
        <a class="btn btn-success" href="{{ $meeting }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-video me-1" aria-hidden="true"></i>Join class</a>
    @endif
    @unless($cancelled)
        <a class="btn btn-primary" href="{{ route('class-sessions.attendance', $classSession) }}"><i class="fas fa-clipboard-check me-1" aria-hidden="true"></i>Mark attendance</a>
        @if(!$classSession->teaching_status && !$classSession->makeup_for_id)<a class="btn btn-outline-primary" href="{{ route('class-sessions.edit', $classSession) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>@endif
    @endunless
    <a class="btn btn-outline-secondary" href="{{ route('batches.show', $classSession->batch) }}"><i class="fas fa-layer-group me-1" aria-hidden="true"></i>Batch</a>
</x-show.hero>

<section class="panel mb-4" aria-labelledby="teaching-heading">
    <div class="panel-heading"><h2 id="teaching-heading">Teaching record · {{ $classSession->teachingLabel() }}</h2></div>
    <div class="form-section">
        <p class="form-section-note">Teacher confirmation is separate from student attendance. This is a self-reported class record for online and in-person teaching.</p>
        @if($classSession->batch->meeting_platform === 'in_person')
            <p class="small text-muted">Assigned teacher's machine punches for this date: {{ $teacherPunches->count() }}.
                @if($teacherPunches->isNotEmpty())First {{ $teacherPunches->first()->punched_at->format('H:i') }}, last {{ $teacherPunches->last()->punched_at->format('H:i') }}.@endif
                Presence at the institute does not confirm this specific class was taken.
            </p>
        @endif
        @if($classSession->teaching_recorded_at)
            <p class="small">Recorded by <strong>{{ $classSession->teachingRecorder?->name ?? 'Recorded user' }}</strong> on {{ $classSession->teaching_recorded_at->format('d M Y H:i') }}.</p>
        @endif
        @if($classSession->teaching_started_at)<p class="small">Start recorded: {{ $classSession->teaching_started_at->format('d M Y H:i') }}</p>@endif
        @if($classSession->teaching_reason)<p class="small">Reason: {{ $classSession->teaching_reason }}</p>@endif
        @if($classSession->makeupFor)
            <p class="small"><a href="{{ route('class-sessions.show', $classSession->makeupFor) }}">Original missed class: {{ $classSession->makeupFor->session_date->format('d M Y') }} · {{ $classSession->makeupFor->topic }}</a></p>
            @if(!$classSession->teaching_status && !$cancelled)<p class="form-section-note">To change this makeup date, cancel this class below, then schedule a replacement from the original missed class.</p>@endif
        @endif
        @foreach($classSession->makeupClasses as $makeup)
            <p class="small"><a href="{{ route('class-sessions.show', $makeup) }}">Makeup: {{ $makeup->session_date->format('d M Y') }} {{ $makeup->start_time?->format('H:i') }}</a> · {{ $makeup->teachingLabel() }}</p>
        @endforeach
        @if(!$classSession->teaching_status && !$cancelled && $classSession->batch->status !== 'cancelled' && $classSession->session_date->lte(today()))
            <div class="d-flex flex-wrap gap-2">
                @unless($classSession->teaching_started_at)
                    <form method="post" action="{{ route('class-sessions.start', $classSession) }}">@csrf<button class="btn btn-outline-primary">Start class</button></form>
                @endunless
                <form method="post" action="{{ route('class-sessions.end', $classSession) }}" data-confirm="Confirm you took this class? Your name and confirmation time will be recorded.">@csrf<button class="btn btn-primary">Class taken</button></form>
            </div>
            @unless($records->isNotEmpty())
                <details class="mt-3"><summary class="fw-semibold">Class missed / teacher on leave</summary>
                    <form method="post" class="mt-3" action="{{ route('class-sessions.missed', $classSession) }}">
                        @csrf
                        <label class="form-label" for="missed-reason">Reason</label>
                        <textarea class="form-control mb-2" id="missed-reason" name="reason" minlength="5" maxlength="1000" required placeholder="e.g. Teacher on leave">{{ old('reason') }}</textarea>
                        <button class="btn btn-outline-danger">Record missed class</button>
                    </form>
                </details>
            @endunless
        @endif
        @if($classSession->teaching_status === 'missed' && !$classSession->makeup_for_id && !$classSession->makeupClasses->contains(fn ($class) => $class->status !== 'cancelled' || $class->teaching_status === 'taken'))
            <a class="btn btn-primary" href="{{ route('class-sessions.create', ['makeup_for_id' => $classSession->id]) }}">Schedule linked makeup</a>
        @endif
    </div>
</section>

<x-list.kpis :items="[
    ['label' => 'Present', 'value' => $counts['present'] ?? 0, 'icon' => 'fa-user-check'],
    ['label' => 'Late', 'value' => $counts['late'] ?? 0, 'icon' => 'fa-user-clock'],
    ['label' => 'Absent', 'value' => $counts['absent'] ?? 0, 'icon' => 'fa-user-xmark'],
    ['label' => 'Excused', 'value' => $counts['excused'] ?? 0, 'icon' => 'fa-user-shield'],
]" />

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3">
    <section class="panel tw:xl:col-span-2" aria-labelledby="attendance-heading">
        <div class="panel-heading"><h2 id="attendance-heading">Attendance</h2><span class="page-subtitle">{{ $records->count() }} marked</span></div>
        <div class="table-responsive"><table class="table list-table">
            <thead><tr><th scope="col">Student</th><th scope="col">Status</th><th scope="col">Notes</th></tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><div class="person-cell"><span class="user-avatar" aria-hidden="true">{{ mb_substr($record->student->name, 0, 1) }}</span><strong>{{ $record->student->name }}</strong></div></td>
                    <td><x-list.status :value="$record->status" />@if($record?->auto_marked)<span class="cell-sub"><i class="fas fa-fingerprint" aria-hidden="true"></i> Machine {{ $record->check_in_time?->format('H:i') }}</span>@endif</td>
                    <td class="cell-sub">{{ $record->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty-state">No attendance marked yet.@unless($cancelled) <a href="{{ route('class-sessions.attendance', $classSession) }}">Mark it now</a>.@endunless</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
    <div class="tw:flex tw:flex-col tw:gap-5">
        <section class="panel" aria-labelledby="details-heading">
            <div class="panel-heading"><h2 id="details-heading">Details</h2></div>
            <div style="padding:6px 22px 14px">
                <div class="finance-row"><span>Course</span><strong>{{ $classSession->batch->course?->name }}</strong></div>
                <div class="finance-row"><span>Batch</span><strong>{{ $classSession->batch->name }}</strong></div>
                @if($classSession->description)<p class="mt-3 mb-0" style="font-size:13px;white-space:pre-line">{{ $classSession->description }}</p>@endif
            </div>
        </section>
        @if(!$cancelled && !$classSession->teaching_status)
            <details class="panel d-print-none" style="padding:16px 22px">
                <summary class="fw-semibold" style="cursor:pointer">Cancel this session</summary>
                <form class="mt-3" method="post" action="{{ route('class-sessions.destroy', $classSession) }}" data-confirm="Cancel this session? Attendance history will be preserved.">
                    @csrf @method('DELETE')
                    <p class="form-text">Attendance already marked stays on record.</p>
                    <button class="btn btn-outline-danger">Cancel session</button>
                </form>
            </details>
        @endif
    </div>
</div>
@endsection
