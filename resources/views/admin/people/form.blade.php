@extends('layouts.admin')
@section('title', ($person ? 'Edit ' : 'Add ').($kind === 'students' ? 'Student' : 'Teacher'))
@section('content')
<h1>{{ $person ? 'Edit' : 'Add' }} {{ $kind === 'students' ? 'Student' : 'Teacher' }}</h1>
<form method="post" action="{{ $person ? route($kind.'.update', $person) : route($kind.'.store') }}" class="card card-body">
    @csrf
    @if($person) @method('PUT') @endif
    @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'address' => 'Address'] as $field => $label)
        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
        <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $person?->$field) }}" required>
    @endforeach
    <label class="form-label" for="date_of_birth">Date of birth</label>
    <input class="form-control mb-3" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $person?->date_of_birth?->format('Y-m-d')) }}">
    <label class="form-label" for="emergency_contact">Emergency contact</label>
    <input class="form-control mb-3" id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact', $person?->emergency_contact) }}">
    @if($kind === 'teachers')
        @foreach(['qualification' => 'Qualification', 'specialization' => 'Specialization', 'experience_years' => 'Experience in years'] as $field => $label)
            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
            <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'experience_years' ? 'number' : 'text' }}" value="{{ old($field, $person?->$field) }}">
        @endforeach
        <label class="form-label" for="hire_date">Hire date</label>
        <input class="form-control mb-3" type="date" id="hire_date" name="hire_date" value="{{ old('hire_date', $person?->hire_date?->format('Y-m-d')) }}">
    @elseif(!$person)
        <label class="form-label" for="batch_id">Enroll in batch (optional)</label>
        <select class="form-select mb-3" id="batch_id" name="batch_id">
            <option value="">Register without enrollment</option>
            @foreach($batches as $batch)
                <option value="{{ $batch->id }}" @selected(old('batch_id') == $batch->id)>{{ $batch->name }}</option>
            @endforeach
        </select>
        <label class="form-label" for="discount_percentage">Discount percentage</label>
        <input class="form-control mb-3" id="discount_percentage" name="discount_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('discount_percentage', 0) }}">
    @endif
    <label class="form-label" for="password">{{ $person ? 'New password (leave blank to keep current)' : 'Password' }}</label>
    <input class="form-control mb-3" id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(!$person)>
    <label class="form-label" for="password_confirmation">Confirm password</label>
    <input class="form-control mb-3" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$person)>
    <input type="hidden" name="is_active" value="0">
    <label class="form-check-label mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $person?->is_active ?? true))> Active account</label>
    <div><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="{{ route($kind.'.index') }}">Cancel</a></div>
</form>
@endsection
