@extends('layouts.admin')
@section('title', 'Batches')
@section('content')
<x-list.header title="Batches" eyebrow="Academics" subtitle="Schedules, teachers, capacity and class platform">
    <a class="btn btn-primary" href="{{ route('batches.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add batch</a>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search batch or course…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="name" default="-start">Batch</x-list.th>
                <th scope="col">Teacher</th>
                <x-list.th sort="start" default="-start">Dates</x-list.th>
                <x-list.th sort="capacity" default="-start">Students</x-list.th>
                <th scope="col">Status</th>
                <th scope="col">Platform</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td><a class="cell-main" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a><span class="cell-sub">{{ $batch->course?->name }} · {{ $batch->course?->level }}</span></td>
                    <td>{{ $batch->teacher?->name ?? 'Not assigned' }}</td>
                    <td>{{ $batch->start_date->format('d M Y') }}@if($batch->end_date)<span class="cell-sub">to {{ $batch->end_date->format('d M Y') }}</span>@endif</td>
                    <td>{{ $batch->active_enrollments_count }} / {{ $batch->max_students }}
                        <div class="balance-bar mt-1" style="max-width:90px" aria-hidden="true"><span style="width:{{ $batch->max_students ? min(100, round(100 * $batch->active_enrollments_count / $batch->max_students)) : 0 }}%"></span></div></td>
                    <td><x-list.status :value="$batch->status" /></td>
                    <td><i class="fas {{ $batch->meeting_platform === 'in_person' ? 'fa-users' : 'fa-video' }} me-1 text-muted" aria-hidden="true"></i>{{ $platforms[$batch->meeting_platform] ?? $batch->meeting_platform }}</td>
                    <td class="text-end">
                        <x-list.row-menu :label="$batch->name">
                            <li><a class="dropdown-item" href="{{ route('batches.show', $batch) }}"><i class="fas fa-eye me-2" aria-hidden="true"></i>View</a></li>
                            <li><a class="dropdown-item" href="{{ route('batches.edit', $batch) }}"><i class="fas fa-pen me-2" aria-hidden="true"></i>Edit</a></li>
                            <li><a class="dropdown-item" href="{{ route('enrollments.create', ['batch_id' => $batch->id]) }}"><i class="fas fa-user-plus me-2" aria-hidden="true"></i>Enroll student</a></li>
                            <li><a class="dropdown-item" href="{{ route('fees.batch', $batch) }}"><i class="fas fa-money-bill me-2" aria-hidden="true"></i>Batch fees</a></li>
                            @if($batch->status !== 'cancelled')
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="post" action="{{ route('batches.destroy', $batch) }}" data-confirm="Cancel {{ $batch->name }}? Enrollment and session history will be preserved.">@csrf @method('DELETE')
                                    <button class="dropdown-item text-danger"><i class="fas fa-ban me-2" aria-hidden="true"></i>Cancel batch</button></form></li>
                            @endif
                        </x-list.row-menu>
                    </td>
                </tr>
            @empty
                <x-list.empty colspan="7" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$batches" label="batches" />
</div>
@endsection
