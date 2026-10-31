@extends('layouts.admin')
@section('title', 'Payment Receipt')
@section('content')
<h1>Payment Receipt</h1>
<p>{{ $payment->receipt_details['institute_name'] ?? 'Institute details unavailable' }}</p>
<dl>
    <dt>Receipt</dt><dd>{{ $payment->receipt_number }}</dd>
    <dt>Student</dt><dd>{{ $payment->receipt_details['student_name'] ?? 'Historical details unavailable' }}</dd>
    <dt>Batch</dt><dd>{{ $payment->receipt_details['batch_name'] ?? 'Historical details unavailable' }}</dd>
    <dt>Date ({{ $payment->receipt_details['timezone'] ?? config('app.timezone') }})</dt><dd>{{ $payment->paid_at?->format('d M Y H:i') ?? 'Historical date unavailable' }}</dd>
    <dt>Amount</dt><dd>{{ $payment->receipt_details['currency_code'] ?? 'PKR' }} {{ number_format($payment->amount, 2) }}</dd>
    <dt>Method</dt><dd>{{ $payment->method }}</dd>
    <dt>Reference</dt><dd>{{ $payment->reference ?? '—' }}</dd>
</dl>
@if($payment->reversal)
    <div class="alert alert-warning">Reversed: {{ $payment->reversal->reason }}. Original receipt retained.</div>
@endif
<table class="table"><thead><tr><th>Installment due date</th><th>Allocated amount</th></tr></thead><tbody>
    @foreach($payment->allocations as $allocation)
        <tr><td>{{ $allocation->installment->due_date->format('d M Y') }}</td><td>{{ $payment->receipt_details['currency_code'] ?? 'PKR' }} {{ number_format($allocation->amount, 2) }}</td></tr>
    @endforeach
</tbody></table>
<button class="btn btn-outline-primary d-print-none" onclick="window.print()">Print receipt</button>
@if(auth()->user()->isAdmin() && !$payment->reversal && !$payment->legacy_source)
    <form class="mt-3 d-print-none" method="post" action="{{ route('payments.reverse', $payment) }}" onsubmit="return confirm('Reverse this payment and restore its outstanding balance?');">
        @csrf
        <label for="reason">Reversal reason</label>
        <input class="form-control mb-2" id="reason" name="reason" minlength="5" maxlength="1000" required>
        <button class="btn btn-outline-danger">Reverse payment</button>
    </form>
@endif
@endsection
