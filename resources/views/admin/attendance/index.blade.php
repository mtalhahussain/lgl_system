@extends('layouts.admin')
@section('title', 'Attendance')
@section('content')
<x-list.header title="Attendance" eyebrow="Students" subtitle="Mark past and current sessions; future sessions open on their date">
    <a class="btn btn-outline-primary" href="{{ route('attendance.reports') }}"><i class="fas fa-chart-bar me-1" aria-hidden="true"></i>Attendance report</a>
    <a class="btn btn-primary" href="{{ route('attendance.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>New session</a>
</x-list.header>
@include('partials.attendance-tabs')
<x-list.kpis :items="[
    ['label' => 'Present', 'value' => $stats['present'], 'icon' => 'fa-user-check'],
    ['label' => 'Late', 'value' => $stats['late'], 'icon' => 'fa-clock'],
    ['label' => 'Absent', 'value' => $stats['absent'], 'icon' => 'fa-user-times'],
    ['label' => 'Attendance rate', 'value' => $stats['average_attendance'] === null ? '—' : $stats['average_attendance'].'%', 'icon' => 'fa-percent', 'note' => '(present + late) ÷ marked, excused excluded'],
]" />
<p class="text-muted small">Each student's attendance counts once per class. Excused: {{ $stats['excused'] }}. Unmarked attendance is not counted as absent.</p>
@if($stats['unmarked_sessions'] > 0)
    <div class="alert alert-warning d-flex align-items-baseline gap-2" role="status">
        <i class="fas fa-pen-to-square flex-shrink-0" aria-hidden="true"></i>
        <span>{{ $stats['unmarked_sessions'] }} {{ $stats['unmarked_sessions'] === 1 ? 'class needs' : 'classes need' }} attendance marking. <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['marking' => 'unmarked'])) }}">View pending classes</a></span>
    </div>
@endif
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search topic or batch…" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="date" default="-date">Date</x-list.th>
                <th scope="col">Batch</th><th scope="col">Topic</th><th scope="col">Status</th><th scope="col">Records</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($sessions as $session)
                @php $markable = $session->status !== 'cancelled' && $session->session_date->lte(today()); @endphp
                <tr>
                    <td><span class="cell-main">{{ $session->session_date->format('d M Y') }}</span><span class="cell-sub">{{ $session->start_time?->format('h:i A') }}</span></td>
                    <td>{{ $session->batch->name }}<span class="cell-sub">{{ $session->batch->course?->name }}</span></td>
                    <td>{{ $session->topic ?: '—' }}</td>
                    <td><x-list.status :value="$session->status" /></td>
                    <td>@if($session->attendances_count)<x-list.status value="active" :label="$session->attendances_count.' marked'" />@elseif($markable)<x-list.status value="due" label="Needs marking" />@else — @endif</td>
                    <td class="text-end">
                        @if($markable)
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('class-sessions.attendance', $session) }}">{{ $session->attendances_count ? 'Review' : 'Mark' }}</a>
                        @else
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('class-sessions.show', $session) }}">View</a>
                        @endif
                    </td>
                </tr>
            @empty
                <x-list.empty colspan="6" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$sessions" label="sessions" />
</div>
@endsection
