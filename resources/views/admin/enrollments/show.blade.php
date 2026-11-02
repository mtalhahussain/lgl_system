@extends('layouts.admin')
@section('title', 'Enrollment #'.$enrollment->id)
@section('content')
@php
    $net = \App\Support\Money::decimal(max(0, \App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount)));
    $progress = (int) min(100, round($enrollment->payment_progress_percentage));
    $statusLabel = ['dropped' => 'withdrawn'][$enrollment->status] ?? null;
    $installments = $enrollment->feeInstallments->sortBy('due_date');
@endphp
<x-show.hero icon="fa-file-signature" :eyebrow="'Enrollment #'.$enrollment->id" :title="$enrollment->student->name" :status="$enrollment->status" :status-label="$statusLabel">
    <x-slot:meta>
        <span><i class="fas fa-layer-group" aria-hidden="true"></i><a href="{{ route('batches.show', $enrollment->batch) }}">{{ $enrollment->batch->name }}</a> · {{ $enrollment->batch->course->name }}</span>
        <span><i class="fas fa-calendar" aria-hidden="true"></i>Enrolled {{ $enrollment->enrollment_date?->format('d M Y') }}</span>
    </x-slot:meta>
    <a class="btn btn-primary" href="{{ route('fees.student', $enrollment->student) }}"><i class="fas fa-receipt me-1" aria-hidden="true"></i>Fees and payments</a>
    <a class="btn btn-outline-primary" href="{{ route('students.show', $enrollment->student) }}"><i class="fas fa-user me-1" aria-hidden="true"></i>Student</a>
</x-show.hero>

@if($enrollment->status === 'dropped')
    <div class="alert alert-secondary">Withdrawn {{ $enrollment->dropout_date?->format('d M Y') }}: {{ $enrollment->dropout_reason }}. Agreement and debt are retained.</div>
@elseif($enrollment->status === 'transferred')
    <div class="alert alert-secondary">Transferred {{ $enrollment->transfer_date?->format('d M Y') }} to {{ $enrollment->transferredToBatch?->name ?? 'another batch' }}. Schedule moved to the new enrollment.</div>
@endif

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3 tw:mb-6">
    <section class="panel" aria-labelledby="fee-heading">
        <div class="panel-heading"><h2 id="fee-heading">Fee agreement</h2><span class="status-pill">{{ $progress }}% paid</span></div>
        <div style="padding:6px 22px 18px">
            <div class="finance-row"><span>Agreed fee</span><strong>{{ currency_format($enrollment->total_fee) }}</strong></div>
            <div class="finance-row"><span>Discount</span><strong>{{ $enrollment->discount_percentage }}% · {{ currency_format($enrollment->discount_amount) }}</strong></div>
            <div class="finance-row"><span>Net payable</span><strong>{{ currency_format($net) }}</strong></div>
            @if(\App\Support\Money::minor($enrollment->refunded_amount ?? '0') > 0)
                <div class="finance-row"><span>Paid</span><strong>{{ currency_format($enrollment->paid_amount) }}</strong></div>
                <div class="finance-row"><span>Refunded</span><strong>− {{ currency_format($enrollment->refunded_amount) }}</strong></div>
                <div class="finance-row"><span>Net paid</span><strong>{{ currency_format($enrollment->net_paid) }}</strong></div>
            @else
                <div class="finance-row"><span>Paid</span><strong>{{ currency_format($enrollment->paid_amount) }}</strong></div>
            @endif
            <div class="finance-row"><span>Outstanding</span><strong>{{ currency_format($enrollment->remaining_fee) }}</strong></div>
            <div class="balance-bar mt-3" role="progressbar" aria-label="Fee paid" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $progress }}%"></span></div>
            @if($enrollment->status !== 'transferred')
                <details class="mt-3 d-print-none" @if($errors->hasAny(['discount_percentage', 'reason', 'refund_method', 'submission_key'])) open @endif>
                    <summary class="fw-semibold" style="cursor:pointer;font-size:13px"><i class="fas fa-percent me-1" aria-hidden="true"></i>Edit discount</summary>
                    <form class="mt-3" method="post" action="{{ route('enrollments.discount', $enrollment) }}" data-confirm="Change the discount? The difference is applied to unpaid installments."
                        data-discount-preview data-gross="{{ \App\Support\Money::minor($enrollment->total_fee) }}" data-discount="{{ \App\Support\Money::minor($enrollment->discount_amount) }}"
                        data-kept="{{ \App\Support\Money::minor($enrollment->net_paid) }}" data-current-percent="{{ $enrollment->discount_percentage }}"
                        data-currency-symbol="{{ setting('currency_symbol', 'Rs.') }}" data-currency-position="{{ setting('currency_position', 'before') }}" data-currency-decimals="{{ setting('currency_decimals', 2) }}">
                        @csrf @method('PATCH')
                        <label class="form-label" for="discount_percentage">Discount %</label>
                        <input class="form-control mb-2 @error('discount_percentage') is-invalid @enderror" id="discount_percentage" name="discount_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('discount_percentage', $enrollment->discount_percentage) }}" required>
                        @error('discount_percentage')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <p class="form-text mt-0 mb-2">This is the new <strong>total</strong> discount, not an extra one.</p>
                        <div class="discount-preview mb-3" data-preview role="status" aria-live="polite" hidden></div>
                        <label class="form-label" for="discount_reason">Reason</label>
                        <input class="form-control mb-2 @error('reason') is-invalid @enderror" id="discount_reason" name="reason" minlength="5" maxlength="1000" value="{{ old('reason') }}" required>
                        @error('reason')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <p class="form-text">Of {{ currency_format($enrollment->total_fee) }}. Paid installments stay unchanged; the difference moves through the latest unpaid ones.</p>
                        @if(\App\Support\Money::minor($enrollment->net_paid) > 0)
                            <fieldset class="border rounded p-3 mb-3" data-refund-section>
                                <legend class="float-none w-auto px-1 mb-1" style="font-size:12px;font-weight:700">Refund (only if needed)</legend>
                                <p class="form-text mt-0">Fill this in when the new net fee is below the {{ currency_format($enrollment->net_paid) }} already paid; the difference is recorded as a refund with a slip.</p>
                                <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                                <label class="form-label" for="refund_method">Refunded by</label>
                                <select class="form-select mb-2 @error('refund_method') is-invalid @enderror" id="refund_method" name="refund_method">
                                    <option value="">No refund</option>
                                    @foreach(\App\Services\PaymentService::REFUND_METHODS as $method => $label)<option value="{{ $method }}" @selected(old('refund_method') === $method)>{{ $label }}</option>@endforeach
                                </select>
                                @error('refund_method')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                                <label class="form-label" for="refund_reference">Refund reference</label>
                                <input class="form-control" id="refund_reference" name="refund_reference" maxlength="255" value="{{ old('refund_reference') }}" placeholder="Cheque or transaction number">
                            </fieldset>
                        @endif
                        <button class="btn btn-outline-primary w-100">Update discount</button>
                    </form>
                </details>
            @endif
        </div>
    </section>
    <section class="panel tw:xl:col-span-2" aria-labelledby="installments-heading">
        <div class="panel-heading"><h2 id="installments-heading">Installments</h2><span class="page-subtitle">{{ $installments->count() }} scheduled</span></div>
        <div class="table-responsive"><table class="table list-table">
            <thead><tr><th scope="col">Due date</th><th scope="col" class="num">Scheduled</th><th scope="col" class="num">Outstanding</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse($installments as $installment)
                @php
                    $open = \App\Support\Money::minor($installment->outstanding_amount) > 0;
                    $state = ! $open ? 'paid' : ($installment->due_date->lt(today()) ? 'overdue' : 'due');
                @endphp
                <tr>
                    <td>{{ $installment->due_date->format('d M Y') }}</td>
                    <td class="num">{{ currency_format($installment->amount) }}</td>
                    <td class="num">{{ currency_format($installment->outstanding_amount) }}</td>
                    <td><x-list.status :value="$state" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty-state">No installment schedule.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if(\App\Support\Money::minor($enrollment->refunded_amount ?? '0') > 0)
            <p class="form-text px-4 pb-3 mb-0">Paid installments include {{ currency_format($enrollment->refunded_amount) }} that was later refunded.</p>
        @endif
    </section>
</div>

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3">
    <section class="panel tw:xl:col-span-2" aria-labelledby="payments-heading">
        <div class="panel-heading"><h2 id="payments-heading">Payments</h2></div>
        <div class="table-responsive"><table class="table list-table">
            <thead><tr><th scope="col">Receipt</th><th scope="col">Date</th><th scope="col">Method</th><th scope="col" class="num">Amount</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse($enrollment->payments->sortByDesc('id') as $payment)
                <tr>
                    <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td>
                    <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ ucfirst($payment->method) }}</td>
                    <td class="num">{{ currency_format($payment->amount) }}</td>
                    <td><x-list.status :value="$payment->reversal ? 'reversed' : 'paid'" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No payments yet. Record one from the fee ledger.</td></tr>
            @endforelse
            @foreach($enrollment->refunds->sortByDesc('id') as $refund)
                <tr>
                    <td><a href="{{ route('refunds.show', $refund) }}">{{ $refund->voucher_number }}</a><span class="cell-sub">{{ $refund->reason }}</span></td>
                    <td>{{ $refund->refunded_at->format('d M Y') }}</td>
                    <td>{{ \App\Services\PaymentService::REFUND_METHODS[$refund->method] ?? $refund->method }}</td>
                    <td class="num">− {{ currency_format($refund->amount) }}</td>
                    <td><x-list.status value="refund" label="refund" class="tone-muted" /></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
    @if($enrollment->status === 'active')
        <section class="panel d-print-none" aria-labelledby="actions-heading">
            <div class="panel-heading"><h2 id="actions-heading">Change enrollment</h2></div>
            <div style="padding:16px 22px">
                @if($transferBatches->isNotEmpty() && $enrollment->paid_amount == 0)
                    <form method="post" action="{{ route('enrollments.transfer', $enrollment) }}" data-confirm="Transfer this enrollment and move its schedule?">
                        @csrf
                        <label class="form-label" for="new_batch_id">Transfer to batch</label>
                        <select id="new_batch_id" name="new_batch_id" class="form-select mb-2">@foreach($transferBatches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }}</option>@endforeach</select>
                        <button class="btn btn-outline-primary w-100">Transfer</button>
                        <p class="form-text">Same course, unpaid enrollments only.</p>
                    </form>
                    <hr>
                @endif
                <form method="post" action="{{ route('enrollments.withdraw', $enrollment) }}" data-confirm="Withdraw this enrollment? Existing debt remains due.">
                    @csrf @method('PATCH')
                    <label class="form-label" for="dropout_reason">Withdrawal reason</label>
                    <input class="form-control mb-2" id="dropout_reason" name="dropout_reason" minlength="5" maxlength="500" required>
                    <button class="btn btn-outline-danger w-100">Withdraw enrollment</button>
                    <p class="form-text">The agreement, debt and payment history are kept; no refund is issued.</p>
                </form>
            </div>
        </section>
    @endif
</div>
@endsection
