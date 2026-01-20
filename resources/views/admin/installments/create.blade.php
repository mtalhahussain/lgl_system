@extends('layouts.admin')

@section('title', 'Create Fee Installments')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>Create Fee Installments
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ route('fees.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Fees
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-calculator me-2"></i>Fee Installment Setup
        </h5>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('installments.store') }}" method="POST" id="installmentForm">
            @csrf
            
            <div class="row">
                <!-- Student and Enrollment Selection -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Select Student Enrollment *</label>
                        <select name="enrollment_id" id="enrollmentSelect" class="form-select" required>
                            <option value="">Choose student enrollment</option>
                            @foreach($students as $student)
                                @foreach($student->enrollments as $enrollment)
                                    <option value="{{ $enrollment->id }}" 
                                            data-student="{{ $student->name }}"
                                            data-course="{{ $enrollment->batch->course->name }}"
                                            data-fees="{{ $enrollment->batch->course->total_fee }}"
                                            {{ old('enrollment_id') == $enrollment->id ? 'selected' : '' }}>
                                        {{ $student->name }} - {{ $enrollment->batch->course->name }} ({{ $enrollment->batch->name }})
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>

                    <div id="enrollmentDetails" class="alert alert-info" style="display: none;">
                        <h6><i class="fas fa-info-circle me-2"></i>Enrollment Details</h6>
                        <p><strong>Student:</strong> <span id="studentName"></span></p>
                        <p><strong>Course:</strong> <span id="courseName"></span></p>
                        <p class="mb-0"><strong>Total Fees:</strong> <span id="totalFees"></span></p>
                    </div>
                </div>

                <!-- Installment Configuration -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Payment Type *</label>
                        <select name="installment_type" id="installmentType" class="form-select" required>
                            <option value="">Select payment type</option>
                            <option value="full" {{ old('installment_type') === 'full' ? 'selected' : '' }}>Full Payment (One Time)</option>
                            <option value="monthly" {{ old('installment_type') === 'monthly' ? 'selected' : '' }}>Monthly Installments</option>
                            <option value="quarterly" {{ old('installment_type') === 'quarterly' ? 'selected' : '' }}>Quarterly Installments</option>
                            <option value="custom" {{ old('installment_type') === 'custom' ? 'selected' : '' }}>Custom Installments</option>
                        </select>
                    </div>

                    <div class="mb-3" id="installmentCountDiv" style="display: none;">
                        <label class="form-label">Number of Installments</label>
                        <select name="number_of_installments" id="numberOfInstallments" class="form-select">
                            <option value="2">2 Installments</option>
                            <option value="3" selected>3 Installments</option>
                            <option value="4">4 Installments</option>
                            <option value="6">6 Installments</option>
                            <option value="12">12 Installments</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', date('Y-m-d')) }}" required>
                    </div>
                </div>
            </div>

            <!-- First Payment Option -->
            <div class="row">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">
                                <div class="form-check">
                                    <input type="checkbox" name="first_payment" id="firstPayment" class="form-check-input" value="1" {{ old('first_payment') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="firstPayment">
                                        <i class="fas fa-money-bill-wave me-2"></i>Student is making first payment now
                                    </label>
                                </div>
                            </h6>

                            <div id="firstPaymentDetails" style="{{ old('first_payment') ? 'display: block;' : 'display: none;' }}">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">First Payment Amount</label>
                                            <input type="number" name="first_payment_amount" id="firstPaymentAmount" 
                                                   class="form-control" step="0.01" min="0" 
                                                   placeholder="Enter amount" value="{{ old('first_payment_amount') }}">
                                            <small class="text-muted">Leave blank for full amount in one-time payment</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select">
                                                <option value="">Select method</option>
                                                <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                                                <option value="bank" {{ old('payment_method') === 'bank' ? 'selected' : '' }}>Bank Transfer</option>
                                                <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Credit/Debit Card</option>
                                                <option value="online" {{ old('payment_method') === 'online' ? 'selected' : '' }}>Online Payment</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Transaction Reference</label>
                                            <input type="text" name="transaction_reference" class="form-control" 
                                                   placeholder="Receipt/Transaction ID" value="{{ old('transaction_reference') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" 
                                              placeholder="Any additional notes about this payment">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div id="installmentPreview" class="mt-4" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-eye me-2"></i>Installment Preview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Installment</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="previewTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('fees.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success" id="submitBtn">
                    <i class="fas fa-check me-1"></i>Create Installments
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const enrollmentSelect = document.getElementById('enrollmentSelect');
    const installmentType = document.getElementById('installmentType');
    const firstPayment = document.getElementById('firstPayment');
    const firstPaymentDetails = document.getElementById('firstPaymentDetails');
    const firstPaymentAmount = document.getElementById('firstPaymentAmount');
    const installmentCountDiv = document.getElementById('installmentCountDiv');
    const numberOfInstallments = document.getElementById('numberOfInstallments');
    const enrollmentDetails = document.getElementById('enrollmentDetails');

    // Show enrollment details
    enrollmentSelect.addEventListener('change', function() {
        if (this.value) {
            const option = this.selectedOptions[0];
            document.getElementById('studentName').textContent = option.dataset.student;
            document.getElementById('courseName').textContent = option.dataset.course;
            document.getElementById('totalFees').textContent = new Intl.NumberFormat('en-PK', {
                style: 'currency',
                currency: 'PKR'
            }).format(option.dataset.fees);
            enrollmentDetails.style.display = 'block';
            
            // Set max for first payment amount
            firstPaymentAmount.max = option.dataset.fees;
            updatePreview();
        } else {
            enrollmentDetails.style.display = 'none';
        }
    });

    // Show/hide installment count based on type
    installmentType.addEventListener('change', function() {
        if (this.value === 'full') {
            installmentCountDiv.style.display = 'none';
        } else if (this.value === 'monthly') {
            installmentCountDiv.style.display = 'block';
            numberOfInstallments.value = '3';
        } else if (this.value === 'quarterly') {
            installmentCountDiv.style.display = 'block';
            numberOfInstallments.value = '2';
        } else if (this.value === 'custom') {
            installmentCountDiv.style.display = 'block';
            numberOfInstallments.value = '3';
        }
        updatePreview();
    });

    // Show/hide first payment details
    firstPayment.addEventListener('change', function() {
        firstPaymentDetails.style.display = this.checked ? 'block' : 'none';
        updatePreview();
    });

    // Update preview when inputs change
    [numberOfInstallments, firstPaymentAmount].forEach(element => {
        element.addEventListener('input', updatePreview);
    });

    document.querySelector('input[name="start_date"]').addEventListener('change', updatePreview);

    function updatePreview() {
        const enrollmentOption = enrollmentSelect.selectedOptions[0];
        const type = installmentType.value;
        const startDate = document.querySelector('input[name="start_date"]').value;
        
        if (!enrollmentOption || !type || !startDate) {
            document.getElementById('installmentPreview').style.display = 'none';
            return;
        }

        const totalFees = parseFloat(enrollmentOption.dataset.fees);
        const isFirstPayment = firstPayment.checked;
        const firstAmount = parseFloat(firstPaymentAmount.value) || 0;
        const installmentCount = type === 'full' ? 1 : parseInt(numberOfInstallments.value);

        let preview = [];

        if (type === 'full') {
            preview.push({
                number: 1,
                amount: totalFees,
                date: startDate,
                status: isFirstPayment ? 'Paid' : 'Pending'
            });
        } else {
            if (isFirstPayment && firstAmount > 0) {
                preview.push({
                    number: 1,
                    amount: firstAmount,
                    date: startDate,
                    status: 'Paid'
                });

                const remainingAmount = totalFees - firstAmount;
                const remainingInstallments = installmentCount - 1;
                const installmentAmount = remainingAmount / remainingInstallments;

                for (let i = 1; i <= remainingInstallments; i++) {
                    const date = new Date(startDate);
                    if (type === 'monthly') {
                        date.setMonth(date.getMonth() + i);
                    } else if (type === 'quarterly') {
                        date.setMonth(date.getMonth() + (i * 3));
                    } else {
                        date.setDate(date.getDate() + (i * 30));
                    }

                    preview.push({
                        number: i + 1,
                        amount: installmentAmount,
                        date: date.toISOString().split('T')[0],
                        status: 'Pending'
                    });
                }
            } else {
                const installmentAmount = totalFees / installmentCount;

                for (let i = 0; i < installmentCount; i++) {
                    const date = new Date(startDate);
                    if (type === 'monthly') {
                        date.setMonth(date.getMonth() + i);
                    } else if (type === 'quarterly') {
                        date.setMonth(date.getMonth() + (i * 3));
                    } else {
                        date.setDate(date.getDate() + (i * 30));
                    }

                    preview.push({
                        number: i + 1,
                        amount: installmentAmount,
                        date: date.toISOString().split('T')[0],
                        status: 'Pending'
                    });
                }
            }
        }

        const previewBody = document.getElementById('previewTableBody');
        previewBody.innerHTML = '';

        preview.forEach(installment => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>Installment ${installment.number}</td>
                <td>${new Intl.NumberFormat('en-PK', {
                    style: 'currency',
                    currency: 'PKR'
                }).format(installment.amount)}</td>
                <td>${new Date(installment.date).toLocaleDateString()}</td>
                <td><span class="badge bg-${installment.status === 'Paid' ? 'success' : 'warning'}">${installment.status}</span></td>
            `;
            previewBody.appendChild(row);
        });

        document.getElementById('installmentPreview').style.display = 'block';
    }
});
</script>
@endsection