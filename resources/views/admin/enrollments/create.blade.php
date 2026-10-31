@extends('layouts.admin')
@section('title', 'Enroll Student')
@section('content')
<h1>Enroll Student</h1>
<form method="post" action="{{ route('enrollments.store') }}" class="card card-body">
    @csrf
    <label for="student_id">Student</label>
    <select class="form-select mb-3" id="student_id" name="student_id" required>
        @foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>{{ $student->name }}</option>@endforeach
    </select>
    <label for="batch_id">Batch</label>
    <select class="form-select mb-3" id="batch_id" name="batch_id" required>
        @foreach($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id', $selectedBatch?->id) == $batch->id)>{{ $batch->name }} — {{ $batch->course->name }} ({{ currency_format($batch->course->total_fee) }})</option>@endforeach
    </select>
    <label for="discount_percentage">Discount percentage</label><input class="form-control mb-3" id="discount_percentage" name="discount_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('discount_percentage', 0) }}">
    <label for="installments">Equal monthly installment count</label><input class="form-control mb-3" id="installments" name="installments" type="number" min="1" max="12" value="{{ old('installments', 1) }}">
    <label class="mb-2"><input type="checkbox" id="custom_schedule" @checked(old('schedule'))> Use custom amounts and due dates instead</label>
    <div id="schedule_rows">
        <p>Fill only needed rows. Amounts must add up to the fee after discount.</p>
        @for($index = 0; $index < 12; $index++)
            <div class="row mb-2">
                <div class="col"><label for="amount_{{ $index }}">Installment {{ $index + 1 }} amount</label><input class="form-control" id="amount_{{ $index }}" name="schedule[{{ $index }}][amount]" type="number" min="0" step="0.01" value="{{ old('schedule.'.$index.'.amount') }}" disabled></div>
                <div class="col"><label for="date_{{ $index }}">Due date</label><input class="form-control" id="date_{{ $index }}" name="schedule[{{ $index }}][due_date]" type="date" value="{{ old('schedule.'.$index.'.due_date') }}" disabled></div>
            </div>
        @endfor
    </div>
    <div><button class="btn btn-primary">Enroll and create fee plan</button></div>
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
