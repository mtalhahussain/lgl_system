@extends('layouts.admin')

@section('title', 'Students Management')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>Students Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('students.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add New Student
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card stat-card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-3"></i>
                <h3>{{ $stats['total'] }}</h3>
                <p class="card-text">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-success">
            <div class="card-body text-center">
                <i class="fas fa-user-check fa-2x mb-3"></i>
                <h3>{{ $stats['active'] }}</h3>
                <p class="card-text">Active Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h3>{{ $stats['pending_fees'] }}</h3>
                <p class="card-text">Pending Fees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card stat-card-info">
            <div class="card-body text-center">
                <i class="fas fa-graduation-cap fa-2x mb-3"></i>
                <h3>{{ $stats['completed_courses'] }}</h3>
                <p class="card-text">Completed Courses</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card dashboard-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search Students</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="{{ request('search') }}" placeholder="Name, Email, Phone, Student ID">
            </div>
            <div class="col-md-3">
                <label for="course_id" class="form-label">Filter by Course</label>
                <select class="form-select" id="course_id" name="course_id">
                    <option value="">All Courses</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" 
                                {{ request('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->name }} ({{ $course->level }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="{{ route('students.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card dashboard-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Students List
        </h5>
        <span class="badge bg-primary">{{ $students->total() }} Total</span>
    </div>
    <div class="card-body">
        @if($students->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student Info</th>
                            <th>Contact</th>
                            <th>Enrollments</th>
                            <th>Fee Status</th>
                            <th>Biometric</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $student->name }}</strong>
                                        <br>
                                        <small class="text-muted">ID: {{ $student->student_id }}</small>
                                        @if($student->enrollments->where('status', 'active')->count() > 0)
                                            <span class="badge bg-success ms-2">Active</span>
                                        @else
                                            <span class="badge bg-secondary ms-2">Inactive</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope me-1"></i>{{ $student->email }}
                                        <br>
                                        <i class="fas fa-phone me-1"></i>{{ $student->phone }}
                                    </div>
                                </td>
                                <td>
                                    @if($student->enrollments->count() > 0)
                                        @foreach($student->enrollments->take(2) as $enrollment)
                                            <div class="mb-1">
                                                <span class="badge bg-info">
                                                    {{ $enrollment->batch->course->name }} - {{ $enrollment->batch->course->level }}
                                                </span>
                                            </div>
                                        @endforeach
                                        @if($student->enrollments->count() > 2)
                                            <small class="text-muted">+{{ $student->enrollments->count() - 2 }} more</small>
                                        @endif
                                    @else
                                        <span class="text-muted">No enrollments</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $pendingAmount = $student->feeInstallments->where('status', 'pending')->sum('amount');
                                        $paidAmount = $student->feeInstallments->where('status', 'paid')->sum('amount');
                                    @endphp
                                    @if($pendingAmount > 0)
                                        <span class="badge bg-warning">{{ currency_format($pendingAmount) }} Due</span>
                                    @else
                                        <span class="badge bg-success">Paid</span>
                                    @endif
                                    @if($paidAmount > 0)
                                        <br><small class="text-muted">Paid: {{ currency_format($paidAmount) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($student->fingerprint_enrolled)
                                        <span class="badge bg-success mb-1">
                                            <i class="fas fa-fingerprint me-1"></i>Enrolled
                                        </span>
                                        <br>
                                        <small class="text-muted">ID: {{ $student->device_employee_no }}</small>
                                        <br>
                                        <button class="btn btn-xs btn-outline-danger" 
                                                onclick="removeFingerprintModal({{ $student->id }}, '{{ $student->name }}')"
                                                title="Remove Fingerprint">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    @else
                                        <span class="badge bg-secondary mb-1">
                                            <i class="fas fa-fingerprint me-1"></i>Not Enrolled
                                        </span>
                                        <br>
                                        <button class="btn btn-xs btn-outline-primary" 
                                                onclick="enrollFingerprintModal({{ $student->id }}, '{{ $student->name }}')"
                                                title="Enroll Fingerprint">
                                            <i class="fas fa-plus"></i> Enroll
                                        </button>
                                    @endif
                                </td>
                                <td>
                                    <span title="{{ $student->created_at->format('d M Y H:i') }}">
                                        {{ $student->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('students.show', $student) }}" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('students.edit', $student) }}" 
                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('students.destroy', $student) }}" 
                                              style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this student?')">
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
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} results
                </div>
                {{ $students->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5>No students found</h5>
                <p class="text-muted">
                    @if(request()->has('search') || request()->has('course_id'))
                        No students match your search criteria.
                    @else
                        Start by adding your first student.
                    @endif
                </p>
                <a href="{{ route('students.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Add First Student
                </a>
            </div>
        @endif
    </div>
</div>

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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Instructions:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Enter a unique employee number</li>
                        <li>Click "Start Enrollment"</li>
                        <li>Ask student to place finger on biometric device</li>
                        <li>Follow device prompts to complete enrollment</li>
                    </ol>
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
                    The student will no longer be able to use fingerprint attendance.
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

<script>
let currentStudentId = null;

function enrollFingerprintModal(studentId, studentName) {
    currentStudentId = studentId;
    document.getElementById('enrollStudentName').textContent = studentName;
    document.getElementById('deviceEmployeeNo').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('enrollFingerprintModal'));
    modal.show();
}

function removeFingerprintModal(studentId, studentName) {
    currentStudentId = studentId;
    document.getElementById('removeStudentName').textContent = studentName;
    
    const modal = new bootstrap.Modal(document.getElementById('removeFingerprintModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle fingerprint enrollment
    document.getElementById('startEnrollmentBtn').addEventListener('click', function() {
        const deviceEmployeeNo = document.getElementById('deviceEmployeeNo').value;
        
        if (!deviceEmployeeNo || deviceEmployeeNo < 1 || deviceEmployeeNo > 9999) {
            alert('Please enter a valid device employee number (1-9999)');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enrolling...';
        
        fetch(`/students/${currentStudentId}/fingerprint/enroll`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                device_employee_no: parseInt(deviceEmployeeNo)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Fingerprint enrolled successfully!\n\nDevice Employee ID: ' + data.device_employee_no);
                location.reload();
            } else {
                alert('❌ Enrollment failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Failed to enroll fingerprint. Please check console for details.');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-fingerprint me-1"></i>Start Enrollment';
            bootstrap.Modal.getInstance(document.getElementById('enrollFingerprintModal')).hide();
        });
    });
    
    // Handle fingerprint removal
    document.getElementById('confirmRemoveBtn').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Removing...';
        
        fetch(`/students/${currentStudentId}/fingerprint`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Fingerprint removed successfully!');
                location.reload();
            } else {
                alert('❌ Removal failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Failed to remove fingerprint. Please check console for details.');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-trash me-1"></i>Remove Fingerprint';
            bootstrap.Modal.getInstance(document.getElementById('removeFingerprintModal')).hide();
        });
    });
});
</script>

<style>
.btn-xs {
    padding: 0.125rem 0.25rem;
    font-size: 0.75rem;
    border-radius: 0.25rem;
}
</style>
@endsection