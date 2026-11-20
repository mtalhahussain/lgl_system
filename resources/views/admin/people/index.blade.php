@extends('layouts.admin')
@section('title', ucfirst($kind))
@section('content')
@php $students = $kind === 'students'; @endphp
<x-list.header :title="ucfirst($kind)" eyebrow="People" :subtitle="$students ? 'Admitted learners and their batches' : 'Teaching staff and assignments'">
    <a class="btn btn-primary" href="{{ route($kind.'.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ $students ? 'Student admission' : 'Add teacher' }}</a>
    @if($students) @if(can_route('enrollment-packages.create'))<a class="btn btn-outline-primary" href="{{ route('enrollment-packages.create') }}">Multi-level package</a>@endif @endif
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search name, email or phone…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="name" default="-joined">Name</x-list.th>
                <th scope="col">Phone</th>
                @if($students)<th scope="col">Attendance machine</th>@endif
                <th scope="col">{{ $students ? 'Level / batch' : 'Batches taught' }}</th>
                @if($students)
                    <th scope="col" class="num">Discount</th><th scope="col" class="num">Outstanding</th>
                @endif
                <th scope="col">Status</th>
                <x-list.th sort="joined" default="-joined">Joined</x-list.th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($people as $person)
                <tr>
                    <td><div class="person-cell"><span class="user-avatar" aria-hidden="true">{{ mb_substr($person->name, 0, 1) }}</span>
                        <div><a class="cell-main" href="{{ route($kind.'.show', $person) }}">{{ $person->name }}</a><span class="cell-sub">{{ $person->email }}</span></div></div></td>
                    <td>{{ $person->phone ?: '—' }}</td>
                    @if($students)
                        @php
                            $machineRows = $person->machineSyncRows->where('employee_no', $person->device_employee_no)->where('action', 'upsert');
                            $machineNumber = $person->confirmed_machine_number;
                        @endphp
                        <td>
                            @if($machineNumber)<span class="cell-main">ID: {{ $machineNumber }}</span>@endif
                            <span class="cell-sub">{{ $machineRows->contains('status', 'failed') ? 'Sync failed' : ($machineRows->contains('status', 'pending') ? 'Waiting for machine' : ($machineNumber ? 'Synced' : 'Not confirmed')) }}</span>
                        </td>
                    @endif
                    @if($students)
                        <td>@forelse($person->enrollments as $admission)
                            <a class="cell-main" href="{{ route('enrollments.show', $admission) }}">{{ $admission->batch?->course?->name }} ({{ $admission->batch?->course?->level }})</a>
                            <span class="cell-sub">{{ $admission->batch?->name }}</span>
                        @empty<span class="text-muted">Admission pending</span>@endforelse</td>
                        <td class="num">@forelse($person->enrollments as $admission)<span class="cell-main">{{ currency_format($admission->discount_amount) }}</span><span class="cell-sub">{{ $admission->batch?->name }}</span>@empty—@endforelse</td>
                        <td class="num">@forelse($person->enrollments as $admission)<a class="cell-main" href="{{ route('enrollments.show', $admission) }}">{{ currency_format($admission->remaining_fee) }}</a><span class="cell-sub">{{ $admission->batch?->name }}</span>@empty—@endforelse</td>
                    @else<td>{{ $person->taught_batches_count }}</td>@endif
                    <td><x-list.status :value="$person->is_active ? 'active' : 'inactive'" /></td>
                    <td>{{ $person->created_at?->format('d M Y') }}</td>
                    <td class="text-end">
                        @if($students && $person->is_active)
                            <form class="d-inline" method="post" action="{{ route('biometric.resync-user', $person) }}">
                                @csrf
                                <button class="btn btn-icon" type="submit" title="Send to attendance machines" aria-label="Sync {{ $person->name }} to attendance machines"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
                            </form>
                        @endif
                        <x-list.row-menu :label="$person->name">
                            <li><a class="dropdown-item" href="{{ route($kind.'.show', $person) }}"><i class="fas fa-eye me-2" aria-hidden="true"></i>View</a></li>
                            <li><a class="dropdown-item" href="{{ route($kind.'.edit', $person) }}"><i class="fas fa-pen me-2" aria-hidden="true"></i>Edit</a></li>
                            @if($students)<li><a class="dropdown-item" href="{{ route('fees.student', $person) }}"><i class="fas fa-money-bill me-2" aria-hidden="true"></i>Fees</a></li>@endif
                            @if($students && $person->is_active) @if(can_route('students.create'))<li><a class="dropdown-item" href="{{ route('students.create', ['student_id' => $person->id]) }}">Add admission</a></li>@endif @endif
                            @if($person->is_active)
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="post" action="{{ route($kind.'.destroy', $person) }}" data-confirm="Deactivate {{ $person->name }}? History will be preserved.">@csrf @method('DELETE')
                                    <button class="dropdown-item text-danger"><i class="fas fa-user-slash me-2" aria-hidden="true"></i>Deactivate</button></form></li>
                            @endif
                        </x-list.row-menu>
                    </td>
                </tr>
            @empty
                <x-list.empty :colspan="$students ? 9 : 6" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$people" :label="$kind" />
</div>
@endsection
