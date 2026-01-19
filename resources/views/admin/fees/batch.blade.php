@extends('layouts.admin')

@section('title', 'Batch Fee Details - ' . $batch->name)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>Batch Fee Details
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('fees.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Fees
        </a>
    </div>
</div>

<!-- Batch Information -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-info-circle me-2"></i>Batch Information
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Batch Name:</strong> {{ $batch->name }}</p>
                <p><strong>Course:</strong> {{ $batch->course->name }}</p>
                <p><strong>Level:</strong> {{ $batch->course->level }}</p>
            </div>
            <div class="col-md-4">
                <p><strong>Duration:</strong> {{ $batch->course->duration_months }} months</p>
                <p><strong>Course Fees:</strong> {{ currency_format($batch->course->fees) }}</p>
                <p><strong>Total Students:</strong> {{ $batch->enrollments->count() }}</p>
            </div>
            <div class="col-md-4">
                <p><strong>Start Date:</strong> {{ $batch->start_date ? \Carbon\Carbon::parse($batch->start_date)->format('d M Y') : 'Not Set' }}</p>
                <p><strong>End Date:</strong> {{ $batch->end_date ? \Carbon\Carbon::parse($batch->end_date)->format('d M Y') : 'Not Set' }}</p>
                <p><strong>Status:</strong> {{ ucfirst($batch->status) }}</p>
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
                <h3>{{ currency_format($feeStats['total_expected'] ?? 0) }}</h3>
                <p class="card-text">Expected Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <h3>{{ currency_format($feeStats['total_collected'] ?? 0) }}</h3>
                <p class="card-text">Collected</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ currency_format($feeStats['total_pending'] ?? 0) }}</h3>
                <p class="card-text">Pending</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-primary">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-3"></i>
                <h3>{{ $feeStats['collection_rate'] ?? 0 }}%</h3>
                <p class="card-text">Collection Rate</p>
            </div>
        </div>
    </div>
</div>

<!-- Student Fee Details -->
@if($batch->enrollments->count() > 0)
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-users me-2"></i>Student Fee Breakdown
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Total Fees</th>
                            <th>Paid Amount</th>
                            <th>Pending Amount</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batch->enrollments as $enrollment)
                            @php
                                $studentTotal = $enrollment->feeInstallments->sum('amount');
                                $studentPaid = $enrollment->feeInstallments->where('status', 'paid')->sum('amount');
                                $studentPending = $enrollment->feeInstallments->where('status', 'pending')->sum('amount');
                                $paymentPercentage = $studentTotal > 0 ? round(($studentPaid / $studentTotal) * 100, 2) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $enrollment->student->name }}</strong>
                                        <br>
                                        <small class="text-muted">ID: {{ $enrollment->student->student_id }}</small>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-info">{{ currency_format($studentTotal) }}</strong>
                                </td>
                                <td>
                                    <strong class="text-success">{{ currency_format($studentPaid) }}</strong>
                                </td>
                                <td>
                                    <strong class="text-warning">{{ currency_format($studentPending) }}</strong>
                                </td>
                                <td>
                                    <div>
                                        <div class="progress mb-1" style="height: 20px;">
                                            <div class="progress-bar 
                                                {{ $paymentPercentage == 100 ? 'bg-success' : ($paymentPercentage > 50 ? 'bg-warning' : 'bg-danger') }}" 
                                                role="progressbar" 
                                                style="width: {{ $paymentPercentage }}%">
                                                {{ $paymentPercentage }}%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            @if($paymentPercentage == 100)
                                                <i class="fas fa-check-circle text-success"></i> Fully Paid
                                            @elseif($paymentPercentage > 0)
                                                <i class="fas fa-clock text-warning"></i> Partially Paid
                                            @else
                                                <i class="fas fa-times-circle text-danger"></i> Not Paid
                                            @endif
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('fees.student', $enrollment->student) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        @if($studentPending > 0)
                                            <a href="{{ route('fees.index', ['student_search' => $enrollment->student->student_id]) }}" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-money-bill-wave me-1"></i>Collect Payment
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-users fa-3x text-muted mb-3"></i>
            <h5>No Students Enrolled</h5>
            <p class="text-muted">This batch has no student enrollments yet.</p>
            <a href="{{ route('enrollments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add Student Enrollment
            </a>
        </div>
    </div>
@endif
@endsection