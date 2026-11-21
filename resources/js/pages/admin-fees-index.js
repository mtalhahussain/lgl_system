import { bindAction } from '../page-helpers';

export function init(config) {
    function payInstallmentModal(installmentId, studentName, amount) {
        document.getElementById('studentName').textContent = studentName;
        document.getElementById('installmentAmount').textContent = amount;
        document.getElementById('paymentForm').action = `/fees/${installmentId}/pay`;

        const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
        modal.show();
    }
    bindAction('pay-installment', button => payInstallmentModal(button.dataset.installmentId, button.dataset.studentName, button.dataset.amount));
}
