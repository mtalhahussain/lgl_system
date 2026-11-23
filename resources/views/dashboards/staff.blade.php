@extends('layouts.admin')
@section('title', 'Staff Dashboard')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">OVERVIEW</div><h1><i class="fas fa-tachometer-alt me-2 text-primary" aria-hidden="true"></i>Staff Dashboard</h1><p class="page-subtitle">Welcome, {{ explode(' ', auth()->user()->name)[0] }}. These are the sections Admin has given you.</p></div>
</div>
<section class="panel" aria-labelledby="staff-modules-heading">
    <div class="panel-heading"><h2 id="staff-modules-heading"><i class="fas fa-th-large me-2 text-navy" aria-hidden="true"></i>Your sections</h2></div>
    @if($modules->isEmpty())
        <p class="empty-state">No sections have been given to Staff yet. Ask Admin to grant access in Users &gt; Role permissions.</p>
    @else
    <div class="tw:grid tw:grid-cols-1 tw:gap-4 tw:p-4 tw:sm:grid-cols-2 tw:xl:grid-cols-4">
        @foreach($modules as $module => $meta)
            @php
                $routes = \App\Models\RolePermission::actionsFor('staff', $module);
                $target = in_array('view', $meta['granted'], true) ? $routes['view'][0] : $routes[$meta['granted'][0]][0];
            @endphp
            <a class="tw:rounded-lg tw:border tw:border-slate-200 tw:p-4 color-inherit" href="{{ route($target) }}">
                <div class="tw:flex tw:items-center tw:gap-2 tw:mb-2"><i class="fas {{ $meta['icon'] }} text-navy" aria-hidden="true"></i><strong>{{ $meta['label'] }}</strong></div>
                <div class="small text-muted">{{ collect($meta['granted'])->map(fn ($action) => \App\Models\RolePermission::ACTIONS[$action])->implode(' · ') }}</div>
            </a>
        @endforeach
    </div>
    @endif
</section>
@endsection
