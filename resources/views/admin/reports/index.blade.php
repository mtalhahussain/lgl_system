@extends('layouts.admin')

@section('title', 'Reports & Analytics')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
    </h1>
</div>

<div class="row">
    @if(isset($reportTypes))
        @foreach($reportTypes as $key => $report)
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <i class="{{ $report['icon'] }} fa-3x mb-3 text-{{ $report['color'] }}"></i>
                        <h5 class="card-title">{{ $report['title'] }}</h5>
                        <p class="card-text">{{ $report['description'] }}</p>
                        <a href="{{ route('reports.generate', $key) }}" class="btn btn-{{ $report['color'] }}">
                            <i class="fas fa-eye me-1"></i>View Report
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill fa-3x mb-3 text-success"></i>
                    <h5 class="card-title">Financial Reports</h5>
                    <p class="card-text">Fee collection, teacher payments, revenue analysis</p>
                    <a href="{{ route('reports.generate', 'financial') }}" class="btn btn-success">
                        <i class="fas fa-eye me-1"></i>View Report
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-3x mb-3 text-info"></i>
                    <h5 class="card-title">Student Reports</h5>
                    <p class="card-text">Enrollment, attendance, progress tracking</p>
                    <a href="{{ route('reports.generate', 'student') }}" class="btn btn-info">
                        <i class="fas fa-eye me-1"></i>View Report
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-chalkboard-teacher fa-3x mb-3 text-warning"></i>
                    <h5 class="card-title">Teacher Reports</h5>
                    <p class="card-text">Performance, earnings, class statistics</p>
                    <a href="{{ route('reports.generate', 'teacher') }}" class="btn btn-warning">
                        <i class="fas fa-eye me-1"></i>View Report
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-book fa-3x mb-3 text-primary"></i>
                    <h5 class="card-title">Course Reports</h5>
                    <p class="card-text">Batch performance, completion rates</p>
                    <a href="{{ route('reports.generate', 'course') }}" class="btn btn-primary">
                        <i class="fas fa-eye me-1"></i>View Report
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-clipboard-check fa-3x mb-3 text-danger"></i>
                    <h5 class="card-title">Attendance Reports</h5>
                    <p class="card-text">Class attendance, trends, patterns</p>
                    <a href="{{ route('reports.generate', 'attendance') }}" class="btn btn-danger">
                        <i class="fas fa-eye me-1"></i>View Report
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection