@extends('layouts.admin')

@section('title', 'My Attendance')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>My Attendance Records
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar fa-2x mb-3"></i>
                <h3>{{ $stats['total_classes'] }}</h3>
                <p class="card-text">Total Classes</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <h3>{{ $stats['present_classes'] }}</h3>
                <p class="card-text">Present</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ $stats['late_classes'] }}</h3>
                <p class="card-text">Late</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card stat-card-danger">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x mb-3"></i>
                <h3>{{ $stats['absent_classes'] }}</h3>
                <p class="card-text">Absent</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-user-check fa-2x mb-3"></i>
                <h3>{{ $stats['excused_classes'] }}</h3>
                <p class="card-text">Excused</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card dashboard-card stat-card-primary">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-3"></i>
                <h3>{{ $stats['attendance_rate'] }}%</h3>
                <p class="card-text">Attendance Rate</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-md-8">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Attendance Records
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
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
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            <option value="">All Months</option>
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}" {{ request('month') == $i ? 'selected' : '' }}>
                                    {{ \DateTime::createFromFormat('m', $i)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            @for($i = now()->year; $i >= now()->year - 2; $i--)
                                <option value="{{ $i }}" {{ request('year') == $i ? 'selected' : '' }}>
                                    {{ $i }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Attendance History
                </h5>
            </div>
            <div class="card-body">
                @if($attendances->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <th>Topic</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attendances as $attendance)
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>{{ $attendance->classSession->session_date->format('M d, Y') }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $attendance->classSession->session_date->format('l') }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>{{ $attendance->classSession->batch->course->name }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $attendance->classSession->batch->course->level }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{ $attendance->classSession->batch->name }}
                                                <br>
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($attendance->classSession->start_time)->format('h:i A') }} - 
                                                    {{ \Carbon\Carbon::parse($attendance->classSession->end_time)->format('h:i A') }}
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            {{ $attendance->classSession->topic }}
                                        </td>
                                        <td>
                                            <span class="badge fs-6 bg-{{ 
                                                $attendance->status === 'present' ? 'success' : 
                                                ($attendance->status === 'late' ? 'warning' : 
                                                ($attendance->status === 'excused' ? 'info' : 'danger')) 
                                            }}">
                                                @switch($attendance->status)
                                                    @case('present')
                                                        <i class="fas fa-check me-1"></i>Present
                                                        @break
                                                    @case('late')
                                                        <i class="fas fa-clock me-1"></i>Late
                                                        @break
                                                    @case('excused')
                                                        <i class="fas fa-user-check me-1"></i>Excused
                                                        @break
                                                    @case('absent')
                                                        <i class="fas fa-times me-1"></i>Absent
                                                        @break
                                                @endswitch
                                            </span>
                                        </td>
                                        <td>
                                            {{ $attendance->notes ?? '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            Showing {{ $attendances->firstItem() }} to {{ $attendances->lastItem() }} 
                            of {{ $attendances->total() }} records
                        </div>
                        {{ $attendances->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                        <h5>No Attendance Records Found</h5>
                        <p class="text-muted">No attendance records match your search criteria.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Attendance Rate Progress -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Attendance Breakdown
                </h6>
            </div>
            <div class="card-body">
                @php
                    $total = $stats['total_classes'];
                    $present = $stats['present_classes'];
                    $late = $stats['late_classes'];
                    $absent = $stats['absent_classes'];
                    $excused = $stats['excused_classes'];
                @endphp

                @if($total > 0)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Present</span>
                            <span>{{ $present }} ({{ round(($present / $total) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" style="width: {{ ($present / $total) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Late</span>
                            <span>{{ $late }} ({{ round(($late / $total) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-warning" style="width: {{ ($late / $total) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Absent</span>
                            <span>{{ $absent }} ({{ round(($absent / $total) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-danger" style="width: {{ ($absent / $total) * 100 }}%"></div>
                        </div>
                    </div>

                    @if($excused > 0)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Excused</span>
                                <span>{{ $excused }} ({{ round(($excused / $total) * 100, 1) }}%)</span>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-info" style="width: {{ ($excused / $total) * 100 }}%"></div>
                            </div>
                        </div>
                    @endif
                @else
                    <p class="text-muted text-center">No attendance data available</p>
                @endif
            </div>
        </div>

        <!-- Monthly Trends -->
        @if($monthlyStats->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Monthly Attendance Trends
                    </h6>
                </div>
                <div class="card-body">
                    @foreach($monthlyStats as $month)
                        @php
                            $monthName = \DateTime::createFromFormat('m', $month->month)->format('M');
                            $rate = $month->total > 0 ? round((($month->present + $month->late) / $month->total) * 100, 1) : 0;
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ $monthName }} {{ $month->year }}</span>
                                <span class="badge bg-{{ $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger') }}">
                                    {{ $rate }}%
                                </span>
                            </div>
                            <div class="progress mb-1" style="height: 8px;">
                                <div class="progress-bar bg-{{ $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger') }}" 
                                     style="width: {{ $rate }}%"></div>
                            </div>
                            <small class="text-muted">{{ $month->present + $month->late }}/{{ $month->total }} classes</small>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Attendance Tips -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-lightbulb me-2"></i>Attendance Tips
                </h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0 py-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <small>Maintain at least 80% attendance for best results</small>
                    </div>
                    <div class="list-group-item px-0 py-2">
                        <i class="fas fa-clock text-warning me-2"></i>
                        <small>Arrive on time to avoid being marked as late</small>
                    </div>
                    <div class="list-group-item px-0 py-2">
                        <i class="fas fa-phone text-info me-2"></i>
                        <small>Contact your teacher if you need to be excused</small>
                    </div>
                    <div class="list-group-item px-0 py-2">
                        <i class="fas fa-calendar text-primary me-2"></i>
                        <small>Check your dashboard for today's classes</small>
                    </div>
                </div>

                @if($stats['attendance_rate'] < 75)
                    <div class="alert alert-warning mt-3 p-2">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Attendance Alert:</strong> Your attendance rate is below 75%. 
                            Please improve your attendance to stay on track.
                        </small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection