// Progressive enhancement for list pages, forms, confirm prompts and searchable selects.
// Every feature works without JS: forms still submit, selects stay native and confirmations fall back to none.

const FOCUS_KEY = 'lgl:list-focus';

const storage = {
    get(key) { try { return sessionStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { sessionStorage.setItem(key, value); } catch { /* storage unavailable */ } },
    remove(key) { try { sessionStorage.removeItem(key); } catch { /* storage unavailable */ } },
};

function debounce(fn, wait) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

export function initListFilters(root = document) {
    root.querySelectorAll('form[data-page-size]').forEach((form) => {
        form.classList.add('is-enhanced');
        form.querySelector('select[name="per_page"]').addEventListener('change', () => form.requestSubmit());
    });
    root.querySelectorAll('form[data-list-filter]').forEach((form) => {
        form.classList.add('is-enhanced');

        // Drop empty fields so shared URLs stay short; re-enabled if the page is restored from cache.
        form.addEventListener('submit', () => {
            Array.from(form.elements).filter(field => field.matches('input, select')).forEach((field) => {
                if (field.value === '') field.disabled = true;
            });
        });
        window.addEventListener('pageshow', () => {
            Array.from(form.elements).filter(field => field.disabled).forEach((field) => { field.disabled = false; });
        });

        form.querySelectorAll('select, input[type=date]').forEach((field) => {
            field.addEventListener('change', () => { if (!field.dataset.rangeSource) form.requestSubmit(); });
        });
        form.addEventListener('date-range:change', () => form.requestSubmit());

        const search = form.elements.namedItem('search');
        if (search) {
            let last = search.value;
            const run = debounce(() => {
                const value = search.value.trim();
                if (value === last || (value.length > 0 && value.length < 2)) return;
                last = value;
                storage.set(FOCUS_KEY, search.name);
                form.requestSubmit();
            }, 400);
            search.addEventListener('input', run);

            // Restore focus to the search box after its own reload so typing can continue.
            if (storage.get(FOCUS_KEY) === search.name) {
                storage.remove(FOCUS_KEY);
                search.focus();
                search.setSelectionRange(search.value.length, search.value.length);
            }
        }
    });
}

export function initConfirmForms(root = document) {
    const dialog = document.getElementById('confirmDialog');
    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        event.preventDefault();
        const proceed = () => {
            form.dataset.confirmed = '1';
            form.requestSubmit(event.submitter ?? undefined);
        };
        if (!dialog || typeof dialog.showModal !== 'function') {
            if (window.confirm(form.dataset.confirm)) proceed();
            return;
        }
        dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        dialog.returnValue = '';
        dialog.addEventListener('close', () => {
            if (dialog.returnValue === 'confirm') proceed();
        }, { once: true });
        dialog.showModal();
    });
}

// A focused number input otherwise changes its value when the page is scrolled over it.
export function initNumberWheelGuard(root = document) {
    root.addEventListener('wheel', (event) => {
        const field = event.target;
        if (field instanceof HTMLInputElement && field.type === 'number' && document.activeElement === field) {
            field.blur();
        }
    }, { passive: true });
}

// Adds a show/hide eye button to password fields (the login page has its own toggle).
export function initPasswordToggles(root = document) {
    root.querySelectorAll('input[type="password"]:not([data-no-reveal])').forEach((field) => {
        if (field.closest('.password-wrap')) {
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'password-wrap';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('aria-pressed', 'false');
        button.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
        button.addEventListener('click', () => {
            const visible = field.type === 'password';
            field.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', String(visible));
            button.querySelector('i').className = visible ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
        field.before(wrap);
        wrap.append(field, button);
    });
}

// Brief status message; one shared live region so screen readers announce it.
export function showToast(message, tone = 'ok') {
    let toast = document.getElementById('appToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'appToast';
        toast.className = 'app-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        document.body.append(toast);
    }
    toast.textContent = message;
    toast.dataset.tone = tone;
    toast.classList.add('is-visible');
    clearTimeout(toast.hideTimer);
    toast.hideTimer = setTimeout(() => toast.classList.remove('is-visible'), 3500);
}

// Forms marked data-autosave submit in the background whenever one of their controls changes
// (controls may sit elsewhere in the row via the form="" attribute). A rejected change is
// rolled back to the last saved value.
export function initAutoSave(root = document) {
    root.querySelectorAll('form[data-autosave]').forEach((form) => {
        const controls = [...form.elements].filter((el) => el.matches('select, input[type="checkbox"]'));
        const remember = (el) => { el.dataset.saved = el.type === 'checkbox' ? String(el.checked) : el.value; };
        const restore = (el) => {
            if (el.type === 'checkbox') el.checked = el.dataset.saved === 'true';
            else el.value = el.dataset.saved;
        };
        controls.forEach((el) => {
            remember(el);
            el.addEventListener('change', async () => {
                const body = new FormData(form); // before disabling: disabled controls are not submitted
                controls.forEach((control) => { control.disabled = true; });
                let data = {};
                let ok = false;
                try {
                    const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    data = await response.json().catch(() => ({}));
                    ok = response.ok;
                } catch (error) {
                    data = { message: 'Network error. The change was not saved.' };
                }
                controls.forEach((control) => { control.disabled = false; });
                if (!ok) {
                    controls.forEach(restore);
                    const first = data.errors ? Object.values(data.errors)[0] : null;
                    showToast((Array.isArray(first) ? first[0] : first) || data.message || 'The change was not saved.', 'bad');
                    return;
                }
                if (data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }
                controls.forEach(remember);
                form.dispatchEvent(new CustomEvent('autosaved', { detail: data }));
                showToast(data.message || 'Saved.');
            });
        });
    });
    // Keep the Active switch's pill in step with the saved state.
    root.addEventListener('autosaved', (event) => {
        const toggle = document.querySelector(`input[type="checkbox"][form="${event.target.id}"]`);
        const pill = toggle?.closest('.form-check')?.querySelector('.status-pill');
        if (pill && 'is_active' in event.detail) {
            pill.textContent = event.detail.is_active ? 'active' : 'inactive';
            pill.classList.toggle('tone-ok', !!event.detail.is_active);
            pill.classList.toggle('tone-bad', !event.detail.is_active);
        }
    });
}

// Live "what will happen" line for the enrollment discount form. Amounts are integer minor units and the
// discount uses the same rounding as PaymentService::changeDiscount (half up on basis points).
export function initDiscountPreview(root = document) {
    root.querySelectorAll('form[data-discount-preview]').forEach((form) => {
        const input = form.querySelector('[name="discount_percentage"]');
        const output = form.querySelector('[data-preview]');
        const refundSection = form.querySelector('[data-refund-section]');
        const refundMethod = form.querySelector('[name="refund_method"]');
        if (!input || !output) return;
        const data = form.dataset;
        const gross = Number(data.gross);
        const oldDiscount = Number(data.discount);
        const kept = Number(data.kept);
        const decimals = Number(data.currencyDecimals ?? 2);
        const money = (minor) => {
            const amount = (minor / 100).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            return data.currencyPosition === 'after' ? `${amount} ${data.currencySymbol}` : `${data.currencySymbol} ${amount}`;
        };
        const refundNeeded = (on) => {
            refundSection?.classList.toggle('is-required', on);
            if (refundMethod) refundMethod.required = on;
        };
        const update = () => {
            const match = /^\s*(\d{1,3})(?:\.(\d{1,2}))?\s*$/.exec(input.value);
            const basisPoints = match ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0')) : NaN;
            if (!match || basisPoints > 10000) {
                output.hidden = true;
                refundNeeded(false);
                return;
            }
            const newDiscount = Math.floor((gross * basisPoints + 5000) / 10000);
            const oldNet = gross - oldDiscount;
            const newNet = gross - newDiscount;
            const oldDue = Math.max(0, oldNet - kept);
            const refund = Math.max(0, kept - newNet);
            const newDue = Math.max(0, newNet - kept);
            const from = `${data.currentPercent}% → ${input.value.trim()}%`;
            let tone = 'info';
            let text;
            if (newDiscount === oldDiscount) {
                text = `No change: the discount stays ${data.currentPercent}%.`;
            } else if (refund > 0) {
                tone = 'warn';
                text = `Discount ${from}: net fee ${money(oldNet)} → ${money(newNet)}. The student has paid ${money(kept)}, so a refund of ${money(refund)} will be recorded — fill in the refund section below.`;
            } else if (newDue > oldDue) {
                tone = 'warn';
                text = `Discount ${from}: net fee ${money(oldNet)} → ${money(newNet)}. The student will owe ${money(newDue)} (now ${money(oldDue)}).`;
            } else {
                tone = 'ok';
                text = `Discount ${from}: net fee ${money(oldNet)} → ${money(newNet)}. Outstanding ${money(oldDue)} → ${money(newDue)}.`;
            }
            output.textContent = text;
            output.dataset.tone = tone;
            output.hidden = false;
            refundNeeded(refund > 0);
        };
        input.addEventListener('input', update);
        update();
    });
}

// Desktop hamburger:shrinks the sidebar to an icon rail. The <head> script applies the saved
// state before paint; this keeps the button, tooltips and storage in sync.
export function initSidebarCollapse() {
    const button = document.getElementById('sidebarCollapse');
    if (!button) {
        return;
    }
    const root = document.documentElement;
    const links = document.querySelectorAll('#appSidebar .nav-link');
    const sync = () => {
        const collapsed = root.classList.contains('sidebar-collapsed');
        const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', label);
        button.title = label;
        links.forEach((link) => {
            if (collapsed) {
                link.title = link.textContent.trim();
            } else {
                link.removeAttribute('title');
            }
        });
    };
    button.addEventListener('click', () => {
        const collapsed = root.classList.toggle('sidebar-collapsed');
        try {
            localStorage.setItem('lgl:sidebar', collapsed ? 'collapsed' : 'expanded');
        } catch (error) {
            // Storage may be blocked; the toggle still works for this page.
        }
        sync();
    });
    sync();
}

export function initLists() {
    initContactInputs();
    initListFilters();
    initConfirmForms();
    initNumberWheelGuard();
    initPasswordToggles();
    initSidebarCollapse();
    initAutoSave();
    initDiscountPreview();
}

function initContactInputs() {
    const selector = 'input[name="phone"], input[name="emergency_contact"], input[name="institute_phone"]';
    document.querySelectorAll(selector).forEach(input => {
        input.type = 'tel';
        input.inputMode = 'tel';
        input.pattern = '\\+?[0-9]+';
        input.maxLength = input.name === 'institute_phone' ? 30 : 20;
        input.title = 'Digits only, with an optional leading +';
        input.addEventListener('input', event => {
            if (event.isComposing) return;
            const position = input.selectionStart;
            const clean = value => value.replace(/[^0-9+]/g, '').replace(/(?!^)\+/g, '');
            const value = clean(input.value);
            if (value !== input.value) {
                const cursor = clean(input.value.slice(0, position)).length;
                input.value = value;
                input.setSelectionRange(cursor, cursor);
            }
        });
    });
}
