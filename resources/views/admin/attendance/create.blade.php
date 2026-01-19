@extends('layouts.admin')

@section('title', 'Mark Attendance')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-check-circle me-2"></i>Mark Attendance
    </h1>
    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Attendance
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-check me-2"></i>Select Batch and Date
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('attendance.create') }}" class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="batch_id" class="form-label">Select Batch</label>
                            <select name="batch_id" id="batch_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Choose a batch...</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}" {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
                                        {{ $batch->name }} - {{ $batch->course->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($batches->count() == 0)
                                <div class="form-text text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    No active batches available. 
                                    @if(auth()->user()->role === 'teacher')
                                        You may not be assigned to any active batches.
                                    @endif
                                </div>
                            @else
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    {{ $batches->count() }} active {{ $batches->count() == 1 ? 'batch' : 'batches' }} available
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label for="date" class="form-label">Session Date</label>
                            <input type="date" name="date" id="date" class="form-control" 
                                   value="{{ $date }}" onchange="this.form.submit()">
                        </div>
                    </div>
                </form>

                @if($selectedBatch && $students->count() > 0)
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Found {{ $students->count() }} active students in {{ $selectedBatch->name }}
                    </div>
                    
                    <form method="POST" action="{{ route('attendance.store') }}">
                        @csrf
                        <input type="hidden" name="batch_id" value="{{ $selectedBatch->id }}">
                        <input type="hidden" name="session_date" value="{{ $date }}">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" name="start_time" id="start_time" class="form-control" 
                                       value="{{ old('start_time', '09:00') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" name="end_time" id="end_time" class="form-control" 
                                       value="{{ old('end_time', '11:00') }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="topic" class="form-label">Session Topic</label>
                            <input type="text" name="topic" id="topic" class="form-control" 
                                   value="{{ old('topic') }}" placeholder="e.g., German Grammar - Articles" required>
                        </div>

                        <div class="mb-3">
                            <label for="session_type" class="form-label">Session Type</label>
                            <select name="session_type" id="session_type" class="form-select">
                                <option value="regular">Regular Class</option>
                                <option value="assessment">Assessment</option>
                                <option value="review">Review Session</option>
                                <option value="practical">Practical Session</option>
                            </select>
                        </div>

                        <!-- Biometric Attendance Options -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-fingerprint me-2"></i>Biometric Options
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input type="checkbox" name="enable_biometric" id="enable_biometric" 
                                                   class="form-check-input" value="1">
                                            <label class="form-check-label" for="enable_biometric">
                                                <strong>Enable Biometric Attendance</strong>
                                                <div class="small text-muted">Use fingerprint device for attendance</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input type="checkbox" name="auto_mark_absent" id="auto_mark_absent" 
                                                   class="form-check-input" value="1">
                                            <label class="form-check-label" for="auto_mark_absent">
                                                <strong>Auto Mark Absent</strong>
                                                <div class="small text-muted">Mark students absent if not checked in</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row biometric-options d-none">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            When biometric is enabled:
                                            <ul class="mb-0 mt-2">
                                                <li>Students will check-in using fingerprint device</li>
                                                <li>Manual attendance will be optional for troubleshooting</li>
                                                <li>System will sync attendance from device automatically</li>
                                                <li>If auto-absent is enabled, unmarked students will be marked absent</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Biometric Session Duration (minutes)</label>
                                                <select name="biometric_duration" class="form-select">
                                                    <option value="15">15 minutes</option>
                                                    <option value="30" selected>30 minutes</option>
                                                    <option value="45">45 minutes</option>
                                                    <option value="60">60 minutes</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-outline-primary" id="testBiometric">
                                                    <i class="fas fa-wifi me-1"></i>Test Device Connection
                                                </button>
                                                <div class="small text-muted mt-1">
                                                    Check if biometric device is connected
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-2"></i>Mark Attendance for {{ $selectedBatch->name }}
                                    <span class="badge bg-info ms-2">{{ $students->count() }} Students</span>
                                </h6>
                                <div>
                                    <button type="button" class="btn btn-sm btn-success me-1" onclick="markAllPresent()">
                                        <i class="fas fa-check-circle me-1"></i>All Present
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="markAllAbsent()">
                                        <i class="fas fa-times-circle me-1"></i>All Absent
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th width="40%">Student Name</th>
                                                <th width="30%">Status</th>
                                                <th width="20%">Check-in Time</th>
                                                <th width="10%">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($students as $index => $student)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar avatar-sm me-3">
                                                                <span class="badge bg-primary rounded-circle">
                                                                    {{ substr($student->name, 0, 1) }}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <strong>{{ $student->name }}</strong>
                                                                <div class="small text-muted">{{ $student->email }}</div>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="attendance[{{ $index }}][student_id]" value="{{ $student->id }}">
                                                    </td>
                                                    <td>
                                                        <select name="attendance[{{ $index }}][status]" 
                                                                class="form-select form-select-sm attendance-status">
                                                            <option value="">Not Marked</option>
                                                            <option value="present">Present</option>
                                                            <option value="late">Late</option>
                                                            <option value="absent">Absent</option>
                                                            <option value="excused">Excused</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="time" 
                                                               name="attendance[{{ $index }}][check_in_time]" 
                                                               class="form-control form-control-sm check-in-time"
                                                               placeholder="--:--">
                                                    </td>
                                                    <td>
                                                        <input type="text" 
                                                               name="attendance[{{ $index }}][notes]" 
                                                               class="form-control form-control-sm"
                                                               placeholder="Optional notes"
                                                               maxlength="100">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Make sure to mark attendance for all students before submitting.
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-secondary me-2" onclick="window.history.back()">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Submit Attendance
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                @elseif($selectedBatch && $students->count() == 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No active students found for {{ $selectedBatch->name }}</strong><br>
                        <small>
                            Debug Info:<br>
                            - Batch ID: {{ $selectedBatch->id }}<br>
                            - Total Enrollments: {{ $selectedBatch->enrollments()->count() }}<br>
                            - Active Enrollments: {{ $selectedBatch->enrollments()->where('status', 'active')->count() }}<br>
                        </small>
                        Please check if students are enrolled and have 'active' status in this batch.
                    </div>
                @elseif(request('batch_id') && !$selectedBatch)
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        Batch not found or you don't have permission to access it.
                    </div>
                @endif
                
                {{-- Debug Information --}}
                <div class="mt-3">
                    <details class="bg-light p-3 rounded">
                        <summary class="fw-bold mb-2 text-primary">🔍 System Debug Information (Click to expand)</summary>
                        <div class="small">
                            <strong>User Information:</strong><br>
                            - Role: {{ auth()->user()->role }}<br>
                            - User ID: {{ auth()->user()->id }}<br>
                            - Name: {{ auth()->user()->name }}<br>
                            <br>
                            
                            <strong>Batch Query Results:</strong><br>
                            - Available Batches: {{ $batches->count() }}<br>
                            @if($batches->count() == 0)
                                <span class="text-danger">❌ No batches found!</span><br>
                                
                                @php
                                    // Let's check what batches exist in total
                                    $allBatches = \App\Models\Batch::with('course', 'teacher')->get();
                                    $activeBatches = \App\Models\Batch::where('status', 'active')->get();
                                @endphp
                                
                                <br><strong>Database Debug:</strong><br>
                                - Total Batches in DB: {{ $allBatches->count() }}<br>
                                - Active Batches in DB: {{ $activeBatches->count() }}<br>
                                
                                @if(auth()->user()->role === 'teacher')
                                    @php
                                        $teacherBatches = \App\Models\Batch::where('teacher_id', auth()->id())->get();
                                        $teacherActiveBatches = \App\Models\Batch::where('teacher_id', auth()->id())->where('status', 'active')->get();
                                    @endphp
                                    - Your Total Batches: {{ $teacherBatches->count() }}<br>
                                    - Your Active Batches: {{ $teacherActiveBatches->count() }}<br>
                                @endif
                                
                                @if($allBatches->count() > 0)
                                    <br><strong>All Batches in Database:</strong><br>
                                    @foreach($allBatches as $batch)
                                        - {{ $batch->name }} ({{ $batch->course->name ?? 'No Course' }}, 
                                          Status: {{ $batch->status }}, 
                                          Teacher: {{ $batch->teacher->name ?? 'None' }})<br>
                                    @endforeach
                                @else
                                    <span class="text-danger">❌ Database is empty - no batches exist!</span>
                                @endif
                            @else
                                @foreach($batches as $batch)
                                    - {{ $batch->name }} ({{ $batch->course->name }}, Teacher: {{ $batch->teacher->name ?? 'None' }})<br>
                                @endforeach
                            @endif
                            
                            @if(request('batch_id'))
                                <br><strong>Selected Batch Info:</strong><br>
                                - Batch ID: {{ request('batch_id') }}<br>
                                - Date: {{ $date }}<br>
                                
                                @if($selectedBatch)
                                    - Name: {{ $selectedBatch->name }}<br>
                                    - Teacher: {{ $selectedBatch->teacher->name ?? 'None' }}<br>
                                    - Status: {{ $selectedBatch->status }}<br>
                                    - Course: {{ $selectedBatch->course->name }}<br>
                                    <br>
                                    
                                    <strong>Enrollment Data:</strong><br>
                                    @php
                                        $allEnrollments = $selectedBatch->enrollments;
                                        $activeEnrollments = $selectedBatch->enrollments->where('status', 'active');
                                    @endphp
                                    - Total Enrollments: {{ $allEnrollments->count() }}<br>
                                    - Active Enrollments: {{ $activeEnrollments->count() }}<br>
                                    
                                    @if($allEnrollments->count() > 0)
                                        <br><strong>All Enrollments:</strong><br>
                                        @foreach($allEnrollments as $enrollment)
                                            - Student: {{ $enrollment->student->name ?? 'N/A' }} (Status: {{ $enrollment->status }})<br>
                                        @endforeach
                                    @else
                                        <span class="text-danger">❌ No enrollments found in this batch!</span>
                                    @endif
                                @else
                                    <strong class="text-danger">❌ Selected batch not found or no access</strong>
                                @endif
                            @endif
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        @if($selectedBatch)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Batch Information
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Batch:</strong></td>
                            <td>{{ $selectedBatch->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Course:</strong></td>
                            <td>{{ $selectedBatch->course->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Level:</strong></td>
                            <td>
                                <span class="badge bg-info">{{ $selectedBatch->course->level }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Teacher:</strong></td>
                            <td>{{ $selectedBatch->teacher->name ?? 'Not assigned' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Total Students:</strong></td>
                            <td>
                                <span class="badge bg-primary">{{ $students->count() }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Schedule:</strong></td>
                            <td>{{ $selectedBatch->schedule ?? 'Not set' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge bg-{{ $selectedBatch->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($selectedBatch->status) }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock me-2"></i>Attendance Legend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success me-2">Present</span>
                            <span class="small">Student attended on time</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-warning me-2">Late</span>
                            <span class="small">Student arrived late</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger me-2">Absent</span>
                            <span class="small">Student did not attend</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-info me-2">Excused</span>
                            <span class="small">Absence was pre-approved</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
function markAllPresent() {
    const statusSelects = document.querySelectorAll('.attendance-status');
    const checkInInputs = document.querySelectorAll('.check-in-time');
    const currentTime = new Date().toLocaleTimeString('en-US', { 
        hour12: false, 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    
    statusSelects.forEach(select => {
        select.value = 'present';
    });
    
    checkInInputs.forEach(input => {
        input.value = currentTime;
    });
}

function markAllAbsent() {
    const statusSelects = document.querySelectorAll('.attendance-status');
    const checkInInputs = document.querySelectorAll('.check-in-time');
    
    statusSelects.forEach(select => {
        select.value = 'absent';
    });
    
    checkInInputs.forEach(input => {
        input.value = '';
    });
}

// Auto-fill check-in time when status is changed to present or late
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('.attendance-status');
    
    statusSelects.forEach((select, index) => {
        select.addEventListener('change', function() {
            const checkInInput = document.querySelectorAll('.check-in-time')[index];
            
            if (this.value === 'present' || this.value === 'late') {
                if (!checkInInput.value) {
                    const currentTime = new Date().toLocaleTimeString('en-US', { 
                        hour12: false, 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                    checkInInput.value = currentTime;
                }
            } else {
                checkInInput.value = '';
            }
        });
    });
    
    // Biometric functionality
    const biometricCheckbox = document.getElementById('enable_biometric');
    const biometricOptions = document.querySelector('.biometric-options');
    const testBiometricBtn = document.getElementById('testBiometric');
    
    // Toggle biometric options
    if (biometricCheckbox) {
        biometricCheckbox.addEventListener('change', function() {
            if (this.checked) {
                biometricOptions.classList.remove('d-none');
            } else {
                biometricOptions.classList.add('d-none');
                document.getElementById('auto_mark_absent').checked = false;
            }
        });
    }
    
    // Test biometric device connection
    if (testBiometricBtn) {
        testBiometricBtn.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing...';
            
            fetch('{{ route("attendance.biometric.test") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Device connected successfully!\n\nDevice Info:\n' + 
                              '• Status: ' + data.status + '\n' +
                              '• Model: ' + (data.device_info?.model || 'Unknown') + '\n' +
                              '• Version: ' + (data.device_info?.version || 'Unknown'));
                    } else {
                        alert('❌ Device connection failed!\n\nError: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Failed to test device connection. Please check console for details.');
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-wifi me-1"></i>Test Device Connection';
                });
        });
    }
});

// Biometric session management
let biometricSession = {
    active: false,
    sessionId: null,
    interval: null
};

function startBiometricSession(sessionId) {
    if (biometricSession.active) {
        alert('Biometric session is already active!');
        return;
    }
    
    fetch('{{ route("attendance.biometric.start") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ session_id: sessionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            biometricSession.active = true;
            biometricSession.sessionId = sessionId;
            
            // Show biometric active indicator
            showBiometricStatus('Active - Students can check-in using fingerprint', 'success');
            
            // Auto-sync every 30 seconds
            biometricSession.interval = setInterval(() => {
                syncBiometricAttendance(sessionId);
            }, 30000);
            
            alert('✅ Biometric session started! Students can now check-in using fingerprint device.');
        } else {
            alert('❌ Failed to start biometric session: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to start biometric session. Please check console for details.');
    });
}

function endBiometricSession(sessionId) {
    if (!biometricSession.active) {
        alert('No active biometric session!');
        return;
    }
    
    fetch('{{ route("attendance.biometric.end") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ session_id: sessionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            biometricSession.active = false;
            biometricSession.sessionId = null;
            
            if (biometricSession.interval) {
                clearInterval(biometricSession.interval);
                biometricSession.interval = null;
            }
            
            showBiometricStatus('Session Ended', 'secondary');
            
            alert(`✅ Biometric session ended!\n\n` +
                  `📊 Summary:\n` +
                  `• Synced Records: ${data.synced_records}\n` +
                  `• Students Marked Absent: ${data.absent_marked}`);
        } else {
            alert('❌ Failed to end biometric session: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to end biometric session. Please check console for details.');
    });
}

function syncBiometricAttendance(sessionId) {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const sessionDate = document.querySelector('input[name="session_date"]').value;
    
    fetch('{{ route("attendance.biometric.sync") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            session_id: sessionId,
            start_time: `${sessionDate} ${startTime}`,
            end_time: `${sessionDate} ${endTime}`
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Synced biometric attendance:', data.records);
            updateAttendanceTable(data.records);
        } else {
            console.error('Sync failed:', data.message);
        }
    })
    .catch(error => {
        console.error('Sync error:', error);
    });
}

function updateAttendanceTable(records) {
    records.forEach(record => {
        // Find student row and update attendance
        const studentRows = document.querySelectorAll('input[name*="[student_id]"]');
        studentRows.forEach((input, index) => {
            if (input.value == record.student_id) {
                const statusSelect = document.querySelectorAll('.attendance-status')[index];
                const checkInInput = document.querySelectorAll('.check-in-time')[index];
                
                statusSelect.value = record.status;
                checkInInput.value = record.check_in_time;
                
                // Add visual indicator
                statusSelect.style.borderColor = '#28a745';
                statusSelect.style.backgroundColor = '#d4edda';
            }
        });
    });
}

function showBiometricStatus(message, type) {
    // Remove existing status
    const existingStatus = document.querySelector('.biometric-status');
    if (existingStatus) {
        existingStatus.remove();
    }
    
    // Create new status alert
    const statusAlert = document.createElement('div');
    statusAlert.className = `alert alert-${type} biometric-status`;
    statusAlert.innerHTML = `<i class="fas fa-fingerprint me-2"></i>${message}`;
    
    // Insert after biometric options card
    const biometricCard = document.querySelector('.card-header h6:has(.fa-fingerprint)').closest('.card');
    biometricCard.insertAdjacentElement('afterend', statusAlert);
}
</script>

<style>
.avatar {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.attendance-status:focus,
.check-in-time:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}
</style>
@endsection