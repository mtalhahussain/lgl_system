@extends('layouts.admin')
@section('title', 'Class Sessions')
@section('content')
<h1>Class Sessions</h1>
<a class="btn btn-primary mb-3" href="{{ route('class-sessions.create') }}">Add session</a>
<div class="table-responsive"><table class="table">
    <thead><tr><th>Batch</th><th>Date</th><th>Time</th><th>Topic</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($sessions as $session)
        <tr><td>{{ $session->batch->name }}</td><td>{{ $session->session_date->format('d M Y') }}</td><td>{{ $session->start_time->format('H:i') }}</td><td><a href="{{ route('class-sessions.show', $session) }}">{{ $session->topic }}</a></td><td>{{ $session->status }}</td></tr>
    @empty
        <tr><td colspan="5">No sessions.</td></tr>
    @endforelse
    </tbody>
</table></div>
{{ $sessions->links() }}
@endsection
