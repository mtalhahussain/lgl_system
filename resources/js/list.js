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
    root.querySelectorAll('form[data-list-filter]').forEach((form) => {
        form.classList.add('is-enhanced');

        // Drop empty fields so shared URLs stay short; re-enabled if the page is restored from cache.
        form.addEventListener('submit', () => {
            form.querySelectorAll('input, select').forEach((field) => {
                if (field.value === '') field.disabled = true;
            });
        });
        window.addEventListener('pageshow', () => {
            form.querySelectorAll(':disabled').forEach((field) => { field.disabled = false; });
        });

        form.querySelectorAll('select, input[type=date]').forEach((field) => {
            field.addEventListener('change', () => form.requestSubmit());
        });

        const search = form.querySelector('input[type=search]');
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

const COMBOBOX_SELECTOR ='select[data-combobox], select.form-select:not([data-native]):not([multiple])';

// Turns selects into a type-to-search list (Select2-style, no jQuery). The select stays in the
// form as the submitted value; options may carry data-meta for a secondary line.
export function initComboboxes(root = document) {
    root.querySelectorAll(COMBOBOX_SELECTOR).forEach((select, index) => {
        if (select.dataset.comboboxReady) return;
        select.dataset.comboboxReady = '1';
        const options = [...select.options];
        const baseId = select.id || `combobox${index}`;
        const listId = `${baseId}_list`;
        const wrapper = document.createElement('div');
        wrapper.className = 'combobox';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = select.className;
        input.id = `${baseId}_search`;
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.disabled = select.disabled;
        input.placeholder = select.dataset.combobox || 'Type to search…';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', listId);
        if (select.required) input.setAttribute('aria-required', 'true');
        const list = document.createElement('ul');
        list.id = listId;
        list.className = 'combobox-list';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        const empty = document.createElement('li');
        empty.className = 'combobox-empty';
        empty.textContent = 'No matches';
        empty.hidden = true;

        const items = options.map((option, i) => {
            const item = document.createElement('li');
            item.id = `${listId}_${i}`;
            item.setAttribute('role', 'option');
            item.dataset.index = String(i);
            const title = document.createElement('strong');
            title.textContent = option.textContent.trim();
            item.append(title);
            if (option.dataset.meta) {
                const meta = document.createElement('small');
                meta.textContent = option.dataset.meta;
                item.append(meta);
            }
            if (option.value === '') item.classList.add('is-placeholder');
            if (option.disabled) item.setAttribute('aria-disabled', 'true');
            item.dataset.search = `${option.textContent} ${option.dataset.meta ?? ''}`.toLowerCase();
            list.append(item);
            return item;
        });
        list.append(empty);

        const label = () => {
            const chosen = select.selectedOptions[0];
            return chosen ? chosen.textContent.trim() : '';
        };
        let active = -1;
        const visible = () => items.filter((item) => !item.hidden && item.getAttribute('aria-disabled') !== 'true');
        const setActive = (next) => {
            const shown = visible();
            items.forEach((item) => item.removeAttribute('aria-selected'));
            active = shown.length ? (next + shown.length) % shown.length : -1;
            if (active >= 0) {
                shown[active].setAttribute('aria-selected', 'true');
                shown[active].scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', shown[active].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        };
        // Fixed positioning keeps the list visible inside panels and tables that clip overflow.
        const place = () => {
            const box = input.getBoundingClientRect();
            const below = window.innerHeight - box.bottom;
            const height = Math.min(260, list.scrollHeight || 260);
            list.style.left = `${box.left}px`;
            list.style.width = `${box.width}px`;
            if (below < height + 8 && box.top > below) {
                list.style.top = '';
                list.style.bottom = `${window.innerHeight - box.top + 4}px`;
            } else {
                list.style.bottom = '';
                list.style.top = `${box.bottom + 4}px`;
            }
        };
        const onViewportChange = () => { if (!list.hidden) place(); };
        const open = () => {
            if (!list.hidden) return;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            place();
            window.addEventListener('scroll', onViewportChange, true);
            window.addEventListener('resize', onViewportChange);
        };
        const close = () => {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            setActive(-1);
            window.removeEventListener('scroll', onViewportChange, true);
            window.removeEventListener('resize', onViewportChange);
        };
        const showAll = () => { items.forEach((item) => { item.hidden = false; }); empty.hidden = true; };
        const choose = (item) => {
            const option = options[Number(item.dataset.index)];
            input.value = option.textContent.trim();
            close();
            if (select.value !== option.value) {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            input.classList.remove('is-invalid');
        };
        const filter = () => {
            const term = input.value.trim().toLowerCase();
            items.forEach((item) => { item.hidden = term !== '' && !item.dataset.search.includes(term); });
            empty.hidden = visible().length > 0;
            open();
            place();
            setActive(0);
        };

        input.addEventListener('input', filter);
        input.addEventListener('focus', () => {
            input.select();
            showAll();
            open();
            setActive(Math.max(0, visible().findIndex((item) => options[Number(item.dataset.index)].value === select.value)));
        });
        input.addEventListener('blur', () => { close(); input.value = label(); });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); list.hidden ? (showAll(), open(), setActive(0)) : setActive(active + 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(active - 1); }
            else if (event.key === 'Enter' && !list.hidden) { event.preventDefault(); if (active >= 0) choose(visible()[active]); }
            else if (event.key === 'Escape' && !list.hidden) { event.preventDefault(); close(); input.value = label(); input.select(); }
            else if (event.key === 'Tab' && !list.hidden && active >= 0 && input.value !== label()) { choose(visible()[active]); }
        });
        list.addEventListener('mousedown', (event) => event.preventDefault());
        list.addEventListener('click', (event) => {
            const item = event.target.closest('[role=option]');
            if (item && item.getAttribute('aria-disabled') !== 'true') choose(item);
        });
        // Keep the visible text in sync when code or a form reset changes the select.
        select.addEventListener('change', () => { if (document.activeElement !== input) input.value = label(); });
        select.form?.addEventListener('reset', () => setTimeout(() => { input.value = label(); }));
        // A hidden required select cannot show the browser message itself; point it at the input.
        select.addEventListener('invalid', () => { input.classList.add('is-invalid'); input.focus(); });

        const labelElement = select.id ? document.querySelector(`label[for="${select.id}"]`) : null;
        if (labelElement) labelElement.htmlFor = input.id;
        select.classList.add('visually-hidden');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        input.value = label();
        select.after(wrapper);
        wrapper.append(input, list);
    });
}

export function initLists() {
    initListFilters();
    initConfirmForms();
    initNumberWheelGuard();
    initComboboxes();
    initPasswordToggles();
    initSidebarCollapse();
    initAutoSave();
    initDiscountPreview();
}
