@extends('layouts.admin')
@section('title', 'Payment Receipt')
@section('content')
@php
    $details = $payment->receipt_details ?? [];
    $currency = $details['currency_code'] ?? 'PKR';
    $isAdmin = auth()->user()->isAdmin();
    $student = ($details['student_name'] ?? 'Historical details unavailable').(! empty($details['student_reference']) ? ' ('.$details['student_reference'].')' : '');
    $package = $payment->enrollment->packagePurchase;
    $packagePart = $package && in_array($payment->id, array_column($package->receipt_details['modules'], 'payment_id'), true);
@endphp
<div class="doc-wrap">
    <div class="doc-actions d-print-none">
        @if($isAdmin)
            <a class="btn btn-outline-secondary" href="{{ route('fees.student', $payment->enrollment->student) }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Student fees</a>
        @else
            <a class="btn btn-outline-secondary" href="{{ route('fees.student', auth()->user()) }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>My fees</a>
        @endif
        <button class="btn btn-primary ms-auto" type="button" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print receipt</button>
    </div>
    @if($packagePart)
        <div class="alert alert-info">Module allocation of an A1 + A2 package, not an additional payment. <a href="{{ route('enrollment-packages.show', $package) }}">Combined package receipt</a>. Reversing this allocation reverses both original module payments together.</div>
    @endif
    <x-show.document kind="Fee Payment Receipt" :number="$payment->receipt_number" :institute="$details['institute_name'] ?? null"
        :amount="$payment->amount" :currency="$currency" :date="$payment->paid_at?->format('d M Y, H:i') ?? 'Historical date unavailable'" :reversal="$payment->reversal"
        :rows="['Received from' => $student, 'Course' => $details['course_name'] ?? null, 'Batch' => $details['batch_name'] ?? 'Historical details unavailable',
            'Method' => ucfirst($payment->method), 'Reference' => $payment->reference]">
        @if($payment->allocations->isNotEmpty())
            <div style="padding:0 28px 18px">
                <h2 class="h6 mb-2">Applied to installments</h2>
                <table class="table table-sm mb-0">
                    <thead><tr><th scope="col">Installment due date</th><th scope="col" class="num">Allocated</th></tr></thead>
                    <tbody>
                    @foreach($payment->allocations as $allocation)
                        <tr><td>{{ $allocation->installment->due_date->format('d M Y') }}</td><td class="num">{{ $currency }} {{ number_format($allocation->amount, 2) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-show.document>
    @if($isAdmin && ! $payment->reversal && ! $payment->legacy_source)
        <details class="panel mt-4 d-print-none" style="padding:16px 22px">
            <summary class="fw-semibold" style="cursor:pointer">{{ $packagePart ? 'Reverse entire package payment' : 'Reverse this payment' }}</summary>
            <form class="mt-3" method="post" action="{{ route('payments.reverse', $payment) }}" data-confirm="{{ $packagePart ? 'Reverse both package module payments and restore both outstanding balances?' : 'Reverse this payment and restore its outstanding balance?' }}">
                @csrf
                <label class="form-label" for="reason">Reversal reason</label>
                <input class="form-control mb-2" id="reason" name="reason" minlength="5" maxlength="1000" required>
                <p class="form-text">The receipt stays on record and the installments become outstanding again.</p>
                <button class="btn btn-outline-danger">{{ $packagePart ? 'Reverse entire package payment' : 'Reverse payment' }}</button>
            </form>
        </details>
    @endif
</div>
@endsection
