@extends('layouts.admin')

@section('title', 'Add New Teacher')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>Add New Teacher
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
                <form action="{{ route('teachers.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email') }}" required>
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
                                       id="phone" name="phone" value="{{ old('phone') }}" required>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" 
                                       id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                                @error('date_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" 
                                  id="address" name="address" rows="3" required>{{ old('address') }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="qualification" class="form-label">Qualification *</label>
                                <input type="text" class="form-control @error('qualification') is-invalid @enderror" 
                                       id="qualification" name="qualification" value="{{ old('qualification') }}" 
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
                                       value="{{ old('experience_years') }}" required>
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
                                    <option value="A1-A2" {{ old('specialization') === 'A1-A2' ? 'selected' : '' }}>Beginner Levels (A1-A2)</option>
                                    <option value="B1-B2" {{ old('specialization') === 'B1-B2' ? 'selected' : '' }}>Intermediate Levels (B1-B2)</option>
                                    <option value="C1-C2" {{ old('specialization') === 'C1-C2' ? 'selected' : '' }}>Advanced Levels (C1-C2)</option>
                                    <option value="All Levels" {{ old('specialization') === 'All Levels' ? 'selected' : '' }}>All Levels</option>
                                    <option value="Business German" {{ old('specialization') === 'Business German' ? 'selected' : '' }}>Business German</option>
                                    <option value="Exam Preparation" {{ old('specialization') === 'Exam Preparation' ? 'selected' : '' }}>Exam Preparation</option>
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
                                       id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact') }}"
                                       placeholder="Emergency contact number">
                                @error('emergency_contact')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Salary Configuration Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-money-bill-wave me-2"></i>Salary Configuration
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Salary Type *</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="salary_type" 
                                                   id="salary_type_monthly" value="monthly" 
                                                   {{ old('salary_type', 'monthly') === 'monthly' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="salary_type_monthly">
                                                <i class="fas fa-calendar me-1"></i>Monthly Salary
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="salary_type" 
                                                   id="salary_type_per_batch" value="per_batch"
                                                   {{ old('salary_type') === 'per_batch' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="salary_type_per_batch">
                                                <i class="fas fa-users me-1"></i>Per Batch
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="salary_type" 
                                                   id="salary_type_per_student" value="per_student"
                                                   {{ old('salary_type') === 'per_student' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="salary_type_per_student">
                                                <i class="fas fa-user me-1"></i>Per Student
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Monthly Salary Fields -->
                            <div id="monthly_salary_fields" class="salary-fields">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label">Monthly Salary Amount *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control @error('monthly_salary') is-invalid @enderror" 
                                               id="monthly_salary" name="monthly_salary" step="0.01" min="0"
                                               value="{{ old('monthly_salary') }}">
                                    </div>
                                    @error('monthly_salary')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Per Batch Salary Fields -->
                            <div id="per_batch_salary_fields" class="salary-fields" style="display: none;">
                                <div class="mb-3">
                                    <label for="per_batch_amount" class="form-label">Amount Per Batch *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control @error('per_batch_amount') is-invalid @enderror" 
                                               id="per_batch_amount" name="per_batch_amount" step="0.01" min="0"
                                               value="{{ old('per_batch_amount') }}" disabled>
                                    </div>
                                    <div class="form-text">Fixed amount teacher will receive for completing each batch</div>
                                    @error('per_batch_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Per Student Salary Fields -->
                            <div id="per_student_salary_fields" class="salary-fields" style="display: none;">
                                <div class="mb-3">
                                    <label for="per_student_amount" class="form-label">Amount Per Student *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control @error('per_student_amount') is-invalid @enderror" 
                                               id="per_student_amount" name="per_student_amount" step="0.01" min="0"
                                               value="{{ old('per_student_amount') }}" disabled>
                                    </div>
                                    <div class="form-text">Amount teacher will receive per student per month in each batch</div>
                                    @error('per_student_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hire_date" class="form-label">Hire Date *</label>
                                <input type="date" class="form-control @error('hire_date') is-invalid @enderror" 
                                       id="hire_date" name="hire_date" value="{{ old('hire_date') }}" required>
                                @error('hire_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label">Biography</label>
                        <textarea class="form-control @error('bio') is-invalid @enderror" 
                                  id="bio" name="bio" rows="4" 
                                  placeholder="Brief biography, teaching philosophy, achievements, etc.">{{ old('bio') }}</textarea>
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
                                           id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Active Teacher
                                    </label>
                                </div>
                                <div class="form-text">Teacher will be active and able to login</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('teachers.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Teacher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Salary Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Salary Information
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Salary Types:</h6>
                    
                    <div class="mb-3">
                        <strong><i class="fas fa-calendar me-1"></i> Monthly Salary:</strong>
                        <p class="small mb-2">Fixed monthly payment regardless of batches or students</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong><i class="fas fa-users me-1"></i> Per Batch:</strong>
                        <p class="small mb-2">Fixed amount per batch completion (suitable for short courses)</p>
                    </div>
                    
                    <div class="mb-0">
                        <strong><i class="fas fa-user me-1"></i> Per Student:</strong>
                        <p class="small mb-0">Payment based on number of students in assigned batches</p>
                    </div>
                </div>
                
                <div id="salary_preview" class="mt-3">
                    <h6>Salary Preview:</h6>
                    <div id="salary_calculation" class="text-muted">
                        Select salary type to see calculation
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const salaryTypeInputs = document.querySelectorAll('input[name="salary_type"]');
    const salaryFields = document.querySelectorAll('.salary-fields');
    
    function toggleSalaryFields() {
        const selectedType = document.querySelector('input[name="salary_type"]:checked').value;
        
        // Hide all salary fields and disable inputs
        salaryFields.forEach(field => {
            field.style.display = 'none';
            const input = field.querySelector('input[type="number"]');
            if (input) {
                input.disabled = true;
                input.value = '';
            }
        });
        
        // Show selected salary field and enable input
        const activeField = document.getElementById(`${selectedType}_salary_fields`);
        activeField.style.display = 'block';
        const activeInput = activeField.querySelector('input[type="number"]');
        if (activeInput) {
            activeInput.disabled = false;
        }
        
        updateSalaryPreview();
    }
    
    function updateSalaryPreview() {
        const selectedType = document.querySelector('input[name="salary_type"]:checked').value;
        const previewDiv = document.getElementById('salary_calculation');
        
        switch(selectedType) {
            case 'monthly':
                const monthlyAmount = document.getElementById('monthly_salary').value || 0;
                previewDiv.innerHTML = `
                    <strong>Monthly: Rs. ${Number(monthlyAmount).toLocaleString()}</strong><br>
                    <small class="text-muted">Fixed amount every month</small>
                `;
                break;
            case 'per_batch':
                const batchAmount = document.getElementById('per_batch_amount').value || 0;
                previewDiv.innerHTML = `
                    <strong>Per Batch: Rs. ${Number(batchAmount).toLocaleString()}</strong><br>
                    <small class="text-muted">Amount per completed batch</small>
                `;
                break;
            case 'per_student':
                const studentAmount = document.getElementById('per_student_amount').value || 0;
                previewDiv.innerHTML = `
                    <strong>Per Student: Rs. ${Number(studentAmount).toLocaleString()}</strong><br>
                    <small class="text-muted">Monthly amount per student in batch</small>
                `;
                break;
        }
    }
    
    // Add event listeners
    salaryTypeInputs.forEach(input => {
        input.addEventListener('change', toggleSalaryFields);
    });
    
    // Add event listeners to amount inputs
    document.getElementById('monthly_salary').addEventListener('input', updateSalaryPreview);
    document.getElementById('per_batch_amount').addEventListener('input', updateSalaryPreview);
    document.getElementById('per_student_amount').addEventListener('input', updateSalaryPreview);
    
    // Initialize on load
    toggleSalaryFields();
});
</script>

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

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.salary-fields {
    transition: all 0.3s ease;
}

#salary_preview {
    border-top: 1px solid #dee2e6;
    padding-top: 1rem;
}
</style>
@endsection