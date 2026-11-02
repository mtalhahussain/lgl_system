@extends('layouts.admin')
@section('title', 'Refund Slip')
@section('content')
@php
    $details = $refund->slip_details ?? [];
    $student = ($details['student_name'] ?? 'Historical details unavailable').(! empty($details['student_reference']) ? ' ('.$details['student_reference'].')' : '');
    $isAdmin = auth()->user()->isAdmin();
@endphp
<div class="doc-wrap">
    <div class="doc-actions d-print-none">
        @if($isAdmin)
            <a class="btn btn-outline-secondary" href="{{ route('enrollments.show', $refund->enrollment_id) }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Enrollment</a>
        @else
            <a class="btn btn-outline-secondary" href="{{ route('fees.student', auth()->user()) }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>My fees</a>
        @endif
        <button class="btn btn-primary ms-auto" type="button" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print slip</button>
    </div>
    <x-show.document kind="Fee Refund Slip" :number="$refund->voucher_number" :institute="$details['institute_name'] ?? null"
        :amount="$refund->amount" :currency="$details['currency_code'] ?? 'PKR'" :date="$refund->refunded_at->format('d M Y, H:i')"
        :rows="['Refunded to' => $student, 'Course' => $details['course_name'] ?? null, 'Batch' => $details['batch_name'] ?? null,
            'Discount now' => isset($details['discount_percentage']) ? $details['discount_percentage'].'%' : null,
            'Method' => \App\Services\PaymentService::REFUND_METHODS[$refund->method] ?? $refund->method, 'Reference' => $refund->reference,
            'Reason' => $refund->reason]" />
</div>
@endsection
