@extends('layouts.admin')
@section('title', 'Institute Settings')
@section('content')
<h1>Institute Settings</h1>
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
                    <input class="form-control mb-3" id="{{ $setting->key }}" name="{{ $setting->key }}" type="{{ in_array($setting->type, ['text', 'email', 'url', 'number']) ? $setting->type : 'text' }}" value="{{ old($setting->key, $setting->value) }}">
                @endif
            @endforeach
        </section>
    @endforeach
    <button class="btn btn-primary">Save settings</button>
</form>
@endsection