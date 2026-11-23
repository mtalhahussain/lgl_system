@extends('layouts.admin')
@section('title', 'Student Dashboard')
@section('content')
<div class="page-heading"><div><div class="eyebrow">YOUR WORKSPACE</div><h1><i class="fas fa-tachometer-alt me-2 text-primary" aria-hidden="true"></i>Student Dashboard</h1><p class="page-subtitle">Stay connected with your classes and learning progress.</p></div><span class="status-pill">{{ now()->format('d M Y') }}</span></div>
<p class="panel panel-note">Attendance: {{ $attendance['attendance_rate'] === null ? 'No marked classes' : $attendance['attendance_rate'].'%' }}. Unmarked sessions through today: {{ $unmarked }}.</p>
<section class="panel tw:mb-6"><div class="panel-heading"><h2>My Levels</h2></div>
<div class="table-responsive"><table class="table list-table">
    <thead><tr><th scope="col">Level</th><th scope="col">Batch</th><th scope="col">Status</th><th scope="col">Outstanding</th><th scope="col">Attachments</th></tr></thead>
    <tbody>
    @forelse($enrollments as $enrollment)
        <tr>
            <td>{{ $enrollment->batch->course->name }} ({{ $enrollment->batch->course->level }})</td>
            <td>{{ $enrollment->batch->name }}
                @if($enrollment->status === 'active' && $enrollment->batch->meeting_link)<a href="{{ $enrollment->batch->meeting_link }}" rel="noopener noreferrer">Batch meeting</a>@endif
            </td>
            <td>{{ ucfirst($enrollment->status) }}</td>
            <td>{{ currency_format($enrollment->remaining_fee) }}</td>
            <td>@if(in_array($enrollment->status, ['active', 'completed']))@include('materials.level-attachments', ['course' => $enrollment->batch->course])@else<span class="text-muted">—</span>@endif</td>
        </tr>
    @empty<tr><td colspan="5">No enrollments.</td></tr>@endforelse
    </tbody>
</table></div></section>
<section class="panel tw:mb-6"><div class="panel-heading"><h2>Upcoming Sessions</h2></div><ul class="list-group list-group-flush">
    @forelse($sessions as $session)
        <li class="list-group-item">{{ $session->batch->name }} — {{ $session->topic }}, {{ $session->session_date->format('d M Y') }} {{ $session->start_time->format('h:i A') }}
            @php($state = $session->selfJoinState())
            @if($state['open'])
                <form method="POST" action="{{ route('student.session.join', $session) }}" class="tw:inline-block tw:ml-2">@csrf
                    <button type="submit" class="btn btn-sm btn-primary">Join Class</button>
                </form>
            @elseif($session->meeting_link ?: $session->batch->meeting_link)
                <a href="{{ $session->meeting_link ?: $session->batch->meeting_link }}" rel="noopener noreferrer">Join meeting</a>
                @if($session->isOnline() && $state['message'])<small class="text-muted tw:ml-2">{{ $state['message'] }}</small>@endif
            @endif
        </li>
    @empty<li class="list-group-item">No upcoming sessions.</li>@endforelse
</ul></section>
<a class="btn btn-primary" href="{{ route('fees.student', auth()->user()) }}">My fees and receipts</a>
<a class="btn btn-outline-primary" href="{{ route('student.attendance') }}">My attendance</a>
@endsection
