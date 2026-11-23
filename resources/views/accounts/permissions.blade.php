@extends('layouts.admin')
@section('title', 'Role Permissions')
@section('content')
<x-list.header icon="fa-user-lock" title="Role permissions" eyebrow="Administration" subtitle="Choose what each role can use. Every user of a role gets that role's permissions; Admin always has everything.">
    <a class="btn btn-outline-secondary" href="{{ route('accounts.index') }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back to Users</a>
</x-list.header>
<section class="panel" id="role-permissions" aria-labelledby="role-permissions-heading">
    <div class="panel-heading"><h2 id="role-permissions-heading"><i class="fas fa-user-lock me-2" aria-hidden="true"></i>Role permissions</h2><span class="page-subtitle">Role-wide · Admin always has everything</span></div>
    <form method="post" action="{{ route('accounts.permissions') }}" class="pad-panel" data-autosave>
        @csrf @method('PUT')
        <p class="form-text mt-0">Every user of a role gets that role's permissions. Each switch saves immediately. Switched-off modules disappear from the menu and their pages are blocked. Dashboard, notifications and own profile settings always stay available.</p>
        <div class="row g-4">
            @foreach($permissionRoles as $permissionRole => $modules)
                <div class="col-md-6 col-xl-4">
                    <fieldset>
                        <legend class="h6">{{ ucfirst($permissionRole) }}</legend>
                        @if($permissionRole === 'staff')
                            <p class="form-text mt-0">Off until you switch it on; a switched-on module gives Staff full use of it. Users &amp; permissions, Settings &amp; integrations, and Payroll &amp; refunds always stay with Admin.</p>
                        @endif
                        @foreach($modules as $module => $meta)
                            @php
                                $fieldId = 'perm_'.$permissionRole.'_'.$module;
                                $actions = array_keys(\App\Models\RolePermission::actionsFor($permissionRole, $module));
                                $default = $permissionRole !== 'staff';
                                $on = collect($actions)->every(fn ($action) => $permissions[$permissionRole.'.'.$module.'.'.$action] ?? $default);
                            @endphp
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $fieldId }}" name="permissions[{{ $permissionRole }}][{{ $module }}][all]" value="1" @checked($on)>
                                <label class="form-check-label" for="{{ $fieldId }}"><i class="fas {{ $meta['icon'] }} me-2 text-muted" aria-hidden="true"></i>{{ $meta['label'] }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                </div>
            @endforeach
        </div>
        <noscript><div class="form-actions px-0 pb-0"><button class="btn btn-primary">Save permissions</button></div></noscript>
    </form>
</section>
@endsection
