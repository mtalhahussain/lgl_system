@extends('layouts.admin')
@section('title', 'Enrollment')
@section('content')
<h1>{{ $enrollment->student->name }} — {{ $enrollment->batch->name }}</h1>
<p>Status: {{ $enrollment->status }}</p>
<p>Agreed fee: {{ currency_format($enrollment->total_fee) }}; discount: {{ currency_format($enrollment->discount_amount) }}</p>
<a class="btn btn-primary" href="{{ route('fees.student', $enrollment->student) }}">Student fees</a>
<table class="table mt-3"><thead><tr><th>Due date</th><th>Scheduled amount</th><th>Status</th></tr></thead><tbody>
    @foreach($enrollment->feeInstallments as $installment)
        <tr><td>{{ $installment->due_date->format('d M Y') }}</td><td>{{ currency_format($installment->amount) }}</td><td>{{ $installment->status }}</td></tr>
    @endforeach
</tbody></table>
@if($enrollment->status === 'active')
    <form method="post" class="card card-body mb-3" action="{{ route('enrollments.withdraw', $enrollment) }}" onsubmit="return confirm('Withdraw this enrollment? Existing debt remains due.');">
        @csrf @method('PATCH')
        <label for="dropout_reason">Withdrawal reason</label><input class="form-control mb-2" id="dropout_reason" name="dropout_reason" minlength="5" maxlength="500" required>
        <div><button class="btn btn-outline-danger">Withdraw enrollment</button></div>
    </form>
    @if($transferBatches->isNotEmpty() && $enrollment->paid_amount == 0)
        <form method="post" class="card card-body" action="{{ route('enrollments.transfer', $enrollment) }}">
            @csrf
            <label for="new_batch_id">Transfer unpaid enrollment to</label>
            <select id="new_batch_id" name="new_batch_id" class="form-select mb-2">@foreach($transferBatches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }}</option>@endforeach</select>
            <div><button class="btn btn-outline-primary">Transfer</button></div>
        </form>
    @endif
@endif
@endsection
