@extends('layouts.admin')

@section('title', 'Accountant Dashboard')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calculator me-2"></i>Accountant Dashboard
    </h1>
    <div class="d-flex align-items-center">
        <span class="badge bg-info me-2">Financial Overview</span>
        <span class="text-muted">{{ now()->format('l, F j, Y') }}</span>
    </div>
</div>

<!-- Financial Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-money-bill fa-2x mb-3"></i>
                <h3>{{ currency_format(45000) }}</h3>
                <p class="card-text">Monthly Revenue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ currency_format(8500) }}</h3>
                <p class="card-text">Pending Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-credit-card fa-2x mb-3"></i>
                <h3>{{ currency_format(12000) }}</h3>
                <p class="card-text">Teacher Payments</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h3>12</h3>
                <p class="card-text">Overdue Payments</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Financial Transactions -->
    <div class="col-md-8">
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>Recent Transactions
                </h5>
                <a href="{{ route('fees.index') }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-list me-1"></i>View All
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Maria Schmidt</strong>
                                    <br><small class="text-muted">STU0001</small>
                                </td>
                                <td>German A1 - Beginner</td>
                                <td>{{ currency_format(300) }}</td>
                                <td>{{ now()->subHours(2)->format('M d, H:i') }}</td>
                                <td><span class="badge bg-success">Paid</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Johann Weber</strong>
                                    <br><small class="text-muted">STU0002</small>
                                </td>
                                <td>German B1 - Intermediate</td>
                                <td>{{ currency_format(350) }}</td>
                                <td>{{ now()->subHours(5)->format('M d, H:i') }}</td>
                                <td><span class="badge bg-success">Paid</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Anna Mueller</strong>
                                    <br><small class="text-muted">STU0003</small>
                                </td>
                                <td>German A2 - Elementary</td>
                                <td>{{ currency_format(325) }}</td>
                                <td>{{ now()->subDays(1)->format('M d, H:i') }}</td>
                                <td><span class="badge bg-warning">Pending</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Klaus Fischer</strong>
                                    <br><small class="text-muted">STU0004</small>
                                </td>
                                <td>German C1 - Advanced</td>
                                <td>{{ currency_format(400) }}</td>
                                <td>{{ now()->subDays(2)->format('M d, H:i') }}</td>
                                <td><span class="badge bg-danger">Overdue</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Quick Actions -->
    <div class="col-md-4">
        <div class="card dashboard-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('fees.index') }}" class="btn btn-primary">
                        <i class="fas fa-money-bill me-2"></i>Fee Management
                    </a>
                    <a href="{{ route('fees.reports') }}" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-2"></i>Financial Reports
                    </a>
                    <a href="{{ route('students.index') }}" class="btn btn-outline-info">
                        <i class="fas fa-users me-2"></i>Student Records
                    </a>
                    <a href="{{ route('reports.generate', 'financial') }}" class="btn btn-outline-success">
                        <i class="fas fa-file-excel me-2"></i>Export Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Overdue Alerts -->
        <div class="card dashboard-card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Overdue Payments Alert
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>12 overdue payments</strong> totaling <strong>{{ currency_format(3800) }}</strong> require immediate attention.
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>Klaus Fischer</strong>
                            <br><small class="text-muted">C1 Course - 15 days overdue</small>
                        </div>
                        <span class="badge bg-danger">{{ currency_format(400) }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>Lisa Weber</strong>
                            <br><small class="text-muted">B2 Course - 8 days overdue</small>
                        </div>
                        <span class="badge bg-danger">{{ currency_format(375) }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong>Michael Berg</strong>
                            <br><small class="text-muted">A1 Course - 5 days overdue</small>
                        </div>
                        <span class="badge bg-danger">{{ currency_format(300) }}</span>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('fees.index') }}?overdue=1" class="btn btn-danger btn-sm">
                        <i class="fas fa-eye me-1"></i>View All Overdue
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary Charts -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-area me-2"></i>Monthly Revenue Trend
                </h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Payment Methods Distribution
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <h4 class="text-primary">45%</h4>
                        <small>Bank Transfer</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-success">30%</h4>
                        <small>Cash</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning">20%</h4>
                        <small>Card</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-info">5%</h4>
                        <small>Online</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Simple chart for demonstration
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue ({{ currency_symbol() }})',
                    data: [35000, 42000, 38000, 45000, 41000, 47000],
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '{{ currency_symbol() }}' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush