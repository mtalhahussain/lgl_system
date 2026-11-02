@extends('layouts.admin')
@section('title', ucfirst($kind))
@section('content')
@php $students = $kind === 'students'; @endphp
<x-list.header :title="ucfirst($kind)" eyebrow="People" :subtitle="$students ? 'Admitted learners and their batches' : 'Teaching staff and assignments'">
    <a class="btn btn-primary" href="{{ route($kind.'.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add {{ $students ? 'student' : 'teacher' }}</a>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search name, email or phone…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="name" default="-joined">Name</x-list.th>
                <th scope="col">Phone</th>
                <th scope="col">{{ $students ? 'Batches' : 'Batches taught' }}</th>
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
                    <td>{{ $students ? ($person->enrollments->pluck('batch.name')->filter()->unique()->implode(', ') ?: '—') : $person->taught_batches_count }}</td>
                    <td><x-list.status :value="$person->is_active ? 'active' : 'inactive'" /></td>
                    <td>{{ $person->created_at?->format('d M Y') }}</td>
                    <td class="text-end">
                        <x-list.row-menu :label="$person->name">
                            <li><a class="dropdown-item" href="{{ route($kind.'.show', $person) }}"><i class="fas fa-eye me-2" aria-hidden="true"></i>View</a></li>
                            <li><a class="dropdown-item" href="{{ route($kind.'.edit', $person) }}"><i class="fas fa-pen me-2" aria-hidden="true"></i>Edit</a></li>
                            @if($students)<li><a class="dropdown-item" href="{{ route('fees.student', $person) }}"><i class="fas fa-money-bill me-2" aria-hidden="true"></i>Fees</a></li>@endif
                            @if($person->is_active)
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="post" action="{{ route($kind.'.destroy', $person) }}" data-confirm="Deactivate {{ $person->name }}? History will be preserved.">@csrf @method('DELETE')
                                    <button class="dropdown-item text-danger"><i class="fas fa-user-slash me-2" aria-hidden="true"></i>Deactivate</button></form></li>
                            @endif
                        </x-list.row-menu>
                    </td>
                </tr>
            @empty
                <x-list.empty colspan="6" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$people" :label="$kind" />
</div>
@endsection
