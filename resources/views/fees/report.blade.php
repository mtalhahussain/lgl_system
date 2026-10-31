@extends('layouts.admin')
@section('title', 'Fee Collection Report')
@section('content')
<h1>Fee Collection Report</h1>
@include('admin.reports.filters')
<p>Net collected in this period: {{ currency_format($net) }}</p>
<p>Payments use their payment date; reversals subtract on their reversal date.</p>
<p>Payment rows {{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }}; reversal rows {{ $reversals->firstItem() ?? 0 }}–{{ $reversals->lastItem() ?? 0 }} of {{ $reversals->total() }}. Net total covers the full selected period.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Receipt</th><th>Student</th><th>Amount</th><th>Entry</th></tr></thead><tbody>
    @foreach($payments as $payment)
        <tr><td><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td><td>{{ $payment->enrollment->student->name }}</td><td>{{ currency_format($payment->amount) }}</td><td>Payment</td></tr>
    @endforeach
    @foreach($reversals as $reversal)
        <tr><td><a href="{{ route('payments.show', $reversal->payment) }}">{{ $reversal->payment->receipt_number }}</a></td><td>{{ $reversal->payment->enrollment->student->name }}</td><td>-{{ currency_format($reversal->payment->amount) }}</td><td>Reversal</td></tr>
    @endforeach
</tbody></table></div>
{{ $payments->links() }}
{{ $reversals->links() }}
<button class="btn btn-outline-primary d-print-none" onclick="window.print()">Print this page</button>
@endsection
