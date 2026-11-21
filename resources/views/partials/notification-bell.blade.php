{{-- Real-time notification bell. Drop into any authenticated topbar. --}}
<div class="nav-item dropdown" id="notifBell"
     data-read-base="{{ url('/notifications') }}"
     data-feed="{{ route('notifications.feed') }}"
     data-readall="{{ route('notifications.readAll') }}"
     data-index="{{ route('notifications.index') }}"
     data-user="{{ auth()->id() }}"
     data-csrf="{{ csrf_token() }}">
    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none fs-06rem" id="notifCount">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow notif-menu">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <strong class="small text-uppercase text-muted">Notifications</strong>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="notifReadAll">Mark all read</button>
        </div>
        <div id="notifList" class="notif-list">
            <div class="text-center text-muted small py-4">Loading…</div>
        </div>
        <a href="{{ route('notifications.index') }}" class="dropdown-item text-center small border-top py-2">View all</a>
    </div>
</div>

@once
@push('scripts')
<template data-page-module="partials-notification-bell"></template>
@endpush
@endonce
