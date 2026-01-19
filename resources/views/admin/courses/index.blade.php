@extends('layouts.admin')

@section('title', 'Courses Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>Courses Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('courses.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add New Course
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-book fa-2x mb-3"></i>
                <h3>{{ $stats['total_courses'] ?? 0 }}</h3>
                <p class="card-text">Total Courses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                <h3>{{ $stats['active_batches'] ?? 0 }}</h3>
                <p class="card-text">Active Batches</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-3"></i>
                <h3>{{ $stats['total_students'] ?? 0 }}</h3>
                <p class="card-text">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-layer-group fa-2x mb-3"></i>
                <h3>{{ $stats['course_levels'] ?? 0 }}</h3>
                <p class="card-text">Course Levels</p>
            </div>
        </div>
    </div>
</div>

<!-- Courses Grid -->
<div class="card dashboard-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-graduation-cap me-2"></i>German Language Courses (A1-C2)
        </h5>
    </div>
    <div class="card-body">
        @if(isset($courses) && $courses->count() > 0)
            <div class="row">
                @foreach($courses as $course)
                    <div class="col-md-4 mb-4">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title">{{ $course->name }}</h5>
                                    <span class="badge bg-{{ $course->level === 'A1' || $course->level === 'A2' ? 'success' : ($course->level === 'B1' || $course->level === 'B2' ? 'warning' : 'info') }}">
                                        {{ $course->level }}
                                    </span>
                                </div>
                                <p class="card-text">{{ Str::limit($course->description, 100) }}</p>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <small class="text-muted">Duration</small>
                                        <br>
                                        <strong>{{ $course->duration_weeks }} weeks</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Fee</small>
                                        <br>
                                        <strong>{{ currency_format($course->total_fee) }}</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Max Students</small>
                                        <br>
                                        <strong>{{ $course->max_students }}</strong>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary">{{ $course->batches_count ?? 0 }} Batches</span>
                                        <span class="badge bg-success">{{ $course->active_batches_count ?? 0 }} Active</span>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('courses.show', $course) }}" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('courses.edit', $course) }}" 
                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5>No courses found</h5>
                <p class="text-muted">Create your first German language course (A1-C2).</p>
                <a href="{{ route('courses.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Add First Course
                </a>
            </div>
        @endif
    </div>
</div>
@endsection