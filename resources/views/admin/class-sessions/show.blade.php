@extends('layouts.admin')
@section('title', $classSession->topic)
@section('content')
@php
    $records = $classSession->attendances->sortBy(fn ($record) => $record->student?->name);
    $counts = $records->countBy('status');
    $meeting = $classSession->meeting_link ?: $classSession->batch->meeting_link;
    $cancelled = $classSession->status === 'cancelled';
@endphp
<x-show.hero icon="fa-chalkboard" :eyebrow="'Class session · '.$classSession->batch->name" :title="$classSession->topic" :status="$classSession->status">
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
        <a class="btn btn-outline-primary" href="{{ route('class-sessions.edit', $classSession) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>
    @endunless
    <a class="btn btn-outline-secondary" href="{{ route('batches.show', $classSession->batch) }}"><i class="fas fa-layer-group me-1" aria-hidden="true"></i>Batch</a>
</x-show.hero>

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
        @unless($cancelled)
            <details class="panel d-print-none" style="padding:16px 22px">
                <summary class="fw-semibold" style="cursor:pointer">Cancel this session</summary>
                <form class="mt-3" method="post" action="{{ route('class-sessions.destroy', $classSession) }}" data-confirm="Cancel this session? Attendance history will be preserved.">
                    @csrf @method('DELETE')
                    <p class="form-text">Attendance already marked stays on record.</p>
                    <button class="btn btn-outline-danger">Cancel session</button>
                </form>
            </details>
        @endunless
    </div>
</div>
@endsection
