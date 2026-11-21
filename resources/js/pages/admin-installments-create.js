import { onReady } from '../page-helpers';

export function init(config) {
    onReady( function() {
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

        // When enrollment changes, update preview and also trigger change event on payment type to recalculate
        enrollmentSelect.addEventListener('change', function() {
            const option = this.selectedOptions[0];
            // If payment type is already selected, trigger its change event to recalculate preview
            if (installmentType.value) {
                installmentType.dispatchEvent(new Event('change'));
            }
            updatePreview();
        });

        // When number of installments changes, update preview
        numberOfInstallments.addEventListener('change', updatePreview);

        // When payment type changes, update preview
        installmentType.addEventListener('change', updatePreview);

        // When start date changes, update preview
        document.querySelector('input[name="start_date"]').addEventListener('change', updatePreview);

        // When first payment amount changes, update preview
        firstPaymentAmount.addEventListener('input', updatePreview);

        // When first payment checkbox changes, update preview
        firstPayment.addEventListener('change', updatePreview);

        // On page load, if both enrollment and payment type are selected, trigger preview
        if (enrollmentSelect.value && installmentType.value) {
            installmentType.dispatchEvent(new Event('change'));
            updatePreview();
        }

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
}
