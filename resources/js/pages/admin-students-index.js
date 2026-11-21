import { onReady, bindAction } from '../page-helpers';

export function init(config) {
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

    onReady( function() {
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
    bindAction('enroll-fingerprint', button => enrollFingerprintModal(button.dataset.studentId, button.dataset.studentName));
    bindAction('remove-fingerprint', button => removeFingerprintModal(button.dataset.studentId, button.dataset.studentName));
}
