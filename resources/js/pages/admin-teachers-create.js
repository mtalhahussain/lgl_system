import { onReady } from '../page-helpers';

export function init(config) {
    onReady( function() {
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
}
