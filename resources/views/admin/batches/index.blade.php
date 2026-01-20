@extends('layouts.admin')

@section('title', 'Batches Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>{{ auth()->user()->isTeacher() ? 'My Batches' : 'Batches Management' }}
    </h1>
    @if(!auth()->user()->isTeacher())
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('batches.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add New Batch
        </a>
    </div>
    @endif
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                <h3>{{ $stats['total'] ?? 0 }}</h3>
                <p class="card-text">Total Batches</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-play fa-2x mb-3"></i>
                <h3>{{ $stats['active'] ?? 0 }}</h3>
                <p class="card-text">Active Batches</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-check fa-2x mb-3"></i>
                <h3>{{ $stats['completed'] ?? 0 }}</h3>
                <p class="card-text">Completed</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-3"></i>
                <h3>{{ $stats['upcoming'] ?? 0 }}</h3>
                <p class="card-text">Upcoming</p>
            </div>
        </div>
    </div>
</div>

<div class="card dashboard-card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Batches List
        </h5>
    </div>
    <div class="card-body">
        @if($batches->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Batch Name</th>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Start Date</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Platform</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $batch)
                        <tr>
                            <td>
                                <strong>{{ $batch->name }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-primary">{{ $batch->course->name ?? 'N/A' }}</span>
                                <small class="d-block text-muted">{{ $batch->course->level ?? '' }}</small>
                            </td>
                            <td>
                                <i class="fas fa-user me-1"></i>{{ $batch->teacher->name ?? 'Not Assigned' }}
                            </td>
                            <td>
                                <i class="fas fa-calendar me-1"></i>{{ $batch->start_date->format('M d, Y') }}
                                @if($batch->end_date)
                                    <small class="d-block text-muted">to {{ $batch->end_date->format('M d, Y') }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $batch->enrollments->where('status', 'active')->count() }}/{{ $batch->max_students }}</span>
                            </td>
                            <td>
                                @switch($batch->status)
                                    @case('upcoming')
                                        <span class="badge bg-warning">Upcoming</span>
                                        @break
                                    @case('ongoing')
                                        <span class="badge bg-success">Ongoing</span>
                                        @break
                                    @case('completed')
                                        <span class="badge bg-secondary">Completed</span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge bg-danger">Cancelled</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">{{ ucfirst($batch->status) }}</span>
                                @endswitch
                            </td>
                            <td>
                                @switch($batch->meeting_platform)
                                    @case('zoom')
                                        <i class="fas fa-video text-primary" title="Zoom"></i> Zoom
                                        @break
                                    @case('google_meet')
                                        <i class="fas fa-video text-success" title="Google Meet"></i> Meet
                                        @break
                                    @case('in_person')
                                        <i class="fas fa-users text-info" title="In Person"></i> In Person
                                        @break
                                    @default
                                        {{ ucfirst(str_replace('_', ' ', $batch->meeting_platform)) }}
                                @endswitch
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ url('/batches/' . $batch->id) }}" 
                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ url('/batches/' . $batch->id . '/edit') }}" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ url('/batches/' . $batch->id) }}" 
                                          class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this batch?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                {{ $batches->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                <h5>No Batches Found</h5>
                <p class="text-muted">No batches have been created yet. Start by adding your first batch.</p>
                <a href="{{ url('/batches/create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Create First Batch
                </a>
            </div>
        @endif
    </div>
</div>
@endsection