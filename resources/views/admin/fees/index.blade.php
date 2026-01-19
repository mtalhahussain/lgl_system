@extends('layouts.admin')

@section('title', 'Fee Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill me-2"></i>Fee Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('fees.reports') }}" class="btn btn-primary">
            <i class="fas fa-chart-bar me-1"></i>Fee Reports
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['total_paid'] ?? 0) }}</h3>
                <p class="card-text">Total Paid</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['total_pending'] ?? 0) }}</h3>
                <p class="card-text">Pending Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h3>{{ $stats['overdue_count'] ?? 0 }}</h3>
                <p class="card-text">Overdue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar fa-2x mb-3"></i>
                <h3>{{ currency_format($stats['monthly_collection'] ?? 0) }}</h3>
                <p class="card-text">This Month</p>
            </div>
        </div>
    </div>
</div>

<div class="card dashboard-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Fee Management
        </h5>
    </div>
    <div class="card-body">
        <div class="text-center py-5">
            <i class="fas fa-money-bill fa-3x text-muted mb-3"></i>
            <h5>Fee Management System</h5>
            <p class="text-muted">Track payments, installments, and generate fee reports.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="{{ route('fees.reports') }}" class="btn btn-primary">
                    <i class="fas fa-chart-bar me-1"></i>View Reports
                </a>
            </div>
        </div>
    </div>
</div>
@endsection