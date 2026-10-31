@extends('layouts.admin')
@section('title', 'My Profile')
@section('content')
<h1>My Profile</h1>
<p>{{ $person->email }}</p>
<form method="post" action="{{ route('profile.update') }}" class="card card-body">
    @csrf @method('PUT')
    @foreach(['name' => 'Name', 'phone' => 'Phone', 'address' => 'Address', 'emergency_contact' => 'Emergency contact'] as $field => $label)
        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
        <input id="{{ $field }}" name="{{ $field }}" class="form-control mb-3" value="{{ old($field, $person->$field) }}" @required($field === 'name')>
    @endforeach
    <label class="form-label" for="password">New password (leave blank to keep current)</label>
    <label class="form-label" for="current_password">Current password (required to change password)</label>
    <input id="current_password" name="current_password" type="password" class="form-control mb-3" autocomplete="current-password">
    <input id="password" name="password" type="password" class="form-control mb-3" minlength="8" autocomplete="new-password">
    <label class="form-label" for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-3" autocomplete="new-password">
    <div><button class="btn btn-primary">Save profile</button></div>
</form>
@endsection
