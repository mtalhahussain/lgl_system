@extends('layouts.admin')
@section('title', $due->label())
@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $outstanding = \App\Support\Money::decimal($due->outstandingMinor());
@endphp
<x-show.hero :icon="$due->kind === 'salary' ? 'fa-money-check-alt' : 'fa-users'" eyebrow="Payroll" :title="$due->label()"
    :status="$due->state()" :status-label="$due->voided_at ? 'voided' : null">
    <x-slot:meta>
        <span><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i>{{ $due->teacher->name }}{{ $due->teacher->employee_id ? ' · '.$due->teacher->employee_id : '' }}</span>
        <span><i class="fas fa-wallet" aria-hidden="true"></i>{{ currency_format($outstanding) }} outstanding</span>
    </x-slot:meta>
    <a class="btn btn-outline-secondary" href="{{ route('payroll.index') }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ $isAdmin ? 'Payroll' : 'My earnings' }}</a>
</x-show.hero>
@if($due->voided_at)
    <div class="alert alert-warning">Voided {{ $due->voided_at->format('d M Y') }}: {{ $due->void_reason }}</div>
@endif
<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:lg:grid-cols-3 tw:mb-6">
    <section class="panel" aria-labelledby="due-summary">
        <div class="panel-heading"><h2 id="due-summary">Summary</h2></div>
        <div style="padding:18px 22px">
            <div class="finance-row"><span>Type</span><strong>{{ $due->kind === 'salary' ? 'Monthly salary' : 'Per student (batch)' }}</strong></div>
            @if($due->kind === 'batch')
                <div class="finance-row"><span>Students</span><strong>{{ $due->student_count }} × {{ currency_format($due->rate) }}</strong></div>
            @endif
            <div class="finance-row"><span>Amount</span><strong>{{ currency_format($due->amount) }}</strong></div>
            <div class="finance-row"><span>Paid</span><strong>{{ currency_format(\App\Support\Money::decimal($due->paidMinor())) }}</strong></div>
            <div class="finance-row"><span>Outstanding</span><strong>{{ currency_format($outstanding) }}</strong></div>
            <div class="finance-row"><span>Created</span><strong>{{ $due->created_at?->format('d M Y') }}</strong></div>
        </div>
    </section>
    @if($isAdmin && ! $due->voided_at)
        <section class="panel tw:lg:col-span-2 d-print-none" aria-labelledby="record-payout">
            <div class="panel-heading"><h2 id="record-payout">Record payout</h2></div>
            <div style="padding:18px 22px">
                @if($due->outstandingMinor() > 0)
                    <form method="post" action="{{ route('payroll.pay', $due) }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="col-md-6"><label class="form-label required" for="amount">Amount</label>
                            <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" max="{{ $outstanding }}" value="{{ old('amount', $outstanding) }}" required></div>
                        <div class="col-md-6"><label class="form-label" for="method">Method</label>
                            <select class="form-select" id="method" name="method">
                                @foreach($methods as $method => $label)<option value="{{ $method }}" @selected(old('method') === $method)>{{ $label }}</option>@endforeach
                            </select></div>
                        <div class="col-md-6"><label class="form-label" for="reference">Reference</label>
                            <input class="form-control" id="reference" name="reference" maxlength="255" value="{{ old('reference') }}" placeholder="Cheque or transaction number"></div>
                        <div class="col-md-6 d-flex align-items-end"><button class="btn btn-primary w-100">Record payout</button></div>
                    </form>
                @else
                    <p class="page-subtitle mb-0">This due is fully paid.</p>
                @endif
                @if(! $due->payouts->contains(fn ($payout) => ! $payout->reversal))
                    <form method="post" action="{{ route('payroll.void', $due) }}" class="mt-4" data-confirm="Void this due? It can be generated again afterwards.">
                        @csrf
                        <label class="form-label" for="void_reason">Void reason</label>
                        <div class="d-flex gap-2"><input class="form-control" id="void_reason" name="reason" minlength="5" maxlength="1000" required>
                            <button class="btn btn-outline-danger text-nowrap">Void due</button></div>
                        <p class="form-text">Use this when the amount or month was wrong, then generate it again.</p>
                    </form>
                @endif
            </div>
        </section>
    @endif
</div>
<section class="panel" aria-labelledby="payouts-heading">
    <div class="panel-heading"><h2 id="payouts-heading">Payouts</h2></div>
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Voucher</th><th scope="col">Date</th><th scope="col">Method</th><th scope="col" class="num">Amount</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Slip</span></th></tr></thead>
        <tbody>
        @forelse($due->payouts->sortByDesc('id') as $payout)
            <tr>
                <td>{{ $payout->voucher_number }}</td>
                <td>{{ $payout->paid_at->format('d M Y') }}</td>
                <td>{{ \App\Services\PayrollService::METHODS[$payout->method] ?? $payout->method }}</td>
                <td class="num">{{ currency_format($payout->amount) }}</td>
                <td><x-list.status :value="$payout->reversal ? 'reversed' : 'paid'" /></td>
                <td class="text-end"><a class="btn btn-icon" href="{{ route('payroll.slip', $payout) }}" aria-label="Slip {{ $payout->voucher_number }}"><i class="fas fa-receipt" aria-hidden="true"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No payouts yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
