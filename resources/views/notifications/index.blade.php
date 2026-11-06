@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-bell me-2"></i>Notifications</h1>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        @if(auth()->user()->isAdmin())
            <a href="{{ route('notifications.announce') }}" class="btn btn-primary"><i class="fas fa-bullhorn me-1"></i>New Announcement</a>
        @endif
        <form method="POST" action="{{ route('notifications.readAll') }}" onsubmit="event.preventDefault(); fetch(this.action,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','X-Requested-With':'XMLHttpRequest'}}).then(()=>location.reload());">
            <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-check-double me-1"></i>Mark all read</button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
            @php
                $d = $n->data; $level = $d['level'] ?? 'info';
                $color = ['info'=>'primary','success'=>'success','warning'=>'warning','danger'=>'danger'][$level] ?? 'secondary';
            @endphp
            <div class="list-group-item d-flex gap-3 align-items-start {{ $n->read_at ? '' : 'bg-light' }}">
                <span class="fs-5 text-{{ $color }}"><i class="fas {{ $d['icon'] ?? 'fa-bell' }}"></i></span>
                <div class="flex-grow-1">
                    <div class="fw-semibold">
                        @if(!empty($d['url']))<a href="{{ $d['url'] }}" class="text-decoration-none text-body stretched-link">{{ $d['title'] ?? 'Notification' }}</a>
                        @else{{ $d['title'] ?? 'Notification' }}@endif
                        @unless($n->read_at)<span class="badge bg-{{ $color }} ms-1" style="font-size:.6rem;">new</span>@endunless
                    </div>
                    <div class="text-muted small">{{ $d['body'] ?? '' }}</div>
                    <div class="text-muted" style="font-size:.75rem;">{{ $n->created_at->diffForHumans() }} &middot; {{ $n->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <form method="POST" action="{{ route('notifications.destroy', $n->id) }}" class="position-relative" style="z-index:2;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove"><i class="fas fa-times"></i></button>
                </form>
            </div>
        @empty
            <div class="list-group-item text-center text-muted py-5"><i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>No notifications yet.</div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
