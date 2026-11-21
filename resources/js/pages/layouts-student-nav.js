import { bindAction } from '../page-helpers';

export function init(config) {
    function showHelpModal() {
        const modal = new bootstrap.Modal(document.getElementById('helpModal'));
        modal.show();
    }
    bindAction('show-help', () => showHelpModal());
}
