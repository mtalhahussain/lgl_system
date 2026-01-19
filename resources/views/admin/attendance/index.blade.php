@extends('layouts.admin')

@section('title', 'Attendance Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>Attendance Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('attendance.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Mark Attendance
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x mb-3"></i>
                <h3>{{ $stats['total_sessions'] ?? 0 }}</h3>
                <p class="card-text">Total Sessions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check fa-2x mb-3"></i>
                <h3>{{ $stats['completed_sessions'] ?? 0 }}</h3>
                <p class="card-text">Completed</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ $stats['upcoming_sessions'] ?? 0 }}</h3>
                <p class="card-text">Upcoming</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-3"></i>
                <h3>{{ $stats['average_attendance'] ?? 0 }}%</h3>
                <p class="card-text">Avg Attendance</p>
            </div>
        </div>
    </div>
</div>

<div class="card dashboard-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Attendance Management
        </h5>
    </div>
    <div class="card-body">
        <div class="text-center py-5">
            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
            <h5>Attendance Tracking</h5>
            <p class="text-muted">Mark daily attendance and generate attendance reports.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Mark Attendance
                </a>
                <a href="{{ route('attendance.reports') }}" class="btn btn-outline-primary">
                    <i class="fas fa-chart-bar me-1"></i>View Reports
                </a>
            </div>
        </div>
    </div>
</div>
@endsection