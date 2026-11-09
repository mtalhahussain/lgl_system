@extends('layouts.admin')
@section('title', 'Payment Slip')
@section('content')
@php
    $details = $payout->slip_details ?? [];
    $teacher = ($details['teacher_name'] ?? 'Historical details unavailable').(! empty($details['teacher_reference']) ? ' ('.$details['teacher_reference'].')' : '');
@endphp
<div class="doc-wrap">
    <div class="doc-actions d-print-none">
        <a class="btn btn-outline-secondary" href="{{ route('payroll.show', $payout->due) }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back to due</a>
        <button class="btn btn-primary ms-auto" type="button" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print slip</button>
    </div>
    <x-show.document kind="Salary Payment Slip" :number="$payout->voucher_number" :institute="$details['institute_name'] ?? null"
        :amount="$payout->amount" :currency="$details['currency_code'] ?? 'PKR'" :date="$payout->paid_at->format('d M Y, h:i A')" :reversal="$payout->reversal"
        :rows="['Paid to' => $teacher, 'For' => $details['description'] ?? null,
            'Due amount' => isset($details['due_amount']) ? ($details['currency_code'] ?? 'PKR').' '.number_format((float) $details['due_amount'], 2) : null,
            'Method' => \App\Services\PayrollService::METHODS[$payout->method] ?? $payout->method, 'Reference' => $payout->reference]" />
    @if(auth()->user()->isAdmin() && ! $payout->reversal)
        <details class="panel mt-4 d-print-none" style="padding:16px 22px">
            <summary class="fw-semibold" style="cursor:pointer">Reverse this payout</summary>
            <form class="mt-3" method="post" action="{{ route('payroll.reverse', $payout) }}" data-confirm="Reverse this payout and restore the outstanding amount?">
                @csrf
                <label class="form-label" for="reason">Reversal reason</label>
                <input class="form-control mb-2" id="reason" name="reason" minlength="5" maxlength="1000" required>
                <p class="form-text">The slip stays on record and the amount becomes outstanding again.</p>
                <button class="btn btn-outline-danger">Reverse payout</button>
            </form>
        </details>
    @endif
</div>
@endsection
