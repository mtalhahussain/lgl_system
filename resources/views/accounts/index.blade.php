@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<x-list.header title="Users" eyebrow="Administration" subtitle="Change a role or switch an account on or off; it saves immediately. Admins can manage the whole institute, and deactivation preserves history." />
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
                        <div><span class="cell-main">{{ $account->name }}</span><span class="cell-sub">{{ $account->email }}</span></div></div></td>
                    <td>
                        @if($account->role === 'admin')
                            {{-- Admin is not offered in the role list; keep the role fixed so the Active switch never demotes. --}}
                            <input type="hidden" name="role" value="admin" form="{{ $formId }}">
                            <span class="status-pill tone-info"><i class="fas fa-user-shield me-1" aria-hidden="true"></i>Admin</span>
                        @else
                            <label class="visually-hidden" for="role_{{ $account->id }}">Role for {{ $account->name }}</label>
                            <select id="role_{{ $account->id }}" name="role" form="{{ $formId }}" class="form-select form-select-sm" required data-native>
                                @foreach(\Illuminate\Support\Arr::except($roles, 'admin') as $role => $label)<option value="{{ $role }}" @selected($account->role === $role)>{{ $label }}</option>@endforeach
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
@endsection
