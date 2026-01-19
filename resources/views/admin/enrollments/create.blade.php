@extends('layouts.admin')

@section('title', 'Create Enrollment')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-user-plus me-2"></i>Create Enrollment</h2>
            <p class="text-muted mb-0">Enroll a student in a batch</p>
        </div>
        <div>
            <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Enrollments
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card dashboard-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-form me-2"></i>Enrollment Details
            </h5>
        </div>
        <div class="card-body">
            <form action="{{ route('enrollments.store') }}" method="POST">
                @csrf
                
                <div class="row">
                    <!-- Student Selection -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">
                                <i class="fas fa-user me-1"></i>Student <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('student_id') is-invalid @enderror" 
                                    id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}" 
                                            {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                        {{ $student->name }} ({{ $student->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Batch Selection -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="batch_id" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>Batch <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('batch_id') is-invalid @enderror" 
                                    id="batch_id" name="batch_id" required>
                                <option value="">Select Batch</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}" 
                                            {{ (old('batch_id') == $batch->id || ($selectedBatch && $selectedBatch->id == $batch->id)) ? 'selected' : '' }}
                                            data-course="{{ $batch->course->name }}"
                                            data-level="{{ $batch->course->level }}"
                                            data-fee="{{ $batch->course->total_fee }}"
                                            data-start="{{ $batch->start_date->format('M d, Y') }}">
                                        {{ $batch->name }} - {{ $batch->course->name }} ({{ $batch->course->level }}) - {{ currency_format($batch->course->total_fee) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('batch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Batch Information Display -->
                <div id="batch-info" class="row mb-3" style="display: none;">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-1"></i>Batch Information</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Course:</strong> <span id="course-name"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Level:</strong> <span id="course-level"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Fee:</strong> $<span id="course-fee"></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Start Date:</strong> <span id="start-date"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Discount -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="discount_percentage" class="form-label">
                                <i class="fas fa-percent me-1"></i>Discount Percentage
                            </label>
                            <input type="number" 
                                   class="form-control @error('discount_percentage') is-invalid @enderror" 
                                   id="discount_percentage" 
                                   name="discount_percentage" 
                                   value="{{ old('discount_percentage', 0) }}"
                                   min="0" 
                                   max="100" 
                                   step="0.01">
                            <div class="form-text">Enter percentage (0-100)</div>
                            @error('discount_percentage')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Installments -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="installments" class="form-label">
                                <i class="fas fa-credit-card me-1"></i>Number of Installments
                            </label>
                            <select class="form-select @error('installments') is-invalid @enderror" 
                                    id="installments" name="installments">
                                <option value="1" {{ old('installments', 1) == 1 ? 'selected' : '' }}>1 Installment (Full Payment)</option>
                                <option value="2" {{ old('installments') == 2 ? 'selected' : '' }}>2 Installments</option>
                                <option value="3" {{ old('installments') == 3 ? 'selected' : '' }}>3 Installments</option>
                                <option value="4" {{ old('installments') == 4 ? 'selected' : '' }}>4 Installments</option>
                                <option value="6" {{ old('installments') == 6 ? 'selected' : '' }}>6 Installments</option>
                                <option value="12" {{ old('installments') == 12 ? 'selected' : '' }}>12 Installments</option>
                            </select>
                            @error('installments')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Fee Calculation Display -->
                <div id="fee-calculation" class="mb-4" style="display: none;">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="fas fa-calculator me-1"></i>Fee Calculation</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Original Fee:</strong> $<span id="original-fee">0</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Discount:</strong> $<span id="discount-amount">0</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Total Fee:</strong> $<span id="total-fee">0</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Per Installment:</strong> $<span id="per-installment">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-end">
                    <a href="{{ route('enrollments.index') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Create Enrollment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const batchSelect = document.getElementById('batch_id');
    const discountInput = document.getElementById('discount_percentage');
    const installmentsSelect = document.getElementById('installments');
    
    function updateBatchInfo() {
        const selectedOption = batchSelect.options[batchSelect.selectedIndex];
        const batchInfo = document.getElementById('batch-info');
        const feeCalculation = document.getElementById('fee-calculation');
        
        if (selectedOption.value) {
            // Show batch info
            document.getElementById('course-name').textContent = selectedOption.dataset.course;
            document.getElementById('course-level').textContent = selectedOption.dataset.level;
            document.getElementById('course-fee').textContent = selectedOption.dataset.fee;
            document.getElementById('start-date').textContent = selectedOption.dataset.start;
            
            batchInfo.style.display = 'block';
            feeCalculation.style.display = 'block';
            
            updateFeeCalculation();
        } else {
            batchInfo.style.display = 'none';
            feeCalculation.style.display = 'none';
        }
    }
    
    function updateFeeCalculation() {
        const selectedOption = batchSelect.options[batchSelect.selectedIndex];
        if (!selectedOption.value) return;
        
        const originalFee = parseFloat(selectedOption.dataset.fee) || 0;
        const discountPercentage = parseFloat(discountInput.value) || 0;
        const installments = parseInt(installmentsSelect.value) || 1;
        
        const discountAmount = (originalFee * discountPercentage) / 100;
        const totalFee = originalFee - discountAmount;
        const perInstallment = totalFee / installments;
        
        document.getElementById('original-fee').textContent = originalFee.toFixed(2);
        document.getElementById('discount-amount').textContent = discountAmount.toFixed(2);
        document.getElementById('total-fee').textContent = totalFee.toFixed(2);
        document.getElementById('per-installment').textContent = perInstallment.toFixed(2);
    }
    
    batchSelect.addEventListener('change', updateBatchInfo);
    discountInput.addEventListener('input', updateFeeCalculation);
    installmentsSelect.addEventListener('change', updateFeeCalculation);
    
    // Initialize if batch is pre-selected
    if (batchSelect.value) {
        updateBatchInfo();
    }
});
</script>
@endsection