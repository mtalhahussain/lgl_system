@extends('layouts.admin')
@section('title', 'Create Fee Schedule')
@section('content')
<x-list.header title="Create Fee Schedule" eyebrow="Finance" subtitle="New admissions already create a schedule. This form is only for an enrollment that has none." />
<form class="panel form-panel" method="post" action="{{ route('installments.store') }}">
    @csrf
    <div class="form-section">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label required" for="enrollment_id">Enrollment without a schedule</label>
                <select class="form-select" id="enrollment_id" name="enrollment_id" required data-combobox="Search student or batch…">
                    @foreach($enrollments as $enrollment)
                        <option value="{{ $enrollment->id }}" data-meta="{{ $enrollment->batch->name }} · net {{ currency_format(\App\Support\Money::decimal(max(0, \App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount)))) }}" @selected(old('enrollment_id') == $enrollment->id)>{{ $enrollment->student->name }} — {{ $enrollment->batch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="number_of_installments">Installment count</label>
                <input class="form-control" id="number_of_installments" name="number_of_installments" type="number" min="1" max="12" value="{{ old('number_of_installments', 1) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="start_date">First due date</label>
                <input class="form-control" id="start_date" name="start_date" type="date" value="{{ old('start_date', today()->toDateString()) }}" required>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('fees.index') }}">Cancel</a>
        <button class="btn btn-primary">Create schedule</button>
    </div>
</form>
@endsection
