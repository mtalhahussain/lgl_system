@extends('layouts.admin')

@section('title', 'Teachers Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chalkboard-teacher me-2"></i>Teachers Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('teachers.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add New Teacher
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard-teacher fa-2x mb-3"></i>
                <h3>{{ $stats['total'] ?? 0 }}</h3>
                <p class="card-text">Total Teachers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-user-check fa-2x mb-3"></i>
                <h3>{{ $stats['active'] ?? 0 }}</h3>
                <p class="card-text">Active Teachers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-money-bill fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['total_earnings_month'] ?? 0) }}</h3>
                <p class="card-text">Monthly Earnings</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-calculator fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['average_salary'] ?? 0) }}</h3>
                <p class="card-text">Average Salary</p>
            </div>
        </div>
    </div>
</div>

<!-- Teachers Table -->
<div class="card dashboard-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Teachers List
        </h5>
        <span class="badge bg-primary">{{ $teachers->total() ?? 0 }} Total</span>
    </div>
    <div class="card-body">
        @if(isset($teachers) && $teachers->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Teacher Info</th>
                            <th>Contact</th>
                            <th>Specialization</th>
                            <th>Active Batches</th>
                            <th>Monthly Earnings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teachers as $teacher)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $teacher->name }}</strong>
                                        <br>
                                        <small class="text-muted">ID: {{ $teacher->employee_id }}</small>
                                        <br>
                                        <small class="text-muted">{{ $teacher->experience_years }} years exp.</small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope me-1"></i>{{ $teacher->email }}
                                        <br>
                                        <i class="fas fa-phone me-1"></i>{{ $teacher->phone }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $teacher->specialization }}</span>
                                    <br>
                                    <small class="text-muted">{{ $teacher->qualification }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-success">{{ $teacher->taughtBatches->where('status', 'active')->count() }} Active</span>
                                </td>
                                <td>
                                    {{ currency_format($teacher->teacherEarnings()->whereMonth('created_at', now()->month)->sum('total_earning')) }}
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('teachers.show', $teacher) }}" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('teachers.edit', $teacher) }}" 
                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                <h5>No teachers found</h5>
                <p class="text-muted">Start by adding your first teacher.</p>
                <a href="{{ route('teachers.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Add First Teacher
                </a>
            </div>
        @endif
    </div>
</div>
@endsection