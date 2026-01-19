@extends('layouts.admin')

@section('title', 'Fee Reports')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>Fee Reports
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('fees.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Fees
        </a>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Report Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" 
                       value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Generate Report
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- Daily Collection Chart -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Daily Collections
                    <span class="text-muted">({{ $startDate->format('M d') }} - {{ $endDate->format('M d, Y') }})</span>
                </h5>
            </div>
            <div class="card-body">
                @if($dailyCollection->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount Collected</th>
                                    <th>Visual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $maxAmount = $dailyCollection->max('total'); @endphp
                                @foreach($dailyCollection as $collection)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($collection->date)->format('M d, Y') }}</td>
                                        <td class="fw-bold text-success">{{ currency_format($collection->total) }}</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" style="width: {{ $maxAmount > 0 ? ($collection->total / $maxAmount) * 100 : 0 }}%">
                                                    {{ number_format(($collection->total / $dailyCollection->sum('total')) * 100, 1) }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <th>Total</th>
                                    <th>{{ currency_format($dailyCollection->sum('total')) }}</th>
                                    <th>100%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <h6>No Collections Found</h6>
                        <p class="text-muted">No fee collections in the selected date range.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calculator me-2"></i>Summary Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Collections:</span>
                        <strong class="text-success">{{ currency_format($dailyCollection->sum('total')) }}</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Average Daily:</span>
                        <strong>{{ currency_format($dailyCollection->count() > 0 ? $dailyCollection->sum('total') / $dailyCollection->count() : 0) }}</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Best Day:</span>
                        <strong class="text-primary">{{ currency_format($dailyCollection->max('total')) }}</strong>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between">
                        <span>Collection Days:</span>
                        <strong>{{ $dailyCollection->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outstanding Dues -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Outstanding Dues
                </h6>
            </div>
            <div class="card-body">
                @if($outstandingDues->count() > 0)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Overdue:</span>
                            <strong class="text-danger">{{ currency_format($outstandingDues->sum('amount')) }}</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Number of Students:</span>
                            <strong>{{ $outstandingDues->count() }}</strong>
                        </div>
                    </div>
                    <a href="{{ route('fees.index') }}?status=pending" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-list me-1"></i>View Pending Fees
                    </a>
                @else
                    <p class="text-success mb-0">
                        <i class="fas fa-check-circle me-1"></i>No outstanding dues!
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Course-wise Collection -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-graduation-cap me-2"></i>Course-wise Collections
        </h5>
    </div>
    <div class="card-body">
        @if($courseCollection->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Level</th>
                            <th>Amount Collected</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalCourseCollection = $courseCollection->sum('total'); @endphp
                        @foreach($courseCollection as $course)
                            <tr>
                                <td>
                                    <strong>{{ $course->course_name }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ ucfirst($course->level) }}</span>
                                </td>
                                <td class="fw-bold text-success">{{ currency_format($course->total) }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress me-2" style="width: 100px; height: 15px;">
                                            <div class="progress-bar" style="width: {{ $totalCourseCollection > 0 ? ($course->total / $totalCourseCollection) * 100 : 0 }}%"></div>
                                        </div>
                                        <span>{{ $totalCourseCollection > 0 ? number_format(($course->total / $totalCourseCollection) * 100, 1) : 0 }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                <h6>No Course Collections</h6>
                <p class="text-muted">No collections found for any courses in the selected period.</p>
            </div>
        @endif
    </div>
</div>

<!-- Monthly Trends -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>Monthly Trends
        </h5>
    </div>
    <div class="card-body">
        @if($monthlyTrends->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Amount Collected</th>
                            <th>Visual Comparison</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php 
                            $maxMonthlyAmount = $monthlyTrends->max('total');
                            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        @endphp
                        @foreach($monthlyTrends as $trend)
                            <tr>
                                <td>
                                    <strong>{{ $months[$trend->month - 1] ?? 'Month ' . $trend->month }}</strong>
                                </td>
                                <td class="fw-bold text-success">{{ currency_format($trend->total) }}</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-primary" style="width: {{ $maxMonthlyAmount > 0 ? ($trend->total / $maxMonthlyAmount) * 100 : 0 }}%">
                                            {{ number_format(($trend->total / $monthlyTrends->sum('total')) * 100, 1) }}%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th>Total</th>
                            <th>{{ currency_format($monthlyTrends->sum('total')) }}</th>
                            <th>100%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                <h6>No Monthly Data</h6>
                <p class="text-muted">No monthly collection data available.</p>
            </div>
        @endif
    </div>
</div>
@endsection