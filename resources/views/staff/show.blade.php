@extends('layouts.admin')
@section('title', $person->name)
@section('content')
@php
    $signsIn = ! str_ends_with($person->email, '@staff.invalid');
    $contact = [
        ['Phone', 'fa-phone', $person->phone ?: '—', $person->phone ? 'tel:'.$person->phone : null],
        ['Emergency contact', 'fa-phone-volume', $person->emergency_contact ?: '—', $person->emergency_contact ? 'tel:'.$person->emergency_contact : null],
        ['Address', 'fa-location-dot', $person->address ?: '—', null],
        ['Sign-in email', 'fa-envelope', $signsIn ? $person->email : 'No sign-in', $signsIn ? 'mailto:'.$person->email : null],
    ];
@endphp
<x-list.header icon="fa-id-badge" :title="$person->name" eyebrow="Staff" :subtitle="($person->designation ?: 'Staff').($person->employee_id ? ' · '.$person->employee_id : '')">
    <a class="btn btn-outline-secondary" href="{{ route('accounts.index', ['role' => 'staff']) }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back to Users</a>
    <a class="btn btn-primary" href="{{ route('staff.edit', $person) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>
</x-list.header>

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-2">
    <section class="panel" aria-labelledby="contact-heading">
        <div class="panel-heading"><h2 id="contact-heading">Contact</h2></div>
        <ul class="profile-facts">
            @foreach($contact as [$label, $icon, $value, $href])
                <li><i class="fas {{ $icon }}" aria-hidden="true"></i><div><small>{{ $label }}</small>
                    @if($href)<a href="{{ $href }}">{{ $value }}</a>@else<span>{{ $value }}</span>@endif</div></li>
            @endforeach
        </ul>
    </section>
    <section class="panel" aria-labelledby="job-heading">
        <div class="panel-heading"><h2 id="job-heading">Job and pay</h2></div>
        <div class="pad-panel">
            <div class="finance-row"><span>Job</span><strong>{{ $person->designation ?: '—' }}</strong></div>
            <div class="finance-row"><span>Employee ID</span><strong>{{ $person->employee_id ?: '—' }}</strong></div>
            <div class="finance-row"><span>Joining date</span><strong>{{ $person->hire_date?->format('d M Y') ?? '—' }}</strong></div>
            <div class="finance-row"><span>Monthly salary</span><strong>{{ $person->monthly_salary !== null ? currency_format($person->monthly_salary) : 'Not set' }}</strong></div>
            <div class="finance-row"><span>Machine number</span><strong>{{ $person->confirmed_machine_number ?? '—' }}</strong></div>
            <div class="finance-row"><span>Status</span><strong><x-list.status :value="$person->is_active ? 'active' : 'inactive'" /></strong></div>
            <div class="finance-row"><span>Sign-in</span><strong>{{ $signsIn ? 'Can sign in (Staff role permissions)' : 'Does not sign in' }}</strong></div>
        </div>
    </section>
</div>
@endsection
