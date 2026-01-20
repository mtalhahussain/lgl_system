@extends('layouts.admin')

@section('title', 'Student Attendance - ' . $student->name)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>Student Attendance
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Attendance
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

<!-- Attendance Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x mb-3 text-info"></i>
                <h3>{{ $attendanceStats['total_sessions'] }}</h3>
                <p class="card-text">Total Sessions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                <h3>{{ $attendanceStats['present'] }}</h3>
                <p class="card-text">Present</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-danger">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x mb-3 text-danger"></i>
                <h3>{{ $attendanceStats['absent'] }}</h3>
                <p class="card-text">Absent</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3 text-warning"></i>
                <h3>{{ $attendanceStats['attendance_rate'] }}%</h3>
                <p class="card-text">Attendance Rate</p>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Records -->
@if($student->attendances->count() > 0)
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>Attendance Records
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Session Time</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($student->attendances->sortByDesc('created_at') as $attendance)
                            <tr>
                                <td>
                                    <strong>{{ $attendance->classSession->session_date->format('d M Y') }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $attendance->classSession->session_date->format('l') }}</small>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $attendance->classSession->batch->course->name }}</span>
                                    <br>
                                    <small class="text-muted">{{ $attendance->classSession->batch->course->level }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $attendance->classSession->batch->name }}</span>
                                </td>
                                <td>
                                    {{ $attendance->classSession->start_time }} - {{ $attendance->classSession->end_time }}
                                    <br>
                                    <small class="text-muted">
                                        @if($attendance->classSession->is_online)
                                            <i class="fas fa-video text-info"></i> Online Class
                                        @else
                                            <i class="fas fa-chalkboard-teacher text-secondary"></i> Physical Class
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    @switch($attendance->status)
                                        @case('present')
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Present
                                            </span>
                                            @break
                                        @case('absent')
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times"></i> Absent
                                            </span>
                                            @break
                                        @case('late')
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock"></i> Late
                                            </span>
                                            @break
                                        @case('excused')
                                            <span class="badge bg-info">
                                                <i class="fas fa-user-check"></i> Excused
                                            </span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">Unknown</span>
                                    @endswitch
                                </td>
                                <td>
                                    @if($attendance->marked_by)
                                        {{ $attendance->markedBy->name }}
                                        <br>
                                        <small class="text-muted">{{ $attendance->created_at->format('H:i') }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->notes)
                                        <small>{{ $attendance->notes }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info text-center" role="alert">
        <div class="py-4">
            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
            <h5>No Attendance Records</h5>
            <p class="text-muted">No attendance records found for this student.</p>
        </div>
    </div>
@endif

<!-- Course-wise Attendance Summary -->
@if($student->enrollments->count() > 0)
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-chart-pie me-2"></i>Course-wise Attendance Summary
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($student->enrollments as $enrollment)
                    @php
                        $courseAttendances = $student->attendances->where('classSession.batch_id', $enrollment->batch_id);
                        $totalSessions = $courseAttendances->count();
                        $presentSessions = $courseAttendances->whereIn('status', ['present', 'late'])->count();
                        $attendanceRate = $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0;
                    @endphp
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">{{ $enrollment->batch->course->name }}</h6>
                                <p class="card-text">
                                    <span class="badge bg-primary">{{ $enrollment->batch->name }}</span>
                                </p>
                                <div class="progress mb-2" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: {{ $attendanceRate }}%" 
                                         aria-valuenow="{{ $attendanceRate }}" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">{{ $presentSessions }}/{{ $totalSessions }} sessions</small>
                                    <small class="fw-bold">{{ $attendanceRate }}%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<style>
.dashboard-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-3px);
}

.stat-card-info {
    border-left: 4px solid #17a2b8;
}

.stat-card-success {
    border-left: 4px solid #28a745;
}

.stat-card-danger {
    border-left: 4px solid #dc3545;
}

.stat-card-warning {
    border-left: 4px solid #ffc107;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.progress {
    background-color: #e9ecef;
}

.badge {
    font-size: 0.75rem;
}

.card-title {
    font-size: 1rem;
    font-weight: 600;
}
</style>
@endsection