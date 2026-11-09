@extends('layouts.admin')
@section('title', 'Class Sessions')
@section('content')
<x-list.header title="Class Sessions" eyebrow="Timetable" subtitle="Scheduled classes, topics and attendance">
    <a class="btn btn-primary" href="{{ route('class-sessions.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Extra / makeup class</a>
    <a class="btn btn-outline-primary" href="{{ route('class-sessions.index', ['from' => today()->startOfWeek()->toDateString(), 'to' => today()->endOfWeek()->toDateString()]) }}">This week's teaching</a>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search topic or batch…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="date" :default="$defaultSort">Date</x-list.th>
                <th scope="col">Batch</th>
                @if(auth()->user()->isAdmin())<th scope="col">Teacher</th>@endif
                <x-list.th sort="topic" :default="$defaultSort">Topic</x-list.th>
                <th scope="col">Status</th>
                <th scope="col">Teaching / coverage</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td><span class="cell-main">{{ $session->session_date->format('d M Y') }}</span><span class="cell-sub">{{ $session->session_date->format('l') }} · {{ $session->start_time?->format('h:i A') }}</span></td>
                    <td>{{ $session->batch->name }}<span class="cell-sub">{{ $session->batch->course?->name }}@if($session->batch->course?->level) ({{ $session->batch->course?->level }})@endif</span></td>
                    @if(auth()->user()->isAdmin())<td>{{ $session->batch->teacher?->name ?? 'Unassigned' }}</td>@endif
                    <td><a class="cell-main" href="{{ route('class-sessions.show', $session) }}">{{ $session->topic ?: 'Untitled session' }}</a></td>
                    <td><x-list.status :value="$session->status" /></td>
                    <td><span class="cell-main">{{ $session->teachingLabel() }}</span>
                        @if($session->makeupFor)<a class="cell-sub" href="{{ route('class-sessions.show', $session->makeupFor) }}">Makeup for {{ $session->makeupFor->session_date->format('d M Y') }}</a>@endif
                    </td>
                    <td class="text-end">
                        <x-list.row-menu :label="$session->topic ?: $session->session_date->format('d M Y')">
                            <li><a class="dropdown-item" href="{{ route('class-sessions.show', $session) }}"><i class="fas fa-eye me-2" aria-hidden="true"></i>View</a></li>
                            @if($session->status !== 'cancelled' && $session->session_date->lte(today()))
                                <li><a class="dropdown-item" href="{{ route('class-sessions.attendance', $session) }}"><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Mark attendance</a></li>
                            @endif
                            @if(!$session->teaching_status && !$session->makeup_for_id)<li><a class="dropdown-item" href="{{ route('class-sessions.edit', $session) }}"><i class="fas fa-pen me-2" aria-hidden="true"></i>Edit</a></li>@endif
                            @if($session->meeting_link)
                                <li><a class="dropdown-item" href="{{ $session->meeting_link }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-video me-2" aria-hidden="true"></i>Open meeting</a></li>
                            @endif
                        </x-list.row-menu>
                    </td>
                </tr>
            @empty
                <x-list.empty :colspan="auth()->user()->isAdmin() ? 7 : 6" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$sessions" label="sessions" />
</div>
@endsection
