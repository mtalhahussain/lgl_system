export function initCopyLinks() {
    document.querySelectorAll('[data-inquiry-link-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const section = form.closest('section');
            const button = form.querySelector('button');
            const status = section.querySelector('[data-copy-status]');
            if (button.disabled) return;
            button.disabled = true;
            button.textContent = 'Generating…';
            status.textContent = '';
            try {
                const url = new URL(form.action, window.location.href);
                url.search = new URLSearchParams(new FormData(form)).toString();
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (!response.ok) throw new Error('Link generation failed');
                const data = await response.json();
                if (typeof data.inquiryUrl !== 'string') throw new Error('Missing link');
                section.querySelector('#inquiry_link').value = data.inquiryUrl;
                section.querySelector('[data-inquiry-open]').href = data.inquiryUrl;
                section.querySelector('[data-copy-target]').textContent = 'Copy link';
                status.textContent = 'Link ready. You can copy it now.';
            } catch {
                status.textContent = 'Could not generate the link. Please try again.';
            } finally {
                button.disabled = false;
                button.textContent = 'Generate link';
            }
        });
    });

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
