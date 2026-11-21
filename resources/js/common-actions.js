export function initCommonActions() {
    document.querySelectorAll('[data-print]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            window.print();
        });
    });
    document.querySelectorAll('[data-confirm-click]').forEach(button => {
        button.addEventListener('click', event => {
            if (!window.confirm(button.dataset.confirmClick)) event.preventDefault();
        });
    });
    document.querySelectorAll('form[data-notifications-read-all]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = event.submitter;
            if (button) button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                if (!response.ok) throw new Error('Could not mark notifications as read.');
                location.reload();
            } catch {
                if (button) button.disabled = false;
                window.alert('Could not mark notifications as read. Please try again.');
            }
        });
    });
}
