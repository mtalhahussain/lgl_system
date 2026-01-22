@extends('layouts.admin')

@section('title', 'Fee Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill me-2"></i>Fee Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('installments.create') }}" class="btn btn-success me-2">
            <i class="fas fa-plus me-1"></i>Create Installments
        </a>
        <a href="{{ route('fees.reports') }}" class="btn btn-primary">
            <i class="fas fa-chart-bar me-1"></i>Fee Reports
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['total_paid'] ?? 0) }}</h3>
                <p class="card-text">Total Paid</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['total_pending'] ?? 0) }}</h3>
                <p class="card-text">Pending Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h3>{{ $stats['overdue_count'] ?? 0 }}</h3>
                <p class="card-text">Overdue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['monthly_collection'] ?? 0) }}</h3>
                <p class="card-text">This Month</p>
            </div>
        </div>
    </div>
</div>

<div class="card dashboard-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Fee Management
        </h5>
    </div>
    <div class="card-body">
        <div class="text-center py-5">
            <i class="fas fa-money-bill fa-3x text-muted mb-3"></i>
            <h5>Fee Management System</h5>
            <p class="text-muted">Track payments, installments, and generate fee reports.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="{{ route('fees.reports') }}" class="btn btn-primary">
                    <i class="fas fa-chart-bar me-1"></i>View Reports
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Fee Installments List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-money-bill-wave me-2"></i>Fee Installments
        </h5>
        <span class="badge bg-primary">{{ $installments->total() }} Total</span>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Student Search</label>
                        <input type="text" name="student_search" class="form-control" 
                               value="{{ request('student_search') }}" placeholder="Student name or ID">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select">
                            <option value="">All Courses</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>
                                    {{ $course->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="{{ request('due_date') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($installments->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course & Batch</th>
                            <th>Installment</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($installments as $installment)
                            @php
                                $isOverdue = $installment->status === 'pending' && $installment->due_date < now();
                            @endphp
                            <tr class="{{ $isOverdue ? 'table-warning' : '' }}">
                                <td>
                                    <div>
                                        <strong>{{ $installment->enrollment->student->name }}</strong>
                                        <br>
                                        <small class="text-muted">ID: {{ $installment->enrollment->student->student_id }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong>{{ $installment->enrollment->batch->course->name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $installment->enrollment->batch->name }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        Installment {{ $installment->installment_number ?? '#' . $installment->id }}
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
                                    @if($installment->status === 'paid' && $installment->paid_date)
                                        <br>
                                        <small class="text-muted">Paid: {{ \Carbon\Carbon::parse($installment->paid_date)->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($installment->status === 'pending')
                                        <button class="btn btn-success btn-sm" 
                                                onclick="payInstallmentModal({{ $installment->id }}, '{{ $installment->enrollment->student->name }}', '{{ currency_format($installment->amount) }}')">
                                            <i class="fas fa-money-bill-wave me-1"></i>Pay Now
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
            
            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                    @if($installments->hasPages())
                        Showing {{ $installments->firstItem() }} to {{ $installments->lastItem() }} of {{ $installments->total() }} results
                    @else
                        Showing all {{ $installments->total() }} results
                    @endif
                </div>
                <div class="pagination-wrapper">
                    {{ $installments->appends(request()->query())->links('pagination::bootstrap-4') }}
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                <h5>No Fee Installments Found</h5>
                <p class="text-muted">No installments match your search criteria.</p>
            </div>
        @endif
    </div>
</div>

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
                               placeholder="Auto-generated" value="{{ uniqid('TXN') }}" readonly>
                        <small class="text-muted">This will be auto-generated and unique.</small>
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

<style>
/* Pagination Styling */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
}

.pagination {
    margin: 0;
}

.pagination .page-link {
    color: #495057;
    border: 1px solid #dee2e6;
    padding: 0.5rem 0.75rem;
    margin: 0 0.125rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}

.pagination .page-link:hover {
    color: #0056b3;
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
    background-color: white;
    border-color: #dee2e6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .pagination .page-link {
        padding: 0.375rem 0.5rem;
        font-size: 0.875rem;
    }
}
</style>
@endsection