@extends('layouts.admin')
@section('title', 'Create Fee Schedule')
@section('content')
<h1>Create Fee Schedule</h1>
<form class="card card-body" method="post" action="{{ route('installments.store') }}">
    @csrf
    <label class="form-label" for="enrollment_id">Enrollment without a schedule</label>
    <select class="form-select mb-3" id="enrollment_id" name="enrollment_id" required>
        @foreach($enrollments as $enrollment)
            <option value="{{ $enrollment->id }}">{{ $enrollment->student->name }} — {{ $enrollment->batch->name }}</option>
        @endforeach
    </select>
    <label class="form-label" for="number_of_installments">Installment count</label>
    <input class="form-control mb-3" id="number_of_installments" name="number_of_installments" type="number" min="1" max="12" value="{{ old('number_of_installments', 1) }}" required>
    <label class="form-label" for="start_date">First due date</label>
    <input class="form-control mb-3" id="start_date" name="start_date" type="date" value="{{ old('start_date', today()->toDateString()) }}" required>
    <p>New admissions already create a schedule. This form is only for an enrollment that has none.</p>
    <div><button class="btn btn-primary">Create schedule</button></div>
</form>
@endsection
