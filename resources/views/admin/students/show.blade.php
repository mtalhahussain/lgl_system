@extends('layouts.admin')

@section('title', 'Student Details - ' . $student->name)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user me-2"></i>Student Details
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('students.index') }}" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>Back to Students
        </a>
        <a href="{{ route('students.edit', $student) }}" class="btn btn-primary">
            <i class="fas fa-edit me-1"></i>Edit Student
        </a>
    </div>
</div>

<div class="row">
    <!-- Student Information Card -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Personal Information
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="avatar avatar-xl mb-3">
                        <span class="badge bg-primary rounded-circle" style="font-size: 2rem; padding: 1rem;">
                            {{ substr($student->name, 0, 1) }}
                        </span>
                    </div>
                    <h5>{{ $student->name }}</h5>
                    <p class="text-muted">Student ID: {{ $student->student_id }}</p>
                    @if($student->fingerprint_enrolled)
                        <span class="badge bg-success">
                            <i class="fas fa-fingerprint me-1"></i>Biometric Enrolled
                        </span>
                    @endif
                </div>
                
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td>{{ $student->email }}</td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td>{{ $student->phone ?? 'Not provided' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Date of Birth:</strong></td>
                        <td>{{ $student->date_of_birth ? $student->date_of_birth->format('d M Y') : 'Not provided' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td>{{ $student->address ?? 'Not provided' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Joined:</strong></td>
                        <td>{{ $student->created_at->format('d M Y') }}</td>
                    </tr>
                    @if($student->fingerprint_enrolled)
                    <tr>
                        <td><strong>Device ID:</strong></td>
                        <td>{{ $student->device_employee_no }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- Quick Stats Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Quick Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-box">
                            <h4 class="text-primary">{{ $stats['courses_completed'] }}</h4>
                            <small class="text-muted">Completed Courses</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-box">
                            <h4 class="text-success">{{ $stats['attendance_rate'] }}%</h4>
                            <small class="text-muted">Attendance Rate</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Fees Paid:</span>
                            <strong class="text-success">{{ currency_format($stats['total_paid']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Pending Fees:</span>
                            <strong class="text-{{ $stats['pending_fees'] > 0 ? 'danger' : 'success' }}">
                                {{ currency_format($stats['pending_fees']) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollments and Details -->
    <div class="col-lg-8">
        <!-- Current Enrollments -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>Course Enrollments
                </h5>
                <span class="badge bg-primary">{{ $student->enrollments->count() }} Total</span>
            </div>
            <div class="card-body">
                @if($student->enrollments->count() > 0)
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Enrolled Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($student->enrollments as $enrollment)
                                    <tr>
                                        <td>
                                            <strong>{{ $enrollment->batch->course->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $enrollment->batch->course->code }}</small>
                                        </td>
                                        <td>{{ $enrollment->batch->name }}</td>
                                        <td>
                                            <span class="badge bg-{{ $enrollment->status === 'active' ? 'success' : ($enrollment->status === 'completed' ? 'primary' : 'secondary') }}">
                                                {{ ucfirst($enrollment->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $progress = $enrollment->progress ?? 0;
                                            @endphp
                                            <div class="progress" style="width: 100px;">
                                                <div class="progress-bar" style="width: {{ $progress }}%">
                                                    {{ $progress }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $enrollment->created_at->format('d M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                        <h6>No Course Enrollments</h6>
                        <p class="text-muted">This student is not enrolled in any courses yet.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Fee Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>Fee Details
                </h5>
            </div>
            <div class="card-body">
                @if($student->enrollments->count() > 0)
                    @foreach($student->enrollments as $enrollment)
                        @if($enrollment->feeInstallments->count() > 0)
                            <h6>{{ $enrollment->batch->course->name }} - {{ $enrollment->batch->name }}</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Installment</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Paid Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($enrollment->feeInstallments as $installment)
                                            <tr>
                                                <td>{{ $installment->installment_number }}</td>
                                                <td>{{ currency_format($installment->amount) }}</td>
                                                <td>{{ $installment->due_date->format('d M Y') }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $installment->status === 'paid' ? 'success' : ($installment->status === 'overdue' ? 'danger' : 'warning') }}">
                                                        {{ ucfirst($installment->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    {{ $installment->paid_date ? \Carbon\Carbon::parse($installment->paid_date)->format('d M Y') : '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h6>No Fee Records</h6>
                        <p class="text-muted">No fee installments found for this student.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-check me-2"></i>Recent Attendance
                </h5>
                <a href="{{ route('attendance.student', $student) }}" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                @if($student->attendances->count() > 0)
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Session</th>
                                    <th>Status</th>
                                    <th>Check-in Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($student->attendances->take(10) as $attendance)
                                    <tr>
                                        <td>{{ $attendance->classSession->session_date->format('d M Y') }}</td>
                                        <td>{{ $attendance->classSession->batch->course->name }}</td>
                                        <td>{{ $attendance->classSession->topic }}</td>
                                        <td>
                                            <span class="badge bg-{{ $attendance->status === 'present' ? 'success' : ($attendance->status === 'late' ? 'warning' : ($attendance->status === 'excused' ? 'info' : 'danger')) }}">
                                                {{ ucfirst($attendance->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $attendance->check_in_time ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                        <h6>No Attendance Records</h6>
                        <p class="text-muted">No attendance records found for this student.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.avatar-xl {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-box {
    padding: 1rem 0;
}

.stat-box h4 {
    margin: 0;
    font-weight: bold;
}

.progress {
    height: 20px;
}

.table td {
    vertical-align: middle;
}
</style>
@endsection