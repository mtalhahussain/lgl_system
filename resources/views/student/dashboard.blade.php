@extends('layouts.admin')

@section('title', 'Student Dashboard')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-graduate me-2"></i>Welcome, {{ $student->name }}!
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <span class="badge bg-primary fs-6">Student ID: {{ $student->student_id }}</span>
        </div>
    </div>
</div>

<!-- Today's Classes Alert -->
@if($todayClasses->count() > 0)
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-calendar-day fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">You have {{ $todayClasses->count() }} {{ $todayClasses->count() == 1 ? 'class' : 'classes' }} today!</h5>
                <p class="mb-0">Don't forget to attend your scheduled classes.</p>
            </div>
        </div>
    </div>
@endif

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-primary">
            <div class="card-body text-center">
                <i class="fas fa-book-open fa-2x mb-3"></i>
                <h3>{{ $stats['active_enrollments'] ?? 0 }}</h3>
                <p class="card-text">Active Courses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <h3>{{ $stats['classes_attended'] ?? 0 }}/{{ $stats['total_classes'] ?? 0 }}</h3>
                <p class="card-text">Classes Attended</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-3"></i>
                <h3>{{ number_format($stats['attendance_rate'] ?? 0, 1) }}%</h3>
                <p class="card-text">Attendance Rate</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card {{ ($stats['pending_fees'] ?? 0) > 0 ? 'stat-card-warning' : 'stat-card-success' }}">
            <div class="card-body text-center">
                <i class="fas fa-{{ ($stats['pending_fees'] ?? 0) > 0 ? 'clock' : 'check-circle' }} fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['pending_fees'] ?? 0) }}</h3>
                <p class="card-text">Pending Fees</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Today's Classes -->
    <div class="col-md-8">
        @if($todayClasses->count() > 0)
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-day me-2"></i>Today's Classes - {{ now()->format('l, M d, Y') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    @foreach($todayClasses as $class)
                        @php
                            $attendance = $class->attendances->where('student_id', $student->id)->first();
                            $isLive = now()->between(
                                \Carbon\Carbon::parse($class->session_date . ' ' . $class->start_time),
                                \Carbon\Carbon::parse($class->session_date . ' ' . $class->end_time)
                            );
                            $hasStarted = now()->greaterThan(\Carbon\Carbon::parse($class->session_date . ' ' . $class->start_time));
                        @endphp
                        <div class="d-flex align-items-center p-3 border-bottom {{ $isLive ? 'bg-light' : '' }}">
                            <div class="me-3">
                                @if($isLive)
                                    <div class="badge bg-danger fs-6">🔴 LIVE</div>
                                @elseif($hasStarted)
                                    <div class="badge bg-secondary">Ended</div>
                                @else
                                    <div class="badge bg-primary">{{ \Carbon\Carbon::parse($class->start_time)->format('h:i A') }}</div>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $class->batch->course->name }}</h6>
                                <p class="mb-1 text-muted">{{ $class->topic }}</p>
                                <small class="text-muted">
                                    {{ \Carbon\Carbon::parse($class->start_time)->format('h:i A') }} - 
                                    {{ \Carbon\Carbon::parse($class->end_time)->format('h:i A') }}
                                    | {{ $class->batch->name }}
                                </small>
                            </div>
                            <div class="text-end">
                                @if($class->meeting_link && ($isLive || !$hasStarted))
                                    <a href="{{ $class->meeting_link }}" target="_blank" 
                                       class="btn btn-{{ $isLive ? 'danger' : 'success' }} btn-sm mb-1">
                                        <i class="fas fa-video me-1"></i>{{ $isLive ? 'Join Live' : 'Join Class' }}
                                    </a>
                                @endif
                                @if($attendance)
                                    <div>
                                        <span class="badge bg-{{ $attendance->status === 'present' ? 'success' : ($attendance->status === 'late' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($attendance->status) }}
                                        </span>
                                    </div>
                                @elseif($hasStarted && !$isLive)
                                    <div>
                                        <span class="badge bg-secondary">Not Marked</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Upcoming Classes -->
        @if($upcomingClasses->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock me-2"></i>Upcoming Classes (Next 7 Days)
                    </h5>
                </div>
                <div class="card-body p-0">
                    @foreach($upcomingClasses->take(5) as $class)
                        <div class="d-flex align-items-center p-3 border-bottom">
                            <div class="me-3">
                                <div class="text-center">
                                    <div class="fw-bold text-primary">{{ \Carbon\Carbon::parse($class->session_date)->format('M') }}</div>
                                    <div class="h5 mb-0">{{ \Carbon\Carbon::parse($class->session_date)->format('d') }}</div>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($class->session_date)->format('D') }}</small>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $class->batch->course->name }}</h6>
                                <p class="mb-1 text-muted">{{ $class->topic }}</p>
                                <small class="text-muted">
                                    {{ \Carbon\Carbon::parse($class->start_time)->format('h:i A') }} - 
                                    {{ \Carbon\Carbon::parse($class->end_time)->format('h:i A') }}
                                    | {{ $class->batch->name }}
                                </small>
                            </div>
                            <div class="text-end">
                                @if($class->meeting_link)
                                    <small class="text-muted d-block">
                                        <i class="fas fa-video me-1"></i>Online Class
                                    </small>
                                @endif
                                <small class="text-muted">{{ \Carbon\Carbon::parse($class->session_date)->diffForHumans() }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Current Enrollments -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>My Courses
                </h5>
            </div>
            <div class="card-body">
                @if($student->enrollments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($student->enrollments as $enrollment)
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>{{ $enrollment->batch->course->name }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $enrollment->batch->course->level }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{ $enrollment->batch->name }}
                                                <br>
                                                <small class="text-muted">{{ $enrollment->batch->class_time }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $progress = $enrollment->progress_percentage ?? 0;
                                            @endphp
                                            <div class="progress mb-1">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: {{ $progress }}%" 
                                                     aria-valuenow="{{ $progress }}" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            <small>{{ $progress }}% Complete</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ 
                                                $enrollment->status === 'active' ? 'success' : 
                                                ($enrollment->status === 'completed' ? 'primary' : 'warning') 
                                            }}">
                                                {{ ucfirst($enrollment->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('fees.student', $student) }}" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-money-bill-wave"></i> Fees
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h6>No Active Enrollments</h6>
                        <p class="text-muted">You are not enrolled in any courses yet.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Attendance Summary -->
        @if(count($attendanceSummary) > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Attendance Summary by Course
                    </h5>
                </div>
                <div class="card-body">
                    @foreach($attendanceSummary as $summary)
                        <div class="mb-3 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">{{ $summary['course'] }} ({{ $summary['batch'] }})</h6>
                                <span class="badge bg-{{ $summary['attendance_rate'] >= 80 ? 'success' : ($summary['attendance_rate'] >= 60 ? 'warning' : 'danger') }} fs-6">
                                    {{ $summary['attendance_rate'] }}%
                                </span>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-{{ $summary['attendance_rate'] >= 80 ? 'success' : ($summary['attendance_rate'] >= 60 ? 'warning' : 'danger') }}" 
                                     style="width: {{ $summary['attendance_rate'] }}%"></div>
                            </div>
                            <small class="text-muted">
                                {{ $summary['attended_sessions'] }} out of {{ $summary['total_sessions'] }} classes attended
                            </small>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-primary">
                        <i class="fas fa-user me-2"></i>View Profile
                    </a>
                    <a href="{{ route('student.attendance') }}" class="btn btn-outline-info">
                        <i class="fas fa-clipboard-check me-2"></i>View All Attendance
                    </a>
                    @if($stats['pending_fees'] > 0)
                        <a href="{{ route('fees.student', $student) }}" class="btn btn-outline-warning">
                            <i class="fas fa-money-bill-wave me-2"></i>Pay Fees ({{ currency_format($stats['pending_fees']) }})
                        </a>
                    @endif
                    @if($todayClasses->count() > 0)
                        <button class="btn btn-outline-success" onclick="showTodaySchedule()">
                            <i class="fas fa-calendar-day me-2"></i>Today's Schedule
                        </button>
                    @endif
                    @if($student->enrollments->count() > 0)
                        <button class="btn btn-outline-info" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Summary
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Upcoming Fee Installments -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-money-bill me-2"></i>Upcoming Fees
                </h6>
            </div>
            <div class="card-body">
                @if($upcomingInstallments->count() > 0)
                    @foreach($upcomingInstallments as $installment)
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                            <div>
                                <div class="fw-bold">{{ currency_format($installment->amount) }}</div>
                                <small class="text-muted">
                                    Due: {{ $installment->due_date->format('M d, Y') }}
                                </small>
                            </div>
                            <span class="badge bg-warning">Pending</span>
                        </div>
                    @endforeach
                    <a href="{{ route('fees.student', $student) }}" class="btn btn-sm btn-warning w-100">
                        <i class="fas fa-credit-card me-1"></i>Pay Now
                    </a>
                @else
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="mb-0 text-muted">No pending fees</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>Recent Attendance
                </h6>
            </div>
            <div class="card-body">
                @if($recentAttendances->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($recentAttendances as $attendance)
                            <div class="list-group-item d-flex justify-content-between align-items-start px-0">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">{{ $attendance->classSession->batch->course->name }}</div>
                                    <small class="text-muted">
                                        {{ $attendance->classSession->session_date->format('M d, Y') }} - 
                                        {{ $attendance->classSession->batch->name }}
                                    </small>
                                </div>
                                <span class="badge bg-{{ 
                                    $attendance->status === 'present' ? 'success' : 
                                    ($attendance->status === 'late' ? 'warning' : 
                                    ($attendance->status === 'excused' ? 'info' : 'danger')) 
                                }} rounded-pill">
                                    {{ ucfirst($attendance->status) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                        <h6>No Attendance Records</h6>
                        <p class="text-muted">Your attendance records will appear here.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Student Information -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Student Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">Email:</small>
                    <div>{{ $student->email }}</div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Phone:</small>
                    <div>{{ $student->phone ?? 'Not provided' }}</div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Date of Birth:</small>
                    <div>{{ $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('M d, Y') : 'Not provided' }}</div>
                </div>
                @if($student->emergency_contact)
                    <div class="mb-2">
                        <small class="text-muted">Emergency Contact:</small>
                        <div>{{ $student->emergency_contact }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($student->certificates->count() > 0)
    <!-- Certificates Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-certificate me-2"></i>My Certificates
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($student->certificates as $certificate)
                            <div class="col-md-4 mb-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-award fa-3x text-success mb-3"></i>
                                        <h6 class="card-title">{{ $certificate->course->name }}</h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Issued: {{ $certificate->issued_date->format('M d, Y') }}
                                            </small>
                                        </p>
                                        <a href="{{ $certificate->certificate_url ?? '#' }}" 
                                           class="btn btn-sm btn-success" target="_blank">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
function showTodaySchedule() {
    const scheduleModal = new bootstrap.Modal(document.createElement('div'));
    // Implementation for showing today's schedule modal
    alert('Today\'s schedule feature coming soon!');
}

// Auto-refresh for live classes
setInterval(function() {
    const liveElements = document.querySelectorAll('.badge:contains("LIVE")');
    if (liveElements.length > 0) {
        // Refresh page if there are live classes to update status
        location.reload();
    }
}, 60000); // Check every minute
</script>
@endsection