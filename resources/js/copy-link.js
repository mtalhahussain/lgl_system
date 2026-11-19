export function initCopyLinks() {
    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyTarget);
            const status = button.closest('section')?.querySelector('[data-copy-status]');
            if (!input) return;
            let copied = false;
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(input.value);
                    copied = true;
                }
            } catch { /* Try the selection-based fallback below. */ }
            if (!copied) {
                input.focus();
                input.select();
                try { copied = document.execCommand('copy'); } catch { /* Keep link selected. */ }
            }
            if (status) status.textContent = copied ? '' : 'Select the link and copy it manually.';
            button.textContent = copied ? 'Copied!' : 'Copy link';
            setTimeout(() => { button.textContent = 'Copy link'; }, 2000);
        });
    });
}
