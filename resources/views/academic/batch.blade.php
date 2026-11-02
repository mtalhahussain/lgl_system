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
        <div class="panel-heading"><h2 id="upcoming-heading">Upcoming sessions</h2><a href="{{ route('class-sessions.index', ['batch_id' => $batch->id]) }}" style="font-size:11px;color:#7956df">All <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
        <ul class="profile-facts">
            @forelse($upcoming as $session)
                <li><i class="fas fa-calendar-day" aria-hidden="true"></i><div><small>{{ $session->session_date->format('D, d M') }} · {{ $session->start_time?->format('H:i') }}–{{ $session->end_time?->format('H:i') }}</small>
                    <a href="{{ route('class-sessions.show', $session) }}">{{ $session->topic }}</a></div></li>
            @empty
                <li class="page-subtitle">No upcoming sessions scheduled.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
