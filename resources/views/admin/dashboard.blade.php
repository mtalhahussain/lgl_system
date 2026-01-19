@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
    </h1>
    <div class="d-flex align-items-center">
        <span class="badge bg-success me-2">{{ $stats['active_students'] ?? 0 }} Active Students</span>
        <span class="text-muted">{{ now()->format('l, F j, Y') }}</span>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-3"></i>
                <h3>{{ $stats['total_students'] ?? 250 }}</h3>
                <p class="card-text">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard-teacher fa-2x mb-3"></i>
                <h3>{{ $stats['active_teachers'] ?? 15 }}</h3>
                <p class="card-text">Active Teachers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                <h3>{{ $stats['active_batches'] ?? 8 }}</h3>
                <p class="card-text">Active Batches</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-money-bill fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['total_revenue'] ?? 25000) }}</h3>
                <p class="card-text">Total Revenue</p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clock me-2"></i>Recent Activity
                </h5>
            </div>
            <div class="card-body">
                @if(isset($recentActivities))
                    @forelse($recentActivities as $activity)
                    <div class="alert alert-{{ $activity['color'] }} d-flex align-items-center mb-3" role="alert">
                        <i class="{{ $activity['icon'] }} me-3"></i>
                        <div class="flex-grow-1">
                            <strong>{{ $activity['title'] }}:</strong> {{ $activity['description'] }}
                            <div class="small text-muted">{{ $activity['time'] }}</div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted">No recent activities</p>
                    @endforelse
                @else
                    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                        <i class="fas fa-check-circle me-3"></i>
                        <div class="flex-grow-1">
                            <strong>Welcome to your Admin Dashboard!</strong> All systems are running smoothly. You can manage students, teachers, courses, and financial records from here.
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-user-plus text-success me-3"></i>
                        <div>
                            <strong>New student enrollment:</strong> Maria Schmidt (A1 Course)
                            <div class="small text-muted">2 hours ago</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-money-bill text-info me-3"></i>
                        <div>
                            <strong>Payment received:</strong> {{ currency_format(300) }} from Johann Weber
                            <div class="small text-muted">3 hours ago</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar-alt text-warning me-3"></i>
                        <div>
                            <strong>New batch created:</strong> B2 Evening Class
                            <div class="small text-muted">1 day ago</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Quick Stats
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Course Completion Rate</span>
                        <span class="fw-bold">85%</span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar bg-success" style="width: 85%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Fee Collection</span>
                        <span class="fw-bold">92%</span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar bg-info" style="width: 92%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Teacher Utilization</span>
                        <span class="fw-bold">78%</span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar bg-warning" style="width: 78%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Student Satisfaction</span>
                        <span class="fw-bold">96%</span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar bg-primary" style="width: 96%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tasks me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ url('/students') }}" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </a>
                    <a href="{{ url('/teachers') }}" class="btn btn-outline-success">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Manage Teachers
                    </a>
                    <a href="{{ url('/courses') }}" class="btn btn-outline-info">
                        <i class="fas fa-book me-2"></i>View Courses
                    </a>
                    <a href="{{ url('/reports') }}" class="btn btn-outline-warning">
                        <i class="fas fa-chart-bar me-2"></i>Generate Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection