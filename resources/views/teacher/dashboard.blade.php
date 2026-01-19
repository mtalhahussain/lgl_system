@extends('layouts.admin')

@section('title', 'Teacher Dashboard')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Dashboard
    </h1>
    <div class="d-flex align-items-center">
        <span class="badge bg-success me-2">{{ $stats['active_batches'] }} Active Batches</span>
        <span class="text-muted">{{ now()->format('l, F j, Y') }}</span>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-3"></i>
                <h3>{{ $stats['total_students'] }}</h3>
                <p class="card-text">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                <h3>{{ $stats['active_batches'] }}</h3>
                <p class="card-text">Active Batches</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-check fa-2x mb-3"></i>
                <h3>{{ $stats['pending_sessions'] }}</h3>
                <p class="card-text">Pending Sessions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-money-bill fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['monthly_earnings']) }}</h3>
                <p class="card-text">This Month</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Upcoming Sessions -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-check me-2"></i>Upcoming Sessions
                </h5>
                <a href="{{ route('attendance.create') }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>Mark Attendance
                </a>
            </div>
            <div class="card-body">
                @if($upcomingSessions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course & Batch</th>
                                    <th>Date & Time</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($upcomingSessions as $batch)
                                    @foreach($batch->classSessions()->where('status', 'scheduled')->whereDate('session_date', '>=', now())->take(3)->get() as $session)
                                    <tr>
                                        <td>
                                            <strong>{{ $batch->course->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $batch->name }}</small>
                                        </td>
                                        <td>
                                            {{ $session->session_date->format('M d, Y') }}
                                            <br>
                                            <small class="text-muted">{{ $session->start_time }} - {{ $session->end_time }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $batch->enrollments->where('status', 'active')->count() }} Students</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('attendance.show', $session) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5>No upcoming sessions</h5>
                        <p class="text-muted">All sessions are up to date!</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Earnings & Quick Actions -->
    <div class="col-md-4">
        <div class="card dashboard-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-coins me-2"></i>Recent Earnings
                </h5>
            </div>
            <div class="card-body">
                @if($recentEarnings->count() > 0)
                    @foreach($recentEarnings as $earning)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>{{ currency_format($earning->amount) }}</strong>
                                <br>
                                <small class="text-muted">{{ $earning->batch->course->name }} - {{ $earning->batch->name }}</small>
                            </div>
                            <span class="text-muted" style="font-size: 0.8em;">
                                {{ $earning->created_at->diffForHumans() }}
                            </span>
                        </div>
                        @if(!$loop->last)<hr class="my-2">@endif
                    @endforeach
                @else
                    <div class="text-center py-3">
                        <i class="fas fa-coins fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No earnings yet</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                        <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                    </a>
                    <a href="{{ route('batches.index') }}" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-alt me-2"></i>View My Batches
                    </a>
                    <a href="{{ route('attendance.reports') }}" class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-2"></i>Attendance Reports
                    </a>
                    <a href="#" class="btn btn-outline-secondary">
                        <i class="fas fa-user me-2"></i>Update Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- My Batches Overview -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chalkboard me-2"></i>My Active Batches
                </h5>
            </div>
            <div class="card-body">
                @if($teacher->taughtBatches->where('status', 'active')->count() > 0)
                    <div class="row">
                        @foreach($teacher->taughtBatches->where('status', 'active') as $batch)
                            <div class="col-md-4 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ $batch->course->name }}</h6>
                                        <p class="card-text">
                                            <strong>{{ $batch->name }}</strong><br>
                                            <span class="badge bg-{{ $batch->course->level === 'A1' || $batch->course->level === 'A2' ? 'success' : 'info' }}">{{ $batch->course->level }}</span>
                                            <span class="badge bg-secondary">{{ $batch->enrollments->where('status', 'active')->count() }} Students</span>
                                        </p>
                                        <small class="text-muted">
                                            {{ $batch->start_date->format('M d') }} - {{ $batch->end_date->format('M d, Y') }}
                                        </small>
                                        <div class="mt-2">
                                            <a href="{{ route('batches.show', $batch) }}" class="btn btn-sm btn-outline-primary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
                        <h5>No active batches</h5>
                        <p class="text-muted">Contact admin to get assigned to batches.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-refresh upcoming sessions every 5 minutes
setInterval(function() {
    location.reload();
}, 300000);
</script>
@endpush