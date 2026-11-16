@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<x-list.header title="Users" eyebrow="Administration" subtitle="Change a role or switch an account on or off; it saves immediately. Admins can manage the whole institute, and deactivation preserves history. Staff are paid and use the machine but do not sign in.">
    <a class="btn btn-primary" href="{{ route('accounts.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add user</a>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search name or email…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="name" default="name">User</x-list.th>
                <x-list.th sort="role" default="name">Role</x-list.th>
                <th scope="col">Active</th>
                <x-list.th sort="created" default="name">Created</x-list.th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $account)
                @php $formId = 'account_'.$account->id; @endphp
                <tr>
                    <td><div class="person-cell"><span class="user-avatar" aria-hidden="true">{{ mb_substr($account->name, 0, 1) }}</span>
                        <div><span class="cell-main">{{ $account->name }}</span><span class="cell-sub">{{ $account->isStaff() ? ($account->designation ?: 'Staff').($account->phone ? ' · '.$account->phone : '') : $account->email }}</span></div></div></td>
                    <td>
                        @if($account->role === 'admin')
                            {{-- Admin is not offered in the role list; keep the role fixed so the Active switch never demotes. --}}
                            <input type="hidden" name="role" value="admin" form="{{ $formId }}">
                            <span class="status-pill tone-info"><i class="fas fa-user-shield me-1" aria-hidden="true"></i>Admin</span>
                        @elseif($account->isStaff())
                            <input type="hidden" name="role" value="staff" form="{{ $formId }}">
                            <span class="status-pill tone-muted"><i class="fas fa-id-badge me-1" aria-hidden="true"></i>Staff</span>
                            <a class="d-block small mt-1" href="{{ route('staff.edit', $account) }}">Edit</a>
                        @else
                            <label class="visually-hidden" for="role_{{ $account->id }}">Role for {{ $account->name }}</label>
                            <select id="role_{{ $account->id }}" name="role" form="{{ $formId }}" class="form-select form-select-sm" required data-native>
                                @foreach(\Illuminate\Support\Arr::except($roles, ['admin', 'staff']) as $role => $label)<option value="{{ $role }}" @selected($account->role === $role)>{{ $label }}</option>@endforeach
                            </select>
                        @endif
                    </td>
                    <td>
                        <input type="hidden" name="is_active" value="0" form="{{ $formId }}">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="active_{{ $account->id }}" name="is_active" value="1" form="{{ $formId }}" @checked($account->is_active)>
                            <label class="form-check-label" for="active_{{ $account->id }}"><x-list.status :value="$account->is_active ? 'active' : 'inactive'" /></label>
                        </div>
                    </td>
                    <td>{{ $account->created_at?->format('d M Y') }}
                        <form id="{{ $formId }}" method="post" action="{{ route('accounts.update', $account) }}" data-autosave>
                            @csrf @method('PATCH')
                            <noscript><button class="btn btn-outline-primary btn-sm mt-1">Save</button></noscript>
                        </form>
                    </td>
                </tr>
            @empty
                <x-list.empty colspan="4" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$accounts" label="users" />
</div>

<section class="panel mt-4" id="role-permissions" aria-labelledby="role-permissions-heading">
    <div class="panel-heading"><h2 id="role-permissions-heading"><i class="fas fa-user-lock me-2" aria-hidden="true"></i>Role permissions</h2><span class="page-subtitle">Admin always has every module</span></div>
    <form method="post" action="{{ route('accounts.permissions') }}" class="pad-panel">
        @csrf @method('PUT')
        <p class="form-text mt-0">Tick a module to show it to that role. Unticked modules disappear from the menu and their pages are blocked. Dashboard, notifications and own profile settings always stay available.</p>
        <div class="row g-4">
            @foreach($permissionRoles as $permissionRole => $modules)
                <div class="col-md-6">
                    <fieldset>
                        <legend class="h6">{{ ucfirst($permissionRole) }}</legend>
                        @foreach($modules as $module => $meta)
                            @php $fieldId = 'perm_'.$permissionRole.'_'.$module; @endphp
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $fieldId }}" name="permissions[{{ $permissionRole }}][{{ $module }}]" value="1" @checked($permissions[$permissionRole.'.'.$module] ?? true)>
                                <label class="form-check-label" for="{{ $fieldId }}"><i class="fas {{ $meta['icon'] }} me-2 text-muted" aria-hidden="true"></i>{{ $meta['label'] }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                </div>
            @endforeach
        </div>
        <div class="form-actions px-0 pb-0">
            <button class="btn btn-primary">Save permissions</button>
        </div>
    </form>
</section>
@endsection
