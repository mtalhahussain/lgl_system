@extends('layouts.admin')

@section('title', 'Course Details - ' . $course->name)

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-book me-2"></i>{{ $course->name }}</h2>
            <p class="text-muted mb-0">Course Details and Management</p>
        </div>
        <div>
            <a href="{{ route('courses.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>Back to Courses
            </a>
            <a href="{{ route('courses.edit', $course) }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Edit Course
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
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Batches</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $course->batches->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Batches</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $course->batches->where('status', 'ongoing')->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $course->batches->sum(function($batch) { return $batch->enrollments->where('status', 'active')->count(); }) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Course Fee</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ currency_format($course->total_fee) }}</div>
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
        <!-- Course Information -->
        <div class="col-lg-8">
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Course Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Course Name:</strong>
                                <span class="text-muted">{{ $course->name }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Level:</strong>
                                <span class="badge bg-primary">{{ $course->level }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Duration:</strong>
                                <span class="text-muted">{{ $course->duration_weeks }} weeks</span>
                            </div>
                            <div class="info-item">
                                <strong>Sessions per Week:</strong>
                                <span class="text-muted">{{ $course->sessions_per_week }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Session Duration:</strong>
                                <span class="text-muted">{{ $course->session_duration_minutes }} minutes</span>
                            </div>
                            <div class="info-item">
                                <strong>Total Fee:</strong>
                                <span class="text-success font-weight-bold">{{ currency_format($course->total_fee) }}</span>
                            </div>
                            <div class="info-item">
                                <strong>Teacher Commission:</strong>
                                <span class="text-muted">{{ currency_format($course->teacher_per_student_amount) }} per student</span>
                            </div>
                            <div class="info-item">
                                <strong>Status:</strong>
                                @if($course->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($course->description)
                    <div class="mt-3">
                        <strong>Description:</strong>
                        <p class="text-muted">{{ $course->description }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Course Batches -->
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Course Batches ({{ $course->batches->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    @if($course->batches->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch Name</th>
                                        <th>Teacher</th>
                                        <th>Start Date</th>
                                        <th>Students</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($course->batches as $batch)
                                    <tr>
                                        <td>
                                            <strong>{{ $batch->name }}</strong>
                                        </td>
                                        <td>{{ $batch->teacher->name ?? 'Not Assigned' }}</td>
                                        <td>{{ $batch->start_date->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge bg-info">{{ $batch->enrollments->where('status', 'active')->count() }}/{{ $batch->max_students }}</span>
                                        </td>
                                        <td>
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
                                        </td>
                                        <td>
                                            <a href="{{ route('batches.show', $batch) }}" 
                                               class="btn btn-sm btn-outline-primary" title="View Batch">
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
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                            <h5>No Batches Created</h5>
                            <p class="text-muted">This course doesn't have any batches yet.</p>
                            <a href="{{ route('batches.create') }}?course_id={{ $course->id }}" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Create First Batch
                            </a>
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
                        <a href="{{ route('batches.create') }}?course_id={{ $course->id }}" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-1"></i>Create New Batch
                        </a>
                        <a href="{{ route('courses.edit', $course) }}" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i>Edit Course
                        </a>
                        @if($course->is_active)
                            <form method="POST" action="{{ route('courses.toggle-status', $course) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100" 
                                        onclick="return confirm('Are you sure you want to deactivate this course?')">
                                    <i class="fas fa-pause me-1"></i>Deactivate Course
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('courses.toggle-status', $course) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success w-100">
                                    <i class="fas fa-play me-1"></i>Activate Course
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Course Summary -->
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Course Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Duration:</span>
                            <span class="font-weight-bold">{{ $course->duration_weeks * $course->sessions_per_week }} sessions</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Hours:</span>
                            <span class="font-weight-bold">{{ ($course->duration_weeks * $course->sessions_per_week * $course->session_duration_minutes) / 60 }} hours</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span><strong>Fee per Hour:</strong></span>
                            <span class="font-weight-bold">{{ currency_format($course->total_fee / (($course->duration_weeks * $course->sessions_per_week * $course->session_duration_minutes) / 60)) }}</span>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <div class="d-flex justify-content-between">
                            <span><strong>Revenue per Student:</strong></span>
                            <span class="text-success font-weight-bold">{{ currency_format($course->total_fee - $course->teacher_per_student_amount) }}</span>
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