@extends('layouts.admin')

@section('title', 'My Fees')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-money-bill-wave me-2 text-success"></i>My Fees
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Fee Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card dashboard-card stat-card-info h-100" style="background-color: #17a2b8; color: white;">
                <div class="card-body text-center">
                    <i class="fas fa-calculator fa-2x mb-3"></i>
                    <h3>{{ currency_format($feeStats['total_fees'] ?? 0) }}</h3>
                    <p class="card-text">Total Fees</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card stat-card-success h-100" style="background-color: #28a745; color: white;">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x mb-3"></i>
                    <h3>{{ currency_format($feeStats['paid_fees'] ?? 0) }}</h3>
                    <p class="card-text">Paid Fees</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card stat-card-warning h-100" style="background-color: #ffc107; color: black;">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x mb-3"></i>
                    <h3>{{ currency_format($feeStats['pending_fees'] ?? 0) }}</h3>
                    <p class="card-text">Pending Fees</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card stat-card-danger h-100" style="background-color: #dc3545; color: white;">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h3>{{ currency_format($feeStats['overdue_fees'] ?? 0) }}</h3>
                    <p class="card-text">Overdue Fees</p>
                </div>
            </div>
        </div>
    </div>

    @if($feeStats['pending_fees'] > 0)
        <div class="alert alert-info" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle me-3"></i>
                <div class="flex-grow-1">
                    <strong>Pending Payment</strong>
                    You have {{ currency_format($feeStats['pending_fees']) }} in pending fees.
                    Please contact the office for payment processing.
                    @if($feeStats['overdue_fees'] > 0)
                        <br><small class="text-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            {{ currency_format($feeStats['overdue_fees']) }} is overdue. Please pay immediately.
                        </small>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Course-wise Fee Details -->
    @if($student->enrollments->count() > 0)
        @foreach($student->enrollments as $enrollment)
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-book me-2"></i>{{ $enrollment->batch->course->name }}
                        </h5>
                        <span class="badge bg-light text-dark">{{ $enrollment->batch->name }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <h6 class="text-muted">Course Level</h6>
                            <p class="mb-0">{{ $enrollment->batch->course->level }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Duration</h6>
                            <p class="mb-0">{{ $enrollment->batch->course->duration_weeks }} weeks</p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Course Fees</h6>
                            <p class="mb-0 fw-bold text-primary">{{ currency_format($enrollment->batch->course->total_fee) }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Payment Status</h6>
                            @php
                                $coursePaid = $enrollment->feeInstallments->where('status', 'paid')->sum('amount');
                                $courseTotal = $enrollment->feeInstallments->sum('amount');
                                $percentage = $courseTotal > 0 ? round(($coursePaid / $courseTotal) * 100, 1) : 0;
                            @endphp
                            <div class="progress mb-1" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentage }}%"></div>
                            </div>
                            <small class="text-muted">{{ $percentage }}% Paid ({{ currency_format($coursePaid) }} / {{ currency_format($courseTotal) }})</small>
                        </div>
                    </div>

                    @if($enrollment->feeInstallments->count() > 0)
                        <h6 class="mb-3">
                            <i class="fas fa-list me-2"></i>Payment Schedule
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Installment #</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Payment Date</th>
                                        <th>Payment Method</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($enrollment->feeInstallments->sortBy('due_date') as $installment)
                                        @php
                                            $isOverdue = $installment->status === 'pending' && $installment->due_date < now();
                                            $isDueSoon = $installment->status === 'pending' && $installment->due_date <= now()->addDays(7);
                                        @endphp
                                        <tr class="{{ $isOverdue ? 'table-danger' : ($isDueSoon ? 'table-warning' : '') }}">
                                            <td>
                                                <span class="badge bg-primary">
                                                    #{{ $loop->iteration }}
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-success">{{ currency_format($installment->amount) }}</strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>{{ $installment->due_date->format('d M Y') }}</strong>
                                                    @if($isOverdue)
                                                        <br>
                                                        <small class="text-danger">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                            Overdue by {{ $installment->due_date->diffForHumans(null, true) }}
                                                        </small>
                                                    @elseif($isDueSoon && $installment->status === 'pending')
                                                        <br>
                                                        <small class="text-warning">
                                                            <i class="fas fa-clock"></i>
                                                            Due {{ $installment->due_date->diffForHumans() }}
                                                        </small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($installment->status === 'paid')
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Paid
                                                    </span>
                                                @elseif($isOverdue)
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> Overdue
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock"></i> Pending
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($installment->status === 'paid' && $installment->payment_date)
                                                    <span class="text-success">
                                                        {{ $installment->payment_date->format('d M Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($installment->status === 'paid' && $installment->payment_method)
                                                    <span class="badge bg-info">
                                                        {{ ucfirst($installment->payment_method) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($installment->status === 'pending')
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock"></i> Contact Office
                                                    </span>
                                                @else
                                                    <span class="text-success">
                                                        <i class="fas fa-check-circle"></i> Completed
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            No payment schedule found for this course.
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <div class="alert alert-warning" role="alert">
            <div class="text-center py-4">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5>No Course Enrollments</h5>
                <p class="text-muted">You are not enrolled in any courses yet.</p>
            </div>
        </div>
    @endif
</div>



<style>
.dashboard-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out;
}

.dashboard-card:hover {
    transform: translateY(-2px);
}

.stat-card-info {
    border-left: 4px solid #17a2b8;
}

.stat-card-success {
    border-left: 4px solid #28a745;
}

.stat-card-warning {
    border-left: 4px solid #ffc107;
}

.stat-card-danger {
    border-left: 4px solid #dc3545;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
}

.progress {
    background-color: #e9ecef;
}
</style>
@endsection