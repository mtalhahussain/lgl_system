@extends('layouts.admin')

@section('title', 'Batch Details - ' . $batch->name)

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-calendar-alt me-2"></i>{{ $batch->name }}</h2>
            <p class="text-muted mb-0">Batch Details and Management</p>
        </div>
        <div>
            <a href="{{ url('/batches') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>Back to Batches
            </a>
            <a href="{{ url('/batches/' . $batch->id . '/edit') }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Edit Batch
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Enrolled Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['enrolled_students'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-success">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Spots</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['available_spots'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-info">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Sessions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_sessions'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Fees Collected</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ currency_format($stats['total_fees_collected']) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Batch Information -->
        <div class="col-lg-8">
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Batch Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Course:</strong>
                                <span class="badge bg-primary">{{ $batch->course->name ?? 'N/A' }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Level:</strong>
                                <span class="text-muted">{{ $batch->course->level ?? 'N/A' }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Teacher:</strong>
                                <span class="text-muted">{{ $batch->teacher->name ?? 'Not Assigned' }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Status:</strong>
                                @switch($batch->status)
                                    @case('upcoming')
                                        <span class="badge bg-warning">Upcoming</span>
                                        @break
                                    @case('ongoing')
                                        <span class="badge bg-success">Ongoing</span>
                                        @break
                                    @case('completed')
                                        <span class="badge bg-secondary">Completed</span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge bg-danger">Cancelled</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">{{ ucfirst($batch->status) }}</span>
                                @endswitch
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Start Date:</strong>
                                <span class="text-muted">{{ $batch->start_date->format('M d, Y') }}</span>
                            </div>
                            <div class="info-item">
                                <strong>End Date:</strong>
                                <span class="text-muted">{{ $batch->end_date ? $batch->end_date->format('M d, Y') : 'Not Set' }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Max Students:</strong>
                                <span class="text-muted">{{ $batch->max_students }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Meeting Platform:</strong>
                                @switch($batch->meeting_platform)
                                    @case('zoom')
                                        <i class="fas fa-video text-primary"></i> Zoom
                                        @break
                                    @case('google_meet')
                                        <i class="fas fa-video text-success"></i> Google Meet
                                        @break
                                    @case('in_person')
                                        <i class="fas fa-users text-info"></i> In Person
                                        @break
                                    @default
                                        {{ ucfirst(str_replace('_', ' ', $batch->meeting_platform)) }}
                                @endswitch
                            </div>
                        </div>
                    </div>

                    @if($batch->meeting_link)
                    <div class="mt-3">
                        <strong>Meeting Link:</strong>
                        <a href="{{ $batch->meeting_link }}" target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fas fa-external-link-alt me-1"></i>Join Meeting
                        </a>
                    </div>
                    @endif

                    @if($batch->notes)
                    <div class="mt-3">
                        <strong>Notes:</strong>
                        <p class="text-muted">{{ $batch->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Enrolled Students -->
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Enrolled Students ({{ $batch->enrollments->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    @if($batch->enrollments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Email</th>
                                        <th>Enrollment Date</th>
                                        <th>Status</th>
                                        <th>Fee Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($batch->enrollments as $enrollment)
                                    <tr>
                                        <td>
                                            <strong>{{ $enrollment->student->name ?? 'N/A' }}</strong>
                                        </td>
                                        <td>{{ $enrollment->student->email ?? 'N/A' }}</td>
                                        <td>{{ $enrollment->created_at->format('M d, Y') }}</td>
                                        <td>
                                            @if($enrollment->status === 'active')
                                                <span class="badge bg-success">Active</span>
                                            @elseif($enrollment->status === 'completed')
                                                <span class="badge bg-secondary">Completed</span>
                                            @else
                                                <span class="badge bg-warning">{{ ucfirst($enrollment->status) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($enrollment->feeInstallments->isNotEmpty())
                                                @php
                                                    $totalFees = $enrollment->feeInstallments->sum('amount');
                                                    $paidFees = $enrollment->feeInstallments->where('status', 'paid')->sum('amount');
                                                    $pendingFees = $enrollment->feeInstallments->where('status', 'pending')->sum('amount');
                                                @endphp
                                                @if($pendingFees > 0)
                                                    <span class="badge bg-warning">{{ currency_format($pendingFees) }} Pending</span>
                                                @else
                                                    <span class="badge bg-success">Paid</span>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-dark">No Fees</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('/students/' . $enrollment->student->id) }}" 
                                               class="btn btn-sm btn-outline-primary" title="View Student">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                            <h5>No Students Enrolled</h5>
                            <p class="text-muted">This batch doesn't have any enrolled students yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ url('/enrollments/create?batch_id=' . $batch->id) }}" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i>Add Student
                        </a>
                        <a href="{{ url('/class-sessions/create?batch_id=' . $batch->id) }}" class="btn btn-success">
                            <i class="fas fa-calendar-plus me-1"></i>Schedule Session
                        </a>
                        <a href="{{ url('/fees/batch/' . $batch->id) }}" class="btn btn-info">
                            <i class="fas fa-money-bill-wave me-1"></i>Manage Fees
                        </a>
                        @if($batch->meeting_link)
                            <a href="{{ $batch->meeting_link }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-video me-1"></i>Start Meeting
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Fee Summary -->
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Fee Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Collected:</span>
                            <span class="text-success font-weight-bold">{{ currency_format($stats['total_fees_collected']) }}</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Pending:</span>
                            <span class="text-warning font-weight-bold">{{ currency_format($stats['pending_fees']) }}</span>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <div class="d-flex justify-content-between">
                            <span><strong>Total:</strong></span>
                            <span class="font-weight-bold">{{ currency_format($stats['total_fees_collected'] + $stats['pending_fees']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.info-item {
    margin-bottom: 0.75rem;
}

.stats-card {
    border-left-width: 0.25rem !important;
}

.border-left-primary {
    border-left-color: #4e73df !important;
}

.border-left-success {
    border-left-color: #1cc88a !important;
}

.border-left-info {
    border-left-color: #36b9cc !important;
}

.border-left-warning {
    border-left-color: #f6c23e !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}
</style>
@endsection