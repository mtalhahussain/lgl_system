@extends('layouts.admin')
@section('title', 'Package Receipt')
@section('content')
@php
    $details = $package->receipt_details;
    $currency = $details['currency_code'];
    $full = $package->isFullAdvance();
    $reversal = $full ? $payments->first()?->reversal : null;
    $levels = implode(' + ', array_column($details['modules'], 'level'));
@endphp
<div class="doc-wrap">
    <div class="doc-actions d-print-none">
        <a class="btn btn-outline-secondary" href="{{ route('fees.student', $package->student_id) }}">{{ auth()->user()->isAdmin() ? 'Student fees' : 'My fees' }}</a>
        <button class="btn btn-primary ms-auto" type="button" onclick="window.print()">Print</button>
    </div>
    <x-show.document :kind="$levels.($full ? ' Package Receipt' : ' Package Agreement')" :number="$package->receipt_number" :institute="$details['institute_name']"
        :amount="$package->amount" :currency="$currency" :date="$package->created_at->format('d M Y, h:i A')" :reversal="$reversal"
        :rows="['Received from' => $details['student_name'], 'Student reference' => $details['student_reference'], 'Normal fees' => isset($details['gross_total']) ? $currency.' '.number_format($details['gross_total'], 2) : null, 'Package discount' => isset($details['discount_total']) ? $currency.' '.number_format($details['discount_total'], 2) : null, 'Payment' => $full ? 'Full advance payment' : ($details['installments'].' monthly installments per level'), 'Method' => $full ? ucfirst($package->method) : null, 'Reference' => $package->reference]">
        <div class="p-4 table-responsive">
            <h2 class="h6">Module allocation</h2>
            <table class="table" style="min-width:720px">
                <thead><tr><th scope="col">Module / batch</th><th scope="col">Dates</th><th scope="col" class="num">Normal fee</th><th scope="col" class="num">Discount</th><th scope="col" class="num">{{ $full ? 'Paid allocation' : 'Package share' }}</th></tr></thead>
                <tbody>
                @foreach($details['modules'] as $module)
                    <tr>
                        <td>{{ $module['course'] }} ({{ $module['level'] }})<span class="cell-sub">{{ $module['batch'] }}</span>@if($module['payment_id'])<a class="d-print-none cell-sub" href="{{ route('payments.show', $module['payment_id']) }}">{{ $module['receipt_number'] }}</a>@endif
                            @if(! $full && ! empty($module['installments']))<span class="cell-sub">@foreach($module['installments'] as $row){{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}: {{ $currency }} {{ number_format($row['amount'], 2) }}@if(! $loop->last) · @endif @endforeach</span>@endif</td>
                        <td>{{ $module['start_date'] }} – {{ $module['end_date'] ?? 'Not set' }}</td>
                        <td class="num">{{ $currency }} {{ number_format($module['gross'], 2) }}</td>
                        <td class="num">{{ $currency }} {{ number_format($module['discount'], 2) }}</td>
                        <td class="num">{{ $currency }} {{ number_format($module['amount'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="form-text mb-0">@if($full)One payment, split between the level ledgers. This summary is not an additional collection.@else Agreement only: each level's installments are collected and receipted from the fee ledger.@endif Original agreed fees and dates are shown.</p>
        </div>
    </x-show.document>
    @if(auth()->user()->isAdmin() && $full && $payments->isNotEmpty() && ! $reversal)
        <details class="panel p-4 mt-4 d-print-none">
            <summary class="fw-semibold">Reverse entire package payment</summary>
            <form class="mt-3" method="post" action="{{ route('payments.reverse', $payments->first()) }}" data-confirm="Reverse all original package payments and restore every level balance?">
                @csrf
                <label class="form-label" for="reason">Reason</label>
                <input class="form-control mb-3" id="reason" name="reason" minlength="5" maxlength="1000" required>
                <p class="form-text">All level allocations reverse together. Original receipt, agreed fees and enrollments remain. This corrects a recorded payment; it does not cancel a course or issue a refund.</p>
                <button class="btn btn-outline-danger">Reverse full package payment</button>
            </form>
        </details>
    @endif
</div>
@endsection
