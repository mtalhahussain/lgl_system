import { bindAction } from '../page-helpers';

export function init(config) {
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
    bindAction('enroll-fingerprint', button => enrollFingerprintModal(button.dataset.studentId, button.dataset.studentName));
    bindAction('remove-fingerprint', button => removeFingerprintModal(button.dataset.studentId, button.dataset.studentName));
}
