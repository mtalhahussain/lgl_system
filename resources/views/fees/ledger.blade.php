@extends('layouts.admin')
@section('title', 'Fee Ledger')
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-list.header :title="$student ? $student->name.' — Fees' : ($batch ? $batch->name.' — Fees' : 'Fee Ledger')" eyebrow="Finance"
    subtitle="Fee agreements, installment balances and recorded payments">
    @if($isAdmin && ! $student && ! $batch)<a class="btn btn-outline-primary" href="{{ route('fees.reports') }}"><i class="fas fa-chart-line me-1" aria-hidden="true"></i>Collection report</a>@endif
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" :search="$student ? null : 'Search student name or email…'" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th :sort="$student ? null : 'student'" default="-admitted">Student / batch</x-list.th>
                <th scope="col" class="num">Net agreed</th>
                <th scope="col" class="num">Paid</th>
                <x-list.th sort="balance" default="-admitted" class="num">Outstanding</x-list.th>
                <th scope="col">Next due</th>
                <th scope="col">Balance</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($enrollments as $enrollment)
                @php
                    $installments = $enrollment->feeInstallments->sortBy('due_date');
                    $open = $installments->filter(fn ($row) => \App\Support\Money::minor($row->outstanding_amount) > 0);
                    $next = $open->first();
                    $state = $installments->isEmpty() ? 'no schedule' : ($open->isEmpty() ? 'paid' : ($open->contains(fn ($row) => $row->due_date->lt(today())) ? 'overdue' : 'due'));
                    $net = \App\Support\Money::decimal(max(0, \App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount)));
                    $detailId = 'ledger_detail_'.$enrollment->id;
                @endphp
                <tr>
                    <td><span class="cell-main">{{ $enrollment->student->name }}</span><span class="cell-sub">{{ $enrollment->batch->name }} · {{ $enrollment->status }}@if(\App\Support\Money::minor($enrollment->discount_amount) > 0) · {{ $enrollment->discount_percentage }}% off @endif</span></td>
                    <td class="num">{{ currency_format($net) }}</td>
                    <td class="num">{{ currency_format($enrollment->net_paid) }}@if(\App\Support\Money::minor($enrollment->refunded_amount ?? '0') > 0)<span class="cell-sub">after {{ currency_format($enrollment->refunded_amount) }} refund</span>@endif</td>
                    <td class="num"><strong>{{ currency_format($enrollment->remaining_fee) }}</strong></td>
                    <td>{{ $next?->due_date->format('d M Y') ?? '—' }}</td>
                    <td><x-list.status :value="$state" /></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="false" aria-controls="{{ $detailId }}" aria-label="Installments{{ $isAdmin ? ' and payment' : '' }} for {{ $enrollment->student->name }}">
                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        @if($isAdmin)
                            <x-list.row-menu :label="$enrollment->student->name">
                                <li><a class="dropdown-item" href="{{ route('enrollments.show', $enrollment) }}"><i class="fas fa-file-signature me-2" aria-hidden="true"></i>Enrollment</a></li>
                                <li><a class="dropdown-item" href="{{ route('fees.student', $enrollment->student) }}"><i class="fas fa-user me-2" aria-hidden="true"></i>All fees for student</a></li>
                            </x-list.row-menu>
                        @endif
                    </td>
                </tr>
                <tr class="detail-row"><td colspan="7" class="p-0">
                    <div id="{{ $detailId }}" class="collapse"><div class="detail-body">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h2 class="h6">Installments</h2>
                                <table class="table table-sm">
                                    <thead><tr><th scope="col">Due date</th><th scope="col" class="num">Scheduled</th><th scope="col" class="num">Outstanding</th></tr></thead>
                                    <tbody>
                                    @forelse($installments as $row)
                                        <tr><td>{{ $row->due_date->format('d M Y') }}</td><td class="num">{{ currency_format($row->amount) }}</td><td class="num">{{ currency_format($row->outstanding_amount) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3">No installment schedule.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                                <p class="cell-sub mt-2">Agreed {{ currency_format($enrollment->total_fee) }}; discount {{ $enrollment->discount_percentage }}% ({{ currency_format($enrollment->discount_amount) }}).@if(auth()->user()->isAdmin() && $enrollment->status !== 'transferred') <a href="{{ route('enrollments.show', $enrollment) }}#fee-heading">Edit discount</a>@endif</p>
                            </div>
                            @if($isAdmin && $enrollment->status !== 'transferred')
                                <div class="col-lg-6">
                                    <h2 class="h6">Record payment</h2>
                                    <form method="post" action="{{ route('payments.store', $enrollment) }}" class="row g-2">
                                        @csrf
                                        <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                                        <div class="col-sm-6"><label class="form-label" for="amount_{{ $enrollment->id }}">Payment amount</label>
                                            <input class="form-control" id="amount_{{ $enrollment->id }}" name="amount" type="number" min="0.01" step="0.01" max="{{ $enrollment->remaining_fee }}" required></div>
                                        <div class="col-sm-6"><label class="form-label" for="method_{{ $enrollment->id }}">Method</label>
                                            <select class="form-select" id="method_{{ $enrollment->id }}" name="method">
                                                @foreach(['cash' => 'Cash', 'bank' => 'Bank', 'card' => 'Card', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa'] as $method => $label)<option value="{{ $method }}">{{ $label }}</option>@endforeach
                                            </select></div>
                                        <div class="col-12"><label class="form-label" for="reference_{{ $enrollment->id }}">Reference</label>
                                            <input class="form-control" id="reference_{{ $enrollment->id }}" name="reference" maxlength="255"></div>
                                        <div class="col-12"><button class="btn btn-primary">Record payment</button></div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div></div>
                </td></tr>
            @empty
                <x-list.empty colspan="7" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$enrollments" label="enrollments" />
</div>

<div class="panel list-panel">
    <div class="panel-heading"><h2>Payment history</h2></div>
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Receipt</th><th scope="col">Student</th><th scope="col">Paid at</th><th scope="col">Method</th><th scope="col" class="num">Amount</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><a class="cell-main" href="{{ route('payments.show', $payment) }}">{{ $payment->receipt_number }}</a></td>
                    <td>{{ $payment->enrollment->student->name }}</td>
                    <td>{{ $payment->paid_at?->format('d M Y H:i') }}</td>
                    <td>{{ $payment->method }}</td>
                    <td class="num">{{ currency_format($payment->amount) }}</td>
                    <td><x-list.status :value="$payment->reversal ? 'reversed' : 'paid'" :label="$payment->reversal ? 'Reversed' : 'Posted'" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No recorded payments.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$payments" label="payments" />
</div>

@if($refunds->total() > 0)
<div class="panel list-panel mt-4">
    <div class="panel-heading"><h2>Refunds</h2><span class="page-subtitle">Recorded when a later discount exceeded the unpaid fee</span></div>
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Refund slip</th><th scope="col">Student</th><th scope="col">Refunded at</th><th scope="col">Method</th><th scope="col" class="num">Amount</th><th scope="col">Reason</th></tr></thead>
            <tbody>
            @foreach($refunds as $refund)
                <tr>
                    <td><a class="cell-main" href="{{ route('refunds.show', $refund) }}">{{ $refund->voucher_number }}</a></td>
                    <td>{{ $refund->enrollment->student->name }}</td>
                    <td>{{ $refund->refunded_at->format('d M Y H:i') }}</td>
                    <td>{{ \App\Services\PaymentService::REFUND_METHODS[$refund->method] ?? $refund->method }}</td>
                    <td class="num">− {{ currency_format($refund->amount) }}</td>
                    <td class="cell-sub">{{ $refund->reason }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$refunds" label="refunds" />
</div>
@endif
@endsection
