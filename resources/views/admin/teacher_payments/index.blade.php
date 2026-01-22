@extends('layouts.admin')
@section('content')
<div class="container">
    <h2>Teacher Payments</h2>
    <a href="{{ route('teacher_payments.create') }}" class="btn btn-primary mb-3">Record New Payment</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Teacher</th>
                <th>Salary Type</th>
                <th>Salary Amount</th>
                <th>Paid Amount</th>
                <th>Payment Date</th>
                <th>Reference</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->batchTeacherEarning->batch->name ?? '-' }}</td>
                    <td>{{ $payment->batchTeacherEarning->teacher->name ?? '-' }}</td>
                    <td>{{ ucfirst($payment->batchTeacherEarning->salary_type) }}</td>
                    <td>{{ number_format($payment->batchTeacherEarning->salary_amount, 2) }}</td>
                    <td>{{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->payment_date }}</td>
                    <td>{{ $payment->reference }}</td>
                    <td>{{ $payment->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $payments->links() }}
</div>
@endsection
