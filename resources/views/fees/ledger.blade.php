@extends('layouts.admin')
@section('title', 'Fee Ledger')
@section('content')
<h1>{{ $student ? $student->name.' — Fees' : ($batch ? $batch->name.' — Fees' : 'Fee Ledger') }}</h1>
@foreach($enrollments as $enrollment)
    <section class="card card-body mb-3">
        <h2 class="h4">{{ $enrollment->student->name }} — {{ $enrollment->batch->name }}</h2>
        <p>Agreed: {{ currency_format($enrollment->total_fee) }}; discount: {{ currency_format($enrollment->discount_amount) }}; paid: {{ currency_format($enrollment->paid_amount) }}</p>
        <table class="table"><thead><tr><th>Due date</th><th>Scheduled</th><th>Outstanding</th></tr></thead><tbody>
            @foreach($enrollment->feeInstallments as $row)
                <tr><td>{{ $row->due_date->format('d M Y') }}</td><td>{{ currency_format($row->amount) }}</td><td>{{ currency_format($row->outstanding_amount) }}</td></tr>
            @endforeach
        </tbody></table>
        @if(auth()->user()->isAdmin() && $enrollment->status !== 'transferred')
            <form method="post" action="{{ route('payments.store', $enrollment) }}">
                @csrf
                <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <label for="amount_{{ $enrollment->id }}">Payment amount</label>
                <input id="amount_{{ $enrollment->id }}" name="amount" type="number" min="0.01" step="0.01" required>
                <label for="method_{{ $enrollment->id }}">Method</label>
                <select id="method_{{ $enrollment->id }}" name="method">
                    @foreach(['cash', 'bank', 'card', 'jazzcash', 'easypaisa'] as $method)<option>{{ $method }}</option>@endforeach
                </select>
                <label for="reference_{{ $enrollment->id }}">Reference</label>
                <input id="reference_{{ $enrollment->id }}" name="reference" maxlength="255">
                <button class="btn btn-primary">Record payment</button>
            </form>
        @endif
    </section>
@endforeach
{{ $enrollments->links() }}
<h2>Payment history</h2>
<ul class="list-group mb-3">
    @forelse($payments as $payment)
        <li class="list-group-item"><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a> — {{ currency_format($payment->amount) }} {{ $payment->reversal ? '(reversed)' : '' }}</li>
    @empty
        <li class="list-group-item">No recorded payments.</li>
    @endforelse
</ul>
{{ $payments->links() }}
@endsection
