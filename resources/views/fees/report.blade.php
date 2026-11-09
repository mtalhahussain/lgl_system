@extends('layouts.admin')
@section('title', 'Fee Collection Report')
@section('content')
<x-list.header title="Fee Collection Report" eyebrow="Reports" subtitle="Payments use their payment date; reversals and refunds subtract on their own date.">
    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print this page</button>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" :csv="true" :per-page="false" />
    <p class="cell-sub px-3 pt-3 mb-0">Payment rows {{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }}; reversal rows {{ $reversals->firstItem() ?? 0 }}–{{ $reversals->lastItem() ?? 0 }} of {{ $reversals->total() }}. Totals and CSV cover the full selected period.</p>
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Date</th><th scope="col">Receipt</th><th scope="col">Student</th><th scope="col">Method</th><th scope="col" class="num">Amount</th><th scope="col">Entry</th></tr></thead>
            <tbody>
            @foreach($payments as $payment)
                <tr><td>{{ $payment->paid_at?->format('d M Y') }}</td><td><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td><td>{{ $payment->enrollment->student->name }}</td>
                    <td>{{ \App\Http\Controllers\FeeController::METHODS[$payment->method] ?? $payment->method }}</td><td class="num">{{ currency_format($payment->amount) }}</td><td><x-list.status value="paid" label="Payment" /></td></tr>
            @endforeach
            @foreach($reversals as $reversal)
                <tr><td>{{ $reversal->created_at ? \Illuminate\Support\Carbon::parse($reversal->created_at)->format('d M Y') : '' }}</td><td><a href="{{ route('payments.show', $reversal->payment) }}">{{ $reversal->payment->receipt_number }}</a></td><td>{{ $reversal->payment->enrollment->student->name }}</td>
                    <td>{{ \App\Http\Controllers\FeeController::METHODS[$reversal->payment->method] ?? $reversal->payment->method }}</td><td class="num">-{{ currency_format($reversal->payment->amount) }}</td><td><x-list.status value="reversed" label="Reversal" /></td></tr>
            @endforeach
            @foreach($refunds as $refund)
                <tr><td>{{ $refund->refunded_at?->format('d M Y') }}</td><td><a href="{{ route('refunds.show', $refund) }}">{{ $refund->voucher_number }}</a></td><td>{{ $refund->enrollment->student->name }}</td>
                    <td>{{ \App\Http\Controllers\FeeController::METHODS[$refund->method] ?? $refund->method }}</td><td class="num">-{{ currency_format($refund->amount) }}</td><td><x-list.status value="refund" label="Refund" class="tone-muted" /></td></tr>
            @endforeach
            @if($payments->isEmpty() && $reversals->isEmpty() && $refunds->isEmpty())<x-list.empty colspan="6" message="No collections in this period." />@endif
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$payments" label="payments" :per-page="false" />
    @if($reversals->hasPages())<x-list.footer :paginator="$reversals" label="reversals" :per-page="false" />@endif
    @if($refunds->hasPages())<x-list.footer :paginator="$refunds" label="refunds" :per-page="false" />@endif
</div>
@endsection
