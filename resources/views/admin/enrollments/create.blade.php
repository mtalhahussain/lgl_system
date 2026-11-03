@extends('layouts.admin')
@section('title', 'Enroll Student')
@section('content')
<div class="alert alert-light border">Paying for A1 and A2 together? <a href="{{ route('enrollment-packages.create') }}">Use the Rs 50,000 full-payment package</a>. This form is for normal module-wise admission.</div>
<x-list.header title="Enroll Student" eyebrow="Admissions" subtitle="Admission locks the fee agreement and creates the installment plan" />
<form method="post" action="{{ route('enrollments.store') }}" class="panel form-panel">
    @csrf
    <div class="form-section">
        <h2 class="form-section-title">Student and batch</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="student_id">Student</label>
                <select class="form-select" id="student_id" name="student_id" required>
                    @foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>{{ $student->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="batch_id">Batch</label>
                <select class="form-select" id="batch_id" name="batch_id" required>
                    @foreach($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id', $selectedBatch?->id) == $batch->id)>{{ $batch->name }} — {{ $batch->course->name }} ({{ currency_format($batch->course->total_fee) }})</option>@endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Fee plan</h2>
        <p class="form-section-note">Equal monthly installments split any remainder deterministically.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="discount_percentage">Discount percentage</label>
                <input class="form-control" id="discount_percentage" name="discount_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('discount_percentage', 0) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="installments">Equal monthly installment count</label>
                <input class="form-control" id="installments" name="installments" type="number" min="1" max="12" value="{{ old('installments', 1) }}">
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="custom_schedule" @checked(old('schedule'))>
                    <label class="form-check-label" for="custom_schedule">Use custom amounts and due dates instead</label>
                </div>
            </div>
        </div>
        <div id="schedule_rows" class="mt-3">
            <p class="form-section-note">Fill only needed rows. Amounts must add up to the fee after discount.</p>
            @for($index = 0; $index < 12; $index++)
                <div class="row g-3 mb-2">
                    <div class="col-md-6"><label class="form-label" for="amount_{{ $index }}">Installment {{ $index + 1 }} amount</label><input class="form-control" id="amount_{{ $index }}" name="schedule[{{ $index }}][amount]" type="number" min="0" step="0.01" value="{{ old('schedule.'.$index.'.amount') }}" disabled></div>
                    <div class="col-md-6"><label class="form-label" for="date_{{ $index }}">Due date</label><input class="form-control" id="date_{{ $index }}" name="schedule[{{ $index }}][due_date]" type="date" value="{{ old('schedule.'.$index.'.due_date') }}" disabled></div>
                </div>
            @endfor
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('enrollments.index') }}">Cancel</a>
        <button class="btn btn-primary">Enroll and create fee plan</button>
    </div>
</form>
<script>
const customSchedule = document.getElementById('custom_schedule');
const scheduleRows = document.getElementById('schedule_rows');
function showSchedule() {
    scheduleRows.hidden = !customSchedule.checked;
    scheduleRows.querySelectorAll('input').forEach(input => input.disabled = !customSchedule.checked);
}
customSchedule.addEventListener('change', showSchedule);
showSchedule();
</script>
@endsection
