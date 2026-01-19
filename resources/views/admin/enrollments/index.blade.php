@extends('layouts.admin')

@section('title', 'Enrollments Management')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-users me-2"></i>Enrollments Management</h2>
            <p class="text-muted mb-0">Manage student enrollments and batch assignments</p>
        </div>
        <div>
            <a href="{{ route('enrollments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>New Enrollment
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card dashboard-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('enrollments.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="transferred" {{ request('status') === 'transferred' ? 'selected' : '' }}>Transferred</option>
                        <option value="withdrawn" {{ request('status') === 'withdrawn' ? 'selected' : '' }}>Withdrawn</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="batch_id" class="form-label">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id">
                        <option value="">All Batches</option>
                        @foreach(\App\Models\Batch::with('course')->get() as $batch)
                            <option value="{{ $batch->id }}" {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
                                {{ $batch->name }} - {{ $batch->course->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Student</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="{{ request('search') }}" placeholder="Search by student name or email...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                        <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Enrollments Table -->
    <div class="card dashboard-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Enrollments List ({{ $enrollments->total() }})
            </h5>
        </div>
        <div class="card-body">
            @if($enrollments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Batch</th>
                                <th>Course</th>
                                <th>Enrollment Date</th>
                                <th>Status</th>
                                <th>Fee Status</th>
                                <th>Payment Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($enrollments as $enrollment)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $enrollment->student->name ?? 'N/A' }}</strong>
                                        <small class="d-block text-muted">{{ $enrollment->student->email ?? 'N/A' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $enrollment->batch->name ?? 'N/A' }}</span>
                                    <small class="d-block text-muted">
                                        {{ $enrollment->batch->start_date ? $enrollment->batch->start_date->format('M d, Y') : 'TBD' }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $enrollment->batch->course->name ?? 'N/A' }}</span>
                                    <small class="d-block text-muted">{{ $enrollment->batch->course->level ?? '' }}</small>
                                </td>
                                <td>
                                    <i class="fas fa-calendar me-1"></i>{{ $enrollment->created_at->format('M d, Y') }}
                                    <small class="d-block text-muted">{{ $enrollment->created_at->diffForHumans() }}</small>
                                </td>
                                <td>
                                    @switch($enrollment->status)
                                        @case('active')
                                            <span class="badge bg-success">Active</span>
                                            @break
                                        @case('completed')
                                            <span class="badge bg-secondary">Completed</span>
                                            @break
                                        @case('transferred')
                                            <span class="badge bg-warning">Transferred</span>
                                            @break
                                        @case('withdrawn')
                                            <span class="badge bg-danger">Withdrawn</span>
                                            @break
                                        @default
                                            <span class="badge bg-light text-dark">{{ ucfirst($enrollment->status) }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    @php
                                        $remainingFee = $enrollment->remaining_fee ?? 0;
                                        $totalFee = $enrollment->batch->course->total_fee ?? 0;
                                        $paidFee = $totalFee - $remainingFee;
                                    @endphp
                                    @if($remainingFee > 0)
                                        <span class="badge bg-warning">
                                            {{ currency_format($remainingFee) }} Due
                                        </span>
                                    @else
                                        <span class="badge bg-success">Paid</span>
                                    @endif
                                    <small class="d-block text-muted">
                                        {{ currency_format($paidFee) }} / {{ currency_format($totalFee) }}
                                    </small>
                                </td>
                                <td>
                                    @php
                                        $progress = $enrollment->payment_progress ?? 0;
                                    @endphp
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar {{ $progress >= 100 ? 'bg-success' : ($progress >= 50 ? 'bg-warning' : 'bg-danger') }}" 
                                             role="progressbar" 
                                             style="width: {{ $progress }}%"
                                             aria-valuenow="{{ $progress }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            {{ number_format($progress, 1) }}%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('enrollments.show', $enrollment) }}" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($enrollment->status === 'active')
                                            <a href="{{ route('enrollments.edit', $enrollment) }}" 
                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('students.show', $enrollment->student) }}">
                                                        <i class="fas fa-user me-1"></i>View Student
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('batches.show', $enrollment->batch) }}">
                                                        <i class="fas fa-calendar-alt me-1"></i>View Batch
                                                    </a>
                                                </li>
                                                @if($enrollment->status === 'active')
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="showTransferModal({{ $enrollment->id }}, '{{ $enrollment->student->name }}')">
                                                            <i class="fas fa-exchange-alt me-1"></i>Transfer Batch
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#"
                                                           onclick="showWithdrawModal({{ $enrollment->id }}, '{{ $enrollment->student->name }}')">
                                                            <i class="fas fa-user-times me-1"></i>Withdraw Student
                                                        </a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing {{ $enrollments->firstItem() ?? 0 }} to {{ $enrollments->lastItem() ?? 0 }} 
                        of {{ $enrollments->total() }} results
                    </div>
                    <div>
                        {{ $enrollments->appends(request()->query())->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                    <h5>No Enrollments Found</h5>
                    <p class="text-muted">No enrollments match your current filters.</p>
                    <a href="{{ route('enrollments.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create First Enrollment
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Student to Another Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="transferForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Transfer <strong id="transferStudentName"></strong> to another batch?</p>
                    <div class="mb-3">
                        <label for="new_batch_id" class="form-label">New Batch</label>
                        <select class="form-select" id="new_batch_id" name="new_batch_id" required>
                            <option value="">Select New Batch</option>
                            @foreach(\App\Models\Batch::with('course')->where('status', 'upcoming')->orWhere('status', 'ongoing')->get() as $batch)
                                <option value="{{ $batch->id }}">
                                    {{ $batch->name }} - {{ $batch->course->name }} ({{ $batch->course->level }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Transfer Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Withdraw Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="withdrawForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-body">
                    <p>Are you sure you want to withdraw <strong id="withdrawStudentName"></strong> from this batch?</p>
                    <div class="mb-3">
                        <label for="withdrawal_reason" class="form-label">Reason for Withdrawal (Optional)</label>
                        <textarea class="form-control" id="withdrawal_reason" name="withdrawal_reason" rows="3"></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This action will change the enrollment status to "withdrawn" and may affect fee calculations.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Withdraw Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTransferModal(enrollmentId, studentName) {
    document.getElementById('transferStudentName').textContent = studentName;
    document.getElementById('transferForm').action = `/enrollments/${enrollmentId}/transfer`;
    new bootstrap.Modal(document.getElementById('transferModal')).show();
}

function showWithdrawModal(enrollmentId, studentName) {
    document.getElementById('withdrawStudentName').textContent = studentName;
    document.getElementById('withdrawForm').action = `/enrollments/${enrollmentId}/withdraw`;
    new bootstrap.Modal(document.getElementById('withdrawModal')).show();
}
</script>
@endsection