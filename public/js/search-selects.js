// Keep native values, validation and existing native change listeners authoritative.
(() => {
    const $ = window.jQuery;
    if (!$?.fn.select2) return;
    const ready = new WeakSet();
    const eligible = select => !select.hasAttribute('data-native')
        && !select.closest('.flatpickr-calendar')
        && (select.hasAttribute('data-select2') || select.hasAttribute('data-combobox')
            || select.classList.contains('teaching-select')
            || /_ids?(\[\])?$/.test(select.name)
            || select.hasAttribute('data-package-batch')
            || select.options.length >= 8);
    const renderOption = data => {
        const role = data.element?.dataset.personRole;
        if (role) {
            const row = document.createElement('span');
            row.append(document.createTextNode(data.element.dataset.personName));
            const suffix = document.createElement('small');
            suffix.className = 'select2-person-role';
            suffix.textContent = ` (${role})`;
            row.append(suffix);
            return $(row);
        }
        const meta = data.element?.dataset.meta;
        if (!meta) return data.text;
        const row = document.createElement('span');
        const title = document.createElement('span');
        title.textContent = data.text;
        const detail = document.createElement('small');
        detail.textContent = meta;
        row.append(title, detail);
        return $(row);
    };
    const matcher = (params, data) => {
        const term = (params.term || '').trim().toLocaleLowerCase();
        if (!term) return data;
        if (data.children) {
            const children = data.children.map(child => matcher(params, child)).filter(Boolean);
            return children.length ? {...data, children} : null;
        }
        return `${data.text} ${data.element?.dataset.meta || ''}`.toLocaleLowerCase().includes(term) ? data : null;
    };
    const init = () => {
        document.querySelectorAll('select').forEach(select => {
            if (ready.has(select) || !eligible(select)) return;
            ready.add(select);
            const nativeStyle = getComputedStyle(select);
            const fieldStyle = Object.fromEntries(['font-size', 'font-family', 'line-height',
                'padding-top', 'padding-bottom', 'padding-left', 'border-radius', 'border-top-width',
                'border-bottom-width', 'border-color'].map(name => [name, nativeStyle.getPropertyValue(name)]));
            // Browsers report native select line-height as normal even beside 1.5-line inputs.
            if (fieldStyle['line-height'] === 'normal') {
                fieldStyle['line-height'] = `${parseFloat(fieldStyle['font-size']) * 1.5}px`;
            }
            const control = $(select);
            const blank = [...select.options].find(option => option.value === '');
            const options = {
                theme: 'bootstrap-5', width: '100%', tags: false,
                minimumResultsForSearch: 0, matcher, templateResult: renderOption,
                templateSelection: data => data.element?.dataset.personRole ? renderOption(data) : data.text,
                dropdownParent: $(select.closest('.modal, dialog') || document.body),
            };
            // An explicit placeholder can be cleared only when a blank option exists.
            if (blank && (select.dataset.placeholder || select.dataset.combobox)) {
                options.placeholder = select.dataset.placeholder || blank.textContent;
                options.allowClear = !select.required;
            }
            control.select2(options);
            const container = control.next('.select2-container')[0];
            for (const [name, value] of Object.entries(fieldStyle)) {
                container.style.setProperty(`--field-${name}`, value);
            }
            for (const name of select.classList) {
                if (/^m[trblxy]?-[0-5]$/.test(name)) container.classList.add(name);
            }
            const selection = container.querySelector('.select2-selection');
            const sync = () => {
                control.trigger('change.select2');
                selection.setAttribute('aria-invalid', String(select.classList.contains('is-invalid')));
                selection.setAttribute('aria-required', String(select.required));
                const description = select.getAttribute('aria-describedby');
                if (description) selection.setAttribute('aria-describedby', description);
            };
            // Select2 emits jQuery events; application forms listen with addEventListener.
            control.on('change.lgl', event => {
                if (!event.originalEvent && !event.namespace) {
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
            select.addEventListener('change', () => {
                selection.setAttribute('aria-invalid', String(!select.validity.valid));
            });
            select.addEventListener('invalid', event => {
                event.preventDefault();
                selection.setAttribute('aria-invalid', 'true');
                selection.focus();
            });
            select.form?.addEventListener('reset', () => setTimeout(sync));
            new MutationObserver(sync).observe(select, {
                childList: true, subtree: true, characterData: true, attributes: true,
                attributeFilter: ['disabled', 'required', 'class', 'selected', 'label'],
            });
            sync();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1
            && (node.matches('select, option') || node.querySelector('select'))))) init();
    }).observe(document.documentElement, {childList: true, subtree: true});
})();
