@extends('layouts.admin')

@section('title', 'Edit Student - ' . $student->name)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-edit me-2"></i>Edit Student
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Details
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Student Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('students.update', $student) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $student->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email', $student->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" name="phone" value="{{ old('phone', $student->phone) }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" 
                                       id="date_of_birth" name="date_of_birth" 
                                       value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d')) }}">
                                @error('date_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" 
                                  id="address" name="address" rows="3">{{ old('address', $student->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" placeholder="Leave blank to keep current password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">Only fill if you want to change the password</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" name="password_confirmation" 
                                       placeholder="Confirm new password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" 
                                   name="is_active" value="1" {{ old('is_active', $student->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Active Student
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('students.show', $student) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Student Status Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Student Status
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Student ID:</strong> {{ $student->student_id }}
                </div>
                <div class="mb-3">
                    <strong>Joined:</strong> {{ $student->created_at->format('d M Y') }}
                </div>
                <div class="mb-3">
                    <strong>Status:</strong> 
                    <span class="badge bg-{{ $student->is_active ? 'success' : 'danger' }}">
                        {{ $student->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                @if($student->fingerprint_enrolled)
                <div class="mb-3">
                    <strong>Biometric:</strong> 
                    <span class="badge bg-success">
                        <i class="fas fa-fingerprint me-1"></i>Enrolled (ID: {{ $student->device_employee_no }})
                    </span>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if(!$student->fingerprint_enrolled)
                    <button class="btn btn-outline-primary btn-sm" 
                            onclick="enrollFingerprintModal({{ $student->id }}, '{{ $student->name }}')">
                        <i class="fas fa-fingerprint me-1"></i>Enroll Fingerprint
                    </button>
                    @else
                    <button class="btn btn-outline-danger btn-sm" 
                            onclick="removeFingerprintModal({{ $student->id }}, '{{ $student->name }}')">
                        <i class="fas fa-times me-1"></i>Remove Fingerprint
                    </button>
                    @endif
                    
                    <a href="{{ route('attendance.student', $student) }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-calendar-check me-1"></i>View Attendance
                    </a>
                    
                    @if($student->enrollments->count() > 0)
                    <a href="{{ route('fees.student', $student) }}" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-money-bill-wave me-1"></i>View Fees
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include fingerprint modals if needed -->
@if(!$student->fingerprint_enrolled)
<!-- Fingerprint Enrollment Modal -->
<div class="modal fade" id="enrollFingerprintModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-fingerprint me-2"></i>Enroll Student Fingerprint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Enrolling fingerprint for: <strong id="enrollStudentName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Device Employee Number (1-9999)</label>
                    <input type="number" id="deviceEmployeeNo" class="form-control" 
                           min="1" max="9999" placeholder="Enter unique number for biometric device">
                    <div class="form-text">Each student must have a unique employee number on the device.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="startEnrollmentBtn">
                    <i class="fas fa-fingerprint me-1"></i>Start Enrollment
                </button>
            </div>
        </div>
    </div>
</div>
@else
<!-- Fingerprint Removal Modal -->
<div class="modal fade" id="removeFingerprintModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Remove Fingerprint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove fingerprint enrollment for:</p>
                <p><strong id="removeStudentName"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This will remove the student's fingerprint data from both the database and biometric device.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRemoveBtn">
                    <i class="fas fa-trash me-1"></i>Remove Fingerprint
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
// Fingerprint management functions (simplified version)
function enrollFingerprintModal(studentId, studentName) {
    document.getElementById('enrollStudentName').textContent = studentName;
    const modal = new bootstrap.Modal(document.getElementById('enrollFingerprintModal'));
    modal.show();
}

function removeFingerprintModal(studentId, studentName) {
    document.getElementById('removeStudentName').textContent = studentName;
    const modal = new bootstrap.Modal(document.getElementById('removeFingerprintModal'));
    modal.show();
}
</script>
@endsection