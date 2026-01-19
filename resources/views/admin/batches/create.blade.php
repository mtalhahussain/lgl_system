@extends('layouts.admin')

@section('title', 'Create New Batch')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>Create New Batch
    </h1>
    <div class="d-flex align-items-center">
        <a href="{{ url('/batches') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Batches
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle me-2"></i>Batch Information
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ url('/batches') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-tag me-1"></i>Batch Name
                                </label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" 
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
                                    @if(isset($courses))
                                        @foreach($courses as $course)
                                            <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                                                {{ $course->name }} ({{ $course->level }})
                                            </option>
                                        @endforeach
                                    @endif
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
                                    @if(isset($teachers))
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('teacher_id')
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
                                       id="max_students" name="max_students" value="{{ old('max_students', 15) }}" 
                                       min="1" max="50">
                                @error('max_students')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar-day me-1"></i>Start Date
                                </label>
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                       id="start_date" name="start_date" value="{{ old('start_date') }}">
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
                                       id="end_date" name="end_date" value="{{ old('end_date') }}">
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
                                       id="class_time" name="class_time" value="{{ old('class_time') }}" 
                                       placeholder="e.g., 09:00 - 10:30">
                                @error('class_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="meeting_platform" class="form-label">
                                    <i class="fas fa-video me-1"></i>Meeting Platform
                                </label>
                                <select class="form-select @error('meeting_platform') is-invalid @enderror" 
                                        id="meeting_platform" name="meeting_platform">
                                    <option value="">Select Platform</option>
                                    <option value="in_person" {{ old('meeting_platform') == 'in_person' ? 'selected' : '' }}>In Person</option>
                                    <option value="zoom" {{ old('meeting_platform') == 'zoom' ? 'selected' : '' }}>Zoom</option>
                                    <option value="google_meet" {{ old('meeting_platform') == 'google_meet' ? 'selected' : '' }}>Google Meet</option>
                                </select>
                                @error('meeting_platform')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="days_of_week" class="form-label">
                            <i class="fas fa-calendar-week me-1"></i>Days of Week
                        </label>
                        <div class="row">
                            @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                <div class="col-md-3 col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="day_{{ $day }}" name="days_of_week[]" value="{{ $day }}"
                                               {{ is_array(old('days_of_week')) && in_array($day, old('days_of_week')) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="day_{{ $day }}">
                                            {{ ucfirst($day) }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('days_of_week')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="meeting_link" class="form-label">
                            <i class="fas fa-link me-1"></i>Meeting Link (Optional)
                        </label>
                        <input type="url" class="form-control @error('meeting_link') is-invalid @enderror" 
                               id="meeting_link" name="meeting_link" value="{{ old('meeting_link') }}" 
                               placeholder="https://zoom.us/j/123456789">
                        @error('meeting_link')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="meeting_password" class="form-label">
                            <i class="fas fa-key me-1"></i>Meeting Password (Optional)
                        </label>
                        <input type="text" class="form-control @error('meeting_password') is-invalid @enderror" 
                               id="meeting_password" name="meeting_password" value="{{ old('meeting_password') }}" 
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
                                  placeholder="Additional information about the batch...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Batch
                        </button>
                        <a href="{{ url('/batches') }}" class="btn btn-outline-secondary">
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
                    <i class="fas fa-info-circle me-2"></i>Batch Guidelines
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Tips for Creating Batches</h6>
                    <ul class="mb-0">
                        <li>Choose a descriptive name that includes level and time</li>
                        <li>Ensure the teacher is available for selected days/times</li>
                        <li>Set realistic maximum student limits</li>
                        <li>Consider the course duration when setting dates</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes</h6>
                    <ul class="mb-0">
                        <li>Start date should be in the future</li>
                        <li>End date must be after start date</li>
                        <li>Select at least one day of the week</li>
                        <li>Meeting links are required for online classes</li>
                    </ul>
                </div>
                
                <div class="card mt-3">
                    <div class="card-body">
                        <h6><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h5 class="text-primary">{{ $totalBatches ?? 0 }}</h5>
                                    <small class="text-muted">Total Batches</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success">{{ $activeBatches ?? 0 }}</h5>
                                <small class="text-muted">Active Batches</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-set end date based on course duration
    const courseSelect = document.getElementById('course_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    function updateEndDate() {
        if (startDateInput.value && courseSelect.value) {
            const startDate = new Date(startDateInput.value);
            // Add 12 weeks as default duration
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + (12 * 7));
            
            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');
            
            endDateInput.value = `${year}-${month}-${day}`;
        }
    }
    
    startDateInput.addEventListener('change', updateEndDate);
    courseSelect.addEventListener('change', updateEndDate);
    
    // Show/hide meeting fields based on platform
    const platformSelect = document.getElementById('meeting_platform');
    const meetingLink = document.getElementById('meeting_link');
    const meetingPassword = document.getElementById('meeting_password');
    
    platformSelect.addEventListener('change', function() {
        const isOnline = this.value !== 'in_person' && this.value !== '';
        meetingLink.closest('.mb-3').style.display = isOnline ? 'block' : 'none';
        meetingPassword.closest('.mb-3').style.display = isOnline ? 'block' : 'none';
        
        if (isOnline) {
            meetingLink.setAttribute('required', 'required');
        } else {
            meetingLink.removeAttribute('required');
            meetingLink.value = '';
            meetingPassword.value = '';
        }
    });
    
    // Trigger initial check
    platformSelect.dispatchEvent(new Event('change'));
});
</script>
@endsection