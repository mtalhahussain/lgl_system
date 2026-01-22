@extends('layouts.admin')
@section('content')
<div class="container">
    <h2>Record Teacher Payment</h2>
    <form action="{{ route('teacher_payments.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="batch_teacher_earning_id" class="form-label">Batch & Teacher</label>
            <select name="batch_teacher_earning_id" id="batch_teacher_earning_id" class="form-select" required>
                <option value="">Select Batch & Teacher</option>
                @foreach($batchEarnings as $earning)
                    <option value="{{ $earning->id }}">
                        {{ $earning->batch->name ?? '' }} - {{ $earning->teacher->name ?? '' }} ({{ ucfirst($earning->salary_type) }}: {{ number_format($earning->salary_amount, 2) }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="payment_date" class="form-label">Payment Date</label>
            <input type="date" name="payment_date" id="payment_date" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="reference" class="form-label">Reference</label>
            <input type="text" name="reference" id="reference" class="form-control">
        </div>
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea name="notes" id="notes" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-success">Record Payment</button>
        <a href="{{ route('teacher_payments.index') }}" class="btn btn-secondary">Back</a>
    </form>
</div>
@endsection
