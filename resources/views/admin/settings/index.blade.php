@extends('layouts.admin')
@section('title', 'Institute Settings')
@section('content')
<h1>Institute Settings</h1>
<section class="mb-4" aria-labelledby="integrations-heading">
    <h2 class="h4" id="integrations-heading">Integrations</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <a class="card card-body h-100 text-decoration-none" href="{{ route('whatsapp.index') }}">
                <span class="h5 mb-1 text-body"><i class="fab fa-whatsapp me-2 text-success" aria-hidden="true"></i>WhatsApp fee reminders</span>
                <span class="text-muted">Automatic reminders {{ $integrations['whatsapp']['enabled'] ? 'on' : 'off' }} · API {{ $integrations['whatsapp']['configured'] ? 'connected' : 'not set' }}</span>
                <span class="small mt-2">Settings, template, test message and message log →</span>
            </a>
        </div>
        <div class="col-md-6">
            <a class="card card-body h-100 text-decoration-none" href="{{ route('biometric.index') }}">
                <span class="h5 mb-1 text-body"><i class="fas fa-fingerprint me-2 text-primary" aria-hidden="true"></i>Attendance machine</span>
                <span class="text-muted">{{ $integrations['biometric']['machines'] }} active {{ \Illuminate\Support\Str::plural('machine', $integrations['biometric']['machines']) }} · last contact {{ $integrations['biometric']['last_seen'] ? \Illuminate\Support\Carbon::parse($integrations['biometric']['last_seen'])->diffForHumans() : 'never' }}</span>
                <span class="small mt-2">Machines, push URL, bridge PC, user sync and punch log →</span>
            </a>
        </div>
    </div>
</section>
<form method="post" enctype="multipart/form-data" action="{{ route('settings.update') }}">
    @csrf @method('PUT')
    @foreach($settingGroups as $group => $settings)
        <section class="card card-body mb-3">
            <h2 class="h4">{{ ucfirst($group) }}</h2>
            @foreach($settings as $setting)
                <label class="form-label" for="{{ $setting->key }}">{{ $setting->label }}</label>
                @if($setting->type === 'select')
                    <select class="form-select mb-3" id="{{ $setting->key }}" name="{{ $setting->key }}">
                        @foreach($setting->options ?? [] as $value => $label)<option value="{{ $value }}" @selected(old($setting->key, $setting->value) == $value)>{{ $label }}</option>@endforeach
                    </select>
                @elseif($setting->type === 'textarea')
                    <textarea class="form-control mb-3" id="{{ $setting->key }}" name="{{ $setting->key }}">{{ old($setting->key, $setting->value) }}</textarea>
                @elseif($setting->type === 'file')
                    <input class="form-control mb-3" type="file" id="{{ $setting->key }}" name="{{ $setting->key }}" accept="image/png,image/jpeg,image/webp">
                @else
                    <input class="form-control mb-3" id="{{ $setting->key }}" name="{{ $setting->key }}" type="{{ $setting->key === 'institute_phone' ? 'tel' : (in_array($setting->type, ['text', 'email', 'url', 'number']) ? $setting->type : 'text') }}" @if($setting->key === 'institute_phone') inputmode="tel" pattern="\+?[0-9]+" maxlength="30" title="Digits only, with an optional leading +" @endif value="{{ old($setting->key, $setting->value) }}">
                @endif
            @endforeach
        </section>
    @endforeach
    <button class="btn btn-primary">Save settings</button>
</form>
@endsection
