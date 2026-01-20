@extends('layouts.admin')

@section('title', 'Edit Teacher - ' . $teacher->name)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-edit me-2"></i>Edit Teacher
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Teachers
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-info me-2"></i>Teacher Information
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('teachers.update', $teacher) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $teacher->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email', $teacher->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" name="phone" value="{{ old('phone', $teacher->phone) }}" required>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" 
                                       id="date_of_birth" name="date_of_birth" 
                                       value="{{ old('date_of_birth', $teacher->date_of_birth ? $teacher->date_of_birth->format('Y-m-d') : '') }}" required>
                                @error('date_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" 
                                  id="address" name="address" rows="3" required>{{ old('address', $teacher->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="qualification" class="form-label">Qualification *</label>
                                <input type="text" class="form-control @error('qualification') is-invalid @enderror" 
                                       id="qualification" name="qualification" 
                                       value="{{ old('qualification', $teacher->qualification) }}" 
                                       placeholder="e.g., Master's in German Literature" required>
                                @error('qualification')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="experience_years" class="form-label">Years of Experience *</label>
                                <input type="number" class="form-control @error('experience_years') is-invalid @enderror" 
                                       id="experience_years" name="experience_years" min="0" max="50"
                                       value="{{ old('experience_years', $teacher->experience_years) }}" required>
                                @error('experience_years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <select class="form-select @error('specialization') is-invalid @enderror" 
                                        id="specialization" name="specialization">
                                    <option value="">Select Specialization</option>
                                    <option value="A1-A2" {{ old('specialization', $teacher->specialization) === 'A1-A2' ? 'selected' : '' }}>Beginner Levels (A1-A2)</option>
                                    <option value="B1-B2" {{ old('specialization', $teacher->specialization) === 'B1-B2' ? 'selected' : '' }}>Intermediate Levels (B1-B2)</option>
                                    <option value="C1-C2" {{ old('specialization', $teacher->specialization) === 'C1-C2' ? 'selected' : '' }}>Advanced Levels (C1-C2)</option>
                                    <option value="All Levels" {{ old('specialization', $teacher->specialization) === 'All Levels' ? 'selected' : '' }}>All Levels</option>
                                    <option value="Business German" {{ old('specialization', $teacher->specialization) === 'Business German' ? 'selected' : '' }}>Business German</option>
                                    <option value="Exam Preparation" {{ old('specialization', $teacher->specialization) === 'Exam Preparation' ? 'selected' : '' }}>Exam Preparation</option>
                                </select>
                                @error('specialization')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control @error('emergency_contact') is-invalid @enderror" 
                                       id="emergency_contact" name="emergency_contact" 
                                       value="{{ old('emergency_contact', $teacher->emergency_contact) }}"
                                       placeholder="Emergency contact number">
                                @error('emergency_contact')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="salary" class="form-label">Monthly Salary *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" class="form-control @error('salary') is-invalid @enderror" 
                                           id="salary" name="salary" step="0.01" min="0"
                                           value="{{ old('salary', $teacher->salary) }}" required>
                                </div>
                                @error('salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hire_date" class="form-label">Hire Date *</label>
                                <input type="date" class="form-control @error('hire_date') is-invalid @enderror" 
                                       id="hire_date" name="hire_date" 
                                       value="{{ old('hire_date', $teacher->hire_date ? $teacher->hire_date->format('Y-m-d') : '') }}" required>
                                @error('hire_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label">Biography</label>
                        <textarea class="form-control @error('bio') is-invalid @enderror" 
                                  id="bio" name="bio" rows="4" 
                                  placeholder="Brief biography, teaching philosophy, achievements, etc.">{{ old('bio', $teacher->bio) }}</textarea>
                        @error('bio')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Optional: Teacher's background and teaching approach</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" 
                                           id="is_active" value="1" 
                                           {{ old('is_active', $teacher->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Active Teacher
                                    </label>
                                </div>
                                <div class="form-text">Uncheck to deactivate teacher account</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('teachers.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Teacher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Teacher Statistics -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Teacher Statistics
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Active Batches</span>
                        <span class="badge bg-primary">{{ $teacher->batches()->where('status', 'active')->count() }}</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: {{ $teacher->batches()->where('status', 'active')->count() * 20 }}%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Total Students</span>
                        <span class="badge bg-success">{{ $teacher->batches()->withCount('activeEnrollments')->get()->sum('active_enrollments_count') }}</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: {{ min($teacher->batches()->withCount('activeEnrollments')->get()->sum('active_enrollments_count') * 5, 100) }}%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Years Experience</span>
                        <span class="badge bg-info">{{ $teacher->experience_years ?? 0 }}</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-info" style="width: {{ min(($teacher->experience_years ?? 0) * 3.33, 100) }}%"></div>
                    </div>
                </div>

                <div class="mb-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Teaching Since</span>
                        <span class="badge bg-warning">{{ $teacher->hire_date ? $teacher->hire_date->format('Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($teacher->batches()->count() > 0)
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>Assigned Batches
                </h5>
            </div>
            <div class="card-body">
                @foreach($teacher->batches()->with('course')->get() as $batch)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>{{ $batch->name }}</strong><br>
                        <small class="text-muted">{{ $batch->course->name }}</small>
                    </div>
                    <span class="badge bg-{{ $batch->status === 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($batch->status) }}
                    </span>
                </div>
                @if(!$loop->last)
                    <hr class="my-2">
                @endif
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

<style>
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn {
    border-radius: 6px;
    font-weight: 500;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.progress {
    background-color: #e9ecef;
}

.badge {
    font-size: 0.75em;
}
</style>
@endsection