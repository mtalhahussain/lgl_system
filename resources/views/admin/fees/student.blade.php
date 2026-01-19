@extends('layouts.admin')

@section('title', 'Student Fee Details - ' . $student->name)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-graduate me-2"></i>Student Fee Details
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('fees.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Fees
        </a>
    </div>
</div>

<!-- Student Information -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-user me-2"></i>Student Information
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong> {{ $student->name }}</p>
                <p><strong>Student ID:</strong> {{ $student->student_id }}</p>
                <p><strong>Email:</strong> {{ $student->email }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Phone:</strong> {{ $student->phone ?? 'N/A' }}</p>
                <p><strong>Address:</strong> {{ $student->address ?? 'N/A' }}</p>
                <p><strong>Emergency Contact:</strong> {{ $student->emergency_contact ?? 'N/A' }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Fee Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-calculator fa-2x mb-3"></i>
                <h3>{{ currency_format($feeStats['total_fees'] ?? 0) }}</h3>
                <p class="card-text">Total Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <h3>{{ currency_format($feeStats['paid_fees'] ?? 0) }}</h3>
                <p class="card-text">Paid Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ currency_format($feeStats['pending_fees'] ?? 0) }}</h3>
                <p class="card-text">Pending Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h3>{{ currency_format($feeStats['overdue_fees'] ?? 0) }}</h3>
                <p class="card-text">Overdue Fees</p>
            </div>
        </div>
    </div>
</div>

<!-- Enrollments and Fee Details -->
@if($student->enrollments->count() > 0)
    @foreach($student->enrollments as $enrollment)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book me-2"></i>{{ $enrollment->batch->course->name }} - {{ $enrollment->batch->name }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Course:</strong> {{ $enrollment->batch->course->name }}
                    </div>
                    <div class="col-md-3">
                        <strong>Level:</strong> {{ $enrollment->batch->course->level }}
                    </div>
                    <div class="col-md-3">
                        <strong>Duration:</strong> {{ $enrollment->batch->course->duration_months }} months
                    </div>
                    <div class="col-md-3">
                        <strong>Course Fees:</strong> {{ currency_format($enrollment->batch->course->fees) }}
                    </div>
                </div>

                @if($enrollment->feeInstallments->count() > 0)
                    <h6 class="mb-3"><i class="fas fa-list me-2"></i>Fee Installments</h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Installment</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Payment Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($enrollment->feeInstallments as $installment)
                                    @php
                                        $isOverdue = $installment->status === 'pending' && $installment->due_date < now();
                                    @endphp
                                    <tr class="{{ $isOverdue ? 'table-warning' : '' }}">
                                        <td>
                                            <span class="badge bg-info">
                                                #{{ $installment->id }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-primary">{{ currency_format($installment->amount) }}</strong>
                                        </td>
                                        <td>
                                            <div>
                                                {{ $installment->due_date->format('d M Y') }}
                                                @if($isOverdue)
                                                    <br>
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        {{ $installment->due_date->diffForHumans() }}
                                                    </small>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $installment->status === 'paid' ? 'success' : ($isOverdue ? 'danger' : 'warning') }}">
                                                {{ $isOverdue && $installment->status === 'pending' ? 'Overdue' : ucfirst($installment->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($installment->status === 'paid')
                                                <div>
                                                    <small><strong>Paid:</strong> {{ $installment->paid_date ? \Carbon\Carbon::parse($installment->paid_date)->format('d M Y') : 'N/A' }}</small><br>
                                                    @if($installment->payment_method)
                                                        <small><strong>Method:</strong> {{ ucfirst($installment->payment_method) }}</small><br>
                                                    @endif
                                                    @if($installment->transaction_reference)
                                                        <small><strong>Reference:</strong> {{ $installment->transaction_reference }}</small>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted">Not paid yet</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($installment->status === 'pending')
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="payInstallmentModal({{ $installment->id }}, '{{ $student->name }}', '{{ currency_format($installment->amount) }}')">
                                                    <i class="fas fa-money-bill-wave me-1"></i>Pay
                                                </button>
                                            @else
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle"></i> Paid
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No installments created for this enrollment yet. 
                        <a href="{{ route('installments.create') }}" class="btn btn-sm btn-success ms-2">
                            <i class="fas fa-plus me-1"></i>Create Installments
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
            <h5>No Enrollments Found</h5>
            <p class="text-muted">This student has no course enrollments yet.</p>
            <a href="{{ route('enrollments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Create Enrollment
            </a>
        </div>
    </div>
@endif

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-money-bill-wave me-2"></i>Process Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" method="POST">
                    @csrf
                    <div class="mb-3">
                        <strong>Student:</strong> <span id="studentName"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Amount:</strong> <span id="installmentAmount"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" name="transaction_reference" class="form-control" 
                               placeholder="Receipt number, transaction ID, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Any additional notes about this payment"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="paymentForm" class="btn btn-success">
                    <i class="fas fa-check me-1"></i>Process Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function payInstallmentModal(installmentId, studentName, amount) {
    document.getElementById('studentName').textContent = studentName;
    document.getElementById('installmentAmount').textContent = amount;
    document.getElementById('paymentForm').action = `/fees/${installmentId}/pay`;
    
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}
</script>
@endsection