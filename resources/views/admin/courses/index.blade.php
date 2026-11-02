@extends('layouts.admin')
@section('title', 'Courses')
@section('content')
<x-list.header title="Courses" eyebrow="Academics" subtitle="Course catalogue, default fees and batch activity">
    <a class="btn btn-primary" href="{{ route('courses.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add course</a>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search course name or description…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="name" default="level">Course</x-list.th>
                <x-list.th sort="level" default="level">Level</x-list.th>
                <x-list.th sort="duration" default="level">Duration</x-list.th>
                <x-list.th sort="fee" default="level" class="num">Fee</x-list.th>
                <th scope="col">Batches</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($courses as $course)
                <tr>
                    <td><a class="cell-main" href="{{ route('courses.show', $course) }}">{{ $course->name }}</a><span class="cell-sub">{{ \Illuminate\Support\Str::limit($course->description, 70) }}</span></td>
                    <td><span class="status-pill">{{ $course->level }}</span></td>
                    <td>{{ $course->duration_weeks }} weeks<span class="cell-sub">{{ $course->sessions_per_week }} sessions / week</span></td>
                    <td class="num">{{ currency_format($course->total_fee) }}</td>
                    <td>{{ $course->batches_count }}<span class="cell-sub">{{ $course->active_batches_count }} ongoing</span></td>
                    <td><x-list.status :value="$course->is_active ? 'active' : 'inactive'" /></td>
                    <td class="text-end">
                        <x-list.row-menu :label="$course->name">
                            <li><a class="dropdown-item" href="{{ route('courses.show', $course) }}"><i class="fas fa-eye me-2" aria-hidden="true"></i>View</a></li>
                            <li><a class="dropdown-item" href="{{ route('courses.edit', $course) }}"><i class="fas fa-pen me-2" aria-hidden="true"></i>Edit</a></li>
                            <li><a class="dropdown-item" href="{{ route('batches.create', ['course_id' => $course->id]) }}"><i class="fas fa-layer-group me-2" aria-hidden="true"></i>New batch</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><form method="post" action="{{ route('courses.toggle-status', $course) }}" data-confirm="{{ $course->is_active ? 'Deactivate' : 'Activate' }} {{ $course->name }}? Existing batches and history stay unchanged.">@csrf
                                <button class="dropdown-item {{ $course->is_active ? 'text-danger' : '' }}"><i class="fas fa-power-off me-2" aria-hidden="true"></i>{{ $course->is_active ? 'Deactivate' : 'Activate' }}</button></form></li>
                        </x-list.row-menu>
                    </td>
                </tr>
            @empty
                <x-list.empty colspan="7" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$courses" label="courses" />
</div>
@endsection
