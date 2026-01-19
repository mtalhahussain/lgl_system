@extends('layouts.admin')

@section('title', 'Edit Batch')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>Edit Batch: {{ $batch->name }}
    </h1>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('batches.show', $batch) }}" class="btn btn-outline-info btn-sm">
            <i class="fas fa-eye me-1"></i>View Batch
        </a>
        <a href="{{ route('batches.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Batches
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit me-2"></i>Update Batch Information
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('batches.update', $batch) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-tag me-1"></i>Batch Name
                                </label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $batch->name) }}" 
                                       placeholder="e.g., German A1 - Morning Batch">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_id" class="form-label">
                                    <i class="fas fa-book me-1"></i>Course
                                </label>
                                <select class="form-select @error('course_id') is-invalid @enderror" 
                                        id="course_id" name="course_id">
                                    <option value="">Select Course</option>
                                    @foreach($courses as $course)
                                        <option value="{{ $course->id }}" 
                                                {{ old('course_id', $batch->course_id) == $course->id ? 'selected' : '' }}>
                                            {{ $course->name }} ({{ $course->level }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('course_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>Teacher
                                </label>
                                <select class="form-select @error('teacher_id') is-invalid @enderror" 
                                        id="teacher_id" name="teacher_id">
                                    <option value="">Select Teacher</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" 
                                                {{ old('teacher_id', $batch->teacher_id) == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    <i class="fas fa-info-circle me-1"></i>Status
                                </label>
                                <select class="form-select @error('status') is-invalid @enderror" 
                                        id="status" name="status">
                                    <option value="upcoming" {{ old('status', $batch->status) == 'upcoming' ? 'selected' : '' }}>
                                        Upcoming
                                    </option>
                                    <option value="active" {{ old('status', $batch->status) == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="ongoing" {{ old('status', $batch->status) == 'ongoing' ? 'selected' : '' }}>
                                        Ongoing
                                    </option>
                                    <option value="completed" {{ old('status', $batch->status) == 'completed' ? 'selected' : '' }}>
                                        Completed
                                    </option>
                                    <option value="cancelled" {{ old('status', $batch->status) == 'cancelled' ? 'selected' : '' }}>
                                        Cancelled
                                    </option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Start Date
                                </label>
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                       id="start_date" name="start_date" 
                                       value="{{ old('start_date', $batch->start_date->format('Y-m-d')) }}">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">
                                    <i class="fas fa-calendar-check me-1"></i>End Date
                                </label>
                                <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                       id="end_date" name="end_date" 
                                       value="{{ old('end_date', $batch->end_date->format('Y-m-d')) }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="class_time" class="form-label">
                                    <i class="fas fa-clock me-1"></i>Class Time
                                </label>
                                <input type="text" class="form-control @error('class_time') is-invalid @enderror" 
                                       id="class_time" name="class_time" 
                                       value="{{ old('class_time', $batch->class_time) }}" 
                                       placeholder="e.g., 09:00 - 11:00">
                                @error('class_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_students" class="form-label">
                                    <i class="fas fa-users me-1"></i>Maximum Students
                                </label>
                                <input type="number" class="form-control @error('max_students') is-invalid @enderror" 
                                       id="max_students" name="max_students" 
                                       value="{{ old('max_students', $batch->max_students) }}" 
                                       min="1" max="50">
                                @error('max_students')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-calendar-week me-1"></i>Days of Week
                        </label>
                        <div class="d-flex flex-wrap gap-3">
                            @php
                                $currentDays = old('days_of_week', explode(',', $batch->days_of_week ?? ''));
                                $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 
                                        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 
                                        'sunday' => 'Sunday'];
                            @endphp
                            @foreach($days as $value => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           value="{{ $value }}" id="{{ $value }}" name="days_of_week[]"
                                           {{ in_array($value, $currentDays) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="{{ $value }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('days_of_week')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="meeting_platform" class="form-label">
                            <i class="fas fa-video me-1"></i>Meeting Platform
                        </label>
                        <select class="form-select @error('meeting_platform') is-invalid @enderror" 
                                id="meeting_platform" name="meeting_platform">
                            <option value="in_person" {{ old('meeting_platform', $batch->meeting_platform) == 'in_person' ? 'selected' : '' }}>
                                In Person
                            </option>
                            <option value="zoom" {{ old('meeting_platform', $batch->meeting_platform) == 'zoom' ? 'selected' : '' }}>
                                Zoom
                            </option>
                            <option value="google_meet" {{ old('meeting_platform', $batch->meeting_platform) == 'google_meet' ? 'selected' : '' }}>
                                Google Meet
                            </option>
                            <option value="microsoft_teams" {{ old('meeting_platform', $batch->meeting_platform) == 'microsoft_teams' ? 'selected' : '' }}>
                                Microsoft Teams
                            </option>
                        </select>
                        @error('meeting_platform')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3" id="meeting_link_field">
                        <label for="meeting_link" class="form-label">
                            <i class="fas fa-link me-1"></i>Meeting Link
                        </label>
                        <input type="url" class="form-control @error('meeting_link') is-invalid @enderror" 
                               id="meeting_link" name="meeting_link" 
                               value="{{ old('meeting_link', $batch->meeting_link) }}" 
                               placeholder="https://zoom.us/j/...">
                        @error('meeting_link')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3" id="meeting_password_field">
                        <label for="meeting_password" class="form-label">
                            <i class="fas fa-key me-1"></i>Meeting Password (Optional)
                        </label>
                        <input type="text" class="form-control @error('meeting_password') is-invalid @enderror" 
                               id="meeting_password" name="meeting_password" 
                               value="{{ old('meeting_password', $batch->meeting_password) }}" 
                               placeholder="Meeting password if required">
                        @error('meeting_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">
                            <i class="fas fa-sticky-note me-1"></i>Notes (Optional)
                        </label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" 
                                  id="notes" name="notes" rows="3" 
                                  placeholder="Additional information about the batch...">{{ old('notes', $batch->notes) }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Batch
                        </button>
                        <a href="{{ route('batches.show', $batch) }}" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>View Batch
                        </a>
                        <a href="{{ route('batches.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Current Batch Status
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Current Status:</strong></td>
                        <td>
                            <span class="badge bg-{{ $batch->status == 'ongoing' ? 'success' : ($batch->status == 'upcoming' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($batch->status) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Enrolled Students:</strong></td>
                        <td>
                            <span class="badge bg-primary">{{ $batch->enrollments->where('status', 'active')->count() }}</span>
                            / {{ $batch->max_students }}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Created:</strong></td>
                        <td>{{ $batch->created_at->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Last Updated:</strong></td>
                        <td>{{ $batch->updated_at->diffForHumans() }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Update Guidelines
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-lightbulb me-2"></i>Important Notes</h6>
                    <ul class="mb-0">
                        <li>Changing dates may affect existing class schedules</li>
                        <li>Reducing max students may affect waitlisted students</li>
                        <li>Status changes will notify enrolled students</li>
                        <li>Teacher changes should be coordinated in advance</li>
                    </ul>
                </div>
                
                @if($batch->enrollments->where('status', 'active')->count() > 0)
                    <div class="alert alert-info">
                        <h6><i class="fas fa-users me-2"></i>Active Students</h6>
                        <p class="mb-1">This batch has {{ $batch->enrollments->where('status', 'active')->count() }} active students.</p>
                        <small class="text-muted">Be careful when making major changes.</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide meeting fields based on platform
    const platformSelect = document.getElementById('meeting_platform');
    const meetingLinkField = document.getElementById('meeting_link_field');
    const meetingPasswordField = document.getElementById('meeting_password_field');
    const meetingLinkInput = document.getElementById('meeting_link');
    
    function toggleMeetingFields() {
        const isOnline = platformSelect.value !== 'in_person' && platformSelect.value !== '';
        meetingLinkField.style.display = isOnline ? 'block' : 'none';
        meetingPasswordField.style.display = isOnline ? 'block' : 'none';
        
        if (isOnline) {
            meetingLinkInput.setAttribute('required', 'required');
        } else {
            meetingLinkInput.removeAttribute('required');
        }
    }
    
    platformSelect.addEventListener('change', toggleMeetingFields);
    
    // Trigger initial check
    toggleMeetingFields();
});
</script>
@endsection