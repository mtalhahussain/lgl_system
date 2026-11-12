import '../vendor/flatpickr/flatpickr.js';

const selector = 'input[type="date"], input[type="time"], input[type="datetime-local"]';
const managed = new Set();
const messages = new WeakMap();
const defaultValues = new WeakMap();
const ranges = new Set();
const rangeInputs = new WeakMap();
let rangeSequence = 0;

function friendlyControls(input, picker) {
    const calendar = picker.calendarContainer;
    calendar.classList.add('lgl-picker');
    const field = picker.altInput || input;
    field.classList.add('lgl-picker-field');
    field.classList.toggle('lgl-time-field', picker.config.noCalendar);
    const makeSelect = (label, values) => {
        const wrapper = document.createElement('label');
        wrapper.className = 'lgl-time-choice';
        const title = document.createElement('span');
        title.textContent = label;
        const select = document.createElement('select');
        select.className = 'lgl-picker-select';
        select.setAttribute('aria-label', label);
        select.addEventListener('keydown', event => {
            if (event.key === 'Escape') picker.close();
            event.stopPropagation();
        });
        for (const [value, text] of values) select.add(new Option(text, String(value)));
        wrapper.append(title, select);
        return { wrapper, select };
    };
    let year;
    if (!picker.config.noCalendar) {
        const first = picker.config.minDate?.getFullYear() ?? 1900;
        const last = picker.config.maxDate?.getFullYear() ?? Math.max(new Date().getFullYear() + 20, picker.currentYear);
        year = makeSelect('Year', Array.from({ length: Math.max(1, last - first + 1) }, (_, index) => [first + index, String(first + index)])).select;
        year.classList.add('lgl-picker-year');
        picker.currentYearElement.parentElement.after(year);
        year.addEventListener('change', () => picker.changeYear(Number(year.value)));
    }
    let hour, minute, period;
    if (picker.config.enableTime) {
        const group = document.createElement('div');
        group.className = 'lgl-time-controls';
        const hours = makeSelect('Hour', Array.from({ length: 12 }, (_, i) => [i + 1, String(i + 1).padStart(2, '0')]));
        const minutes = makeSelect('Minute', Array.from({ length: 60 }, (_, i) => [i, String(i).padStart(2, '0')]));
        const periods = makeSelect('AM / PM', [['AM', 'AM'], ['PM', 'PM']]);
        hour = hours.select; minute = minutes.select; period = periods.select;
        group.append(hours.wrapper, minutes.wrapper, periods.wrapper);
        picker.timeContainer.after(group);
        const updateTime = () => {
            const selected = picker.selectedDates[0] || new Date();
            const date = new Date(selected.getTime());
            date.setHours(Number(hour.value) % 12 + (period.value === 'PM' ? 12 : 0), Number(minute.value), 0, 0);
            picker.setDate(date, true);
        };
        [hour, minute, period].forEach(select => select.addEventListener('change', updateTime));
    }
    const actions = document.createElement('div');
    actions.className = 'lgl-picker-actions';
    const button = (text, callback, primary = false) => {
        const control = document.createElement('button');
        control.type = 'button';
        control.className = primary ? 'lgl-picker-action is-primary' : 'lgl-picker-action';
        control.textContent = text;
        control.addEventListener('click', callback);
        actions.append(control);
        return control;
    };
    const current = button(picker.config.noCalendar ? 'Now' : 'Today', () => {
        picker.setDate(picker.config.mode === 'range' ? [new Date(), new Date()] : new Date(), true);
        if (!picker.config.enableTime) picker.close();
    });
    button('Clear', () => { picker.clear(); picker.close(); });
    button('Done', () => picker.close(), true);
    calendar.append(actions);
    const sync = () => {
        if (year) {
            if (![...year.options].some(option => Number(option.value) === picker.currentYear)) {
                year.add(new Option(String(picker.currentYear), String(picker.currentYear)));
            }
            year.value = String(picker.currentYear);
        }
        const date = picker.selectedDates[0] || new Date();
        if (hour) {
            hour.value = String(date.getHours() % 12 || 12);
            minute.value = String(date.getMinutes());
            period.value = date.getHours() >= 12 ? 'PM' : 'AM';
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        current.disabled = !picker.config.noCalendar && !picker.isEnabled(today, true);
    };
    ['onChange', 'onOpen', 'onMonthChange', 'onYearChange', 'onValueUpdate'].forEach(hook => picker.config[hook].push(sync));
    sync();
}

function validate(input) {
    const control = input._flatpickr?.altInput || input;
    const previous = messages.get(input);
    if (previous && control.validationMessage === previous) input.setCustomValidity('');
    messages.delete(input);
    // Keep validation supplied by individual forms, including batch date ordering.
    if (control.validity.customError || !input.value) return;
    const type = input.dataset.pickerType;
    const format = type === 'time' ? /^\d{2}:\d{2}$/
        : type === 'date' ? /^\d{4}-\d{2}-\d{2}$/ : /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;
    const parsed = window.flatpickr.parseDate(input.value, input._flatpickr.config.dateFormat);
    const valid = format.test(input.value) && parsed
        && input._flatpickr.formatDate(parsed, input._flatpickr.config.dateFormat) === input.value;
    const message = !valid ? 'Enter a valid date or time in the shown format.'
        : input.min && input.value < input.min ? `Choose a value on or after ${input.min}.`
            : input.max && input.value > input.max ? `Choose a value on or before ${input.max}.` : '';
    if (message) {
        input.setCustomValidity(message);
        messages.set(input, message);
    }
}

function enhanceRanges() {
    for (const form of document.forms) {
        const pairs = [['from', 'to'], ...(form.method === 'get' ? [['start_date', 'end_date']] : [])];
        for (const [fromName, toName] of pairs) {
            const from = form.querySelector(`input[type="date"][name="${fromName}"]`);
            const to = form.querySelector(`input[type="date"][name="${toName}"]`);
            if (!from || !to || rangeInputs.has(from) || from._flatpickr || to._flatpickr
                || [from, to].some(input => input.readOnly || input.hasAttribute('data-native'))) continue;
            const labels = [...(from.labels || [])];
            const endLabels = [...(to.labels || [])];
            const display = document.createElement('input');
            display.type = 'text';
            display.className = `${from.className || 'form-control'} lgl-date-range`;
            display.id = `${from.id || `range_${++rangeSequence}`}_range`;
            display.placeholder = 'DD-MM-YYYY to DD-MM-YYYY';
            display.setAttribute('aria-label', 'Date range');
            display.autocomplete = 'off';
            for (const name of ['aria-describedby', 'aria-labelledby']) {
                if (from.hasAttribute(name)) display.setAttribute(name, from.getAttribute(name));
            }
            const state = {from, to, display, form, picker: null, syncing: false,
                defaults: [from.defaultValue, to.defaultValue], labels, endLabels,
                originalLabels: [...labels, ...endLabels].map(label => [label, label.htmlFor, label.textContent, label.hidden])};
            rangeInputs.set(from, state);
            rangeInputs.set(to, state);
            ranges.add(state);
            [from, to].forEach(input => { input.dataset.rangeSource = '1'; input.hidden = true; });
            from.after(display);
            labels.forEach(label => { label.htmlFor = display.id; label.textContent = 'Date range'; });
            const endWrapper = to.closest('.list-field, [class*="col-"]');
            if (endWrapper && !endWrapper.contains(from) && endWrapper.querySelectorAll('input, select, textarea').length === 1) {
                endWrapper.hidden = true;
                state.endWrapper = endWrapper;
                const startWrapper = from.closest('[class*="col-"]');
                if (startWrapper && !startWrapper.contains(to)) {
                    state.startWrapper = startWrapper;
                    state.startClass = startWrapper.className;
                    for (const token of [...startWrapper.classList]) {
                        const match = token.match(/^(col(?:-(?:sm|md|lg|xl|xxl))?)-(\d+)$/);
                        if (!match) continue;
                        const other = [...endWrapper.classList].find(name => name.startsWith(match[1] + '-'));
                        const width = Number(match[2]) + Number(other?.split('-').at(-1) || 0);
                        if (width <= 12 && other) startWrapper.classList.replace(token, `${match[1]}-${width}`);
                    }
                }
            } else endLabels.forEach(label => { label.hidden = true; });
            const firstWrapper = from.closest('.list-field');
            firstWrapper?.classList.add('date-range-field');
            const validateRange = () => {
                const error = [from, to].find(input => !input.validity.valid);
                display.setCustomValidity(error ? error.validationMessage : '');
            };
            const notify = () => display.dispatchEvent(new CustomEvent('date-range:change', {bubbles: true}));
            const commit = dates => {
                if (state.syncing) return;
                if (dates.length === 1) {
                    display.setCustomValidity('Select an end date to complete the range.');
                    return;
                }
                state.syncing = true;
                const oldFrom = from.value;
                from.value = dates[0] ? state.picker.formatDate(dates[0], 'Y-m-d') : '';
                to.value = dates[1] ? state.picker.formatDate(dates[1], 'Y-m-d') : '';
                if (oldFrom && to.min === oldFrom) to.min = from.value;
                [from, to].forEach(input => {
                    input.dispatchEvent(new Event('input', {bubbles: true}));
                    input.dispatchEvent(new Event('change', {bubbles: true}));
                });
                state.syncing = false;
                state.committedDisplay = display.value;
                validateRange();
                if (display.validity.valid) notify();
            };
            state.picker = window.flatpickr(display, {
                mode: 'range', dateFormat: 'd-m-Y', allowInput: true, disableMobile: true,
                allowInvalidPreload: true, locale: {rangeSeparator: ' to '},
                parseDate: (value, format) => {
                    if (value instanceof Date) return value;
                    const parsed = window.flatpickr.parseDate(value, format);
                    return parsed && window.flatpickr.formatDate(parsed, format) === value ? parsed : undefined;
                },
                onChange: commit,
            });
            const checkTyped = event => {
                if (display.value === state.committedDisplay) return;
                const parts = display.value.trim() ? display.value.trim().split(' to ') : [];
                const valid = parts.length <= 2 && parts.every(text => {
                    const parsed = window.flatpickr.parseDate(text, 'd-m-Y');
                    return /^\d{2}-\d{2}-\d{4}$/.test(text) && parsed
                        && window.flatpickr.formatDate(parsed, 'd-m-Y') === text;
                });
                if (!valid || parts.length === 1) {
                    display.setCustomValidity(valid ? 'Select an end date to complete the range.' : 'Use DD-MM-YYYY to DD-MM-YYYY.');
                    event.stopImmediatePropagation();
                    if (event.type === 'keydown') event.preventDefault();
                } else {
                    display.setCustomValidity('');
                }
            };
            display.addEventListener('blur', checkTyped, true);
            display.addEventListener('keydown', event => { if (event.key === 'Enter') checkTyped(event); }, true);
            const refresh = () => {
                if (state.syncing) return;
                state.syncing = true;
                const picker = state.picker;
                const values = [from.value, to.value].filter(Boolean).map(value => window.flatpickr.parseDate(value, 'Y-m-d'));
                picker.set('minDate', from.min || undefined);
                picker.set('maxDate', to.max || undefined);
                picker.setDate(values, false);
                display.disabled = from.disabled || to.disabled;
                display.required = from.required || to.required;
                display.title = !from.value && to.value ? 'To date only' : from.value && !to.value ? 'From date only' : '';
                state.committedDisplay = display.value;
                validateRange();
                state.syncing = false;
            };
            state.refresh = refresh;
            [from, to].forEach(input => {
                input.addEventListener('change', () => { if (!state.syncing) { refresh(); notify(); } });
                input.addEventListener('invalid', event => {
                    event.preventDefault(); validateRange(); display.focus();
                });
            });
            friendlyControls(display, state.picker);
            refresh();
        }
    }
}

function enhance(root) {
    const inputs = [...root.querySelectorAll(selector)];
    if (root.matches?.(selector)) inputs.unshift(root);
    for (const input of inputs) {
        if (input._flatpickr || rangeInputs.has(input) || input.readOnly || input.hasAttribute('data-native')) continue;
        const type = input.type;
        input.dataset.pickerType = type;
        const isTime = type === 'time';
        const labels = Array.from(input.labels || []);
        const initialError = input.validity.customError ? input.validationMessage : '';
        defaultValues.set(input, input.defaultValue);
        const format = isTime ? 'H:i' : type === 'date' ? 'Y-m-d' : 'Y-m-d\\TH:i';
        window.flatpickr(input, {
            dateFormat: format,
            altInput: true,
            altFormat: isTime ? 'G:i K' : type === 'date' ? 'd-m-Y' : 'd-m-Y G:i K',
            enableTime: type !== 'date',
            noCalendar: isTime,
            time_24hr: false,
            minuteIncrement: 1,
            allowInput: true,
            disableMobile: true,
            // Retain old input for correction rather than clearing out-of-range values.
            allowInvalidPreload: true,
            minDate: !isTime ? input.min || undefined : undefined,
            maxDate: !isTime ? input.max || undefined : undefined,
            minTime: isTime ? input.min || undefined : undefined,
            maxTime: isTime ? input.max || undefined : undefined,
            onChange: () => validate(input),
        });
        const display = input._flatpickr.altInput;
        if (display) {
            display.placeholder = isTime ? 'hh:mm AM/PM' : type === 'date' ? 'DD-MM-YYYY' : 'DD-MM-YYYY hh:mm AM/PM';
            if (input.id) {
                display.id = input.id + '_display';
                labels.forEach(label => { label.htmlFor = display.id; });
            }
            for (const attribute of ['aria-describedby', 'aria-label', 'aria-labelledby']) {
                if (input.hasAttribute(attribute)) display.setAttribute(attribute, input.getAttribute(attribute));
            }
            const nativeValidity = input.setCustomValidity.bind(input);
            input.setCustomValidity = message => {
                nativeValidity(message);
                display.setCustomValidity(message);
            };
            if (initialError) input.setCustomValidity(initialError);
            display.addEventListener('input', () => {
                const parsed = window.flatpickr.parseDate(display.value, input._flatpickr.config.altFormat);
                const valid = !display.value || (parsed && input._flatpickr.formatDate(parsed, input._flatpickr.config.altFormat) === display.value);
                const message = valid ? '' : isTime ? 'Use a valid time, for example 09:30 AM.' : 'Use the shown date format: DD-MM-YYYY.';
                if (message || messages.has(input)) {
                    input.setCustomValidity(message);
                    if (message) messages.set(input, message);
                    else messages.delete(input);
                }
            });
        }
        input.placeholder ||= isTime ? 'HH:MM' : type === 'date' ? 'YYYY-MM-DD' : 'YYYY-MM-DD HH:MM';
        input.addEventListener('input', () => validate(input));
        input.addEventListener('change', () => validate(input));
        friendlyControls(input, input._flatpickr);
        managed.add(input);
    }
}

export function initDateTimePickers() {
    enhanceRanges();
    enhance(document);
    new MutationObserver(records => {
        if (records.some(record => record.type === 'childList')) enhanceRanges();
        for (const record of records) {
            if (record.type === 'childList') {
                for (const node of record.addedNodes) {
                    if (node.nodeType === 1) enhance(node);
                }
            } else if (rangeInputs.has(record.target)) {
                rangeInputs.get(record.target).refresh();
            } else if (managed.has(record.target)) {
                const input = record.target;
                const picker = input._flatpickr;
                const value = input.value;
                const displayValue = picker.altInput?.value;
                const isTime = input.dataset.pickerType === 'time';
                if (record.attributeName === 'min' || record.attributeName === 'max') {
                    const option = record.attributeName + (isTime ? 'Time' : 'Date');
                    picker.set(option, input.getAttribute(record.attributeName) || undefined);
                    input.value = value;
                    if (picker.altInput) picker.altInput.value = displayValue;
                    validate(input);
                } else if (picker.altInput) {
                    picker.altInput.disabled = input.disabled;
                    picker.altInput.required = input.required;
                    picker.altInput.readOnly = input.readOnly;
                }
            }
        }
        for (const input of managed) {
            if (!input.isConnected) {
                input._flatpickr?.destroy();
                delete input.setCustomValidity;
                delete input._flatpickr;
                managed.delete(input);
            }
        }
        for (const range of ranges) {
            if (!range.from.isConnected || !range.to.isConnected) {
                range.picker.destroy();
                delete range.display._flatpickr;
                range.display.remove();
                for (const input of [range.from, range.to]) {
                    rangeInputs.delete(input); input.hidden = false; delete input.dataset.rangeSource;
                }
                if (range.endWrapper) range.endWrapper.hidden = false;
                if (range.startWrapper) range.startWrapper.className = range.startClass;
                for (const [label, htmlFor, text, hidden] of range.originalLabels) {
                    label.htmlFor = htmlFor; label.textContent = text; label.hidden = hidden;
                }
                ranges.delete(range);
            }
        }
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['min', 'max', 'disabled', 'required', 'readonly'] });
    document.addEventListener('reset', event => {
        setTimeout(() => {
            for (const range of ranges) {
                if (range.form === event.target) {
                    [range.from.value, range.to.value] = range.defaults;
                    range.refresh();
                }
            }
            for (const input of managed) {
                if (input.form === event.target) {
                    input._flatpickr.setDate(defaultValues.get(input) || '', false);
                    validate(input);
                }
            }
        }, 0);
    });
}
