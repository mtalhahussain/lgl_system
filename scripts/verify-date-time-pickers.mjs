// Isolated browser fixture: no Laravel connection, accounts or database writes.
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';

const binary = ['C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
assert.ok(binary, 'Chrome or Edge required');
const profile = await mkdtemp(path.join(tmpdir(), 'lgl-pickers-'));
const browser = spawn(binary, ['--headless=new', '--disable-gpu', '--no-first-run',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let socket;
try {
    let port;
    for (let i = 0; i < 100; i++) {
        try { port = (await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
        catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    assert.ok(port, 'Browser started');
    const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => {
        const message = JSON.parse(event.data);
        const entry = pending.get(message.id);
        if (entry) { pending.delete(message.id); clearTimeout(entry.timer); entry.resolve(message); }
    };
    const call = (method, params) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => reject(new Error('Browser timeout')), 10000);
        pending.set(id, { resolve, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        assert.ok(!result.error && !result.result.exceptionDetails, JSON.stringify(result));
        return result.result.result.value;
    };
    await evaluate(`document.body.innerHTML = '<form id="fixture"><input id="start" name="start" type="date" value="2026-11-09" required><input id="end" name="end" type="date" min="2026-11-09" required><input id="time" name="time" type="time" value="13:45"><input id="disabled" type="date" disabled><input id="native" type="date" data-native></form>'`);
    await evaluate(await readFile('resources/vendor/flatpickr/flatpickr.js', 'utf8'));
    const source = (await readFile('resources/js/date-time-pickers.js', 'utf8'))
        .replace("import '../vendor/flatpickr/flatpickr.js';", '').replace('export function', 'function');
    await evaluate(`(() => { ${source}; initDateTimePickers(); })()`);
    const results = await evaluate(`(async () => {
        const checks = {};
        const start = document.getElementById('start'), end = document.getElementById('end');
        const time = document.getElementById('time'), form = document.getElementById('fixture');
        const tick = () => new Promise(resolve => setTimeout(resolve, 25));
        checks.initial = start.value === '2026-11-09' && time.value === '13:45';
        checks.displayFormat = start._flatpickr.altInput.value === '09-11-2026';
        checks.timeDisplay = time._flatpickr.altInput.value === '01:45 PM';
        end._flatpickr.altInput.value = '14-11-2026';
        end._flatpickr.altInput.dispatchEvent(new FocusEvent('blur'));
        checks.typedDate = end.value === '2026-11-14';
        end._flatpickr.clear();
        checks.required = !end._flatpickr.altInput.checkValidity();
        checks.native = document.getElementById('native').type === 'date';
        checks.disabled = document.getElementById('disabled').disabled && document.getElementById('disabled')._flatpickr.altInput.disabled;
        start._flatpickr.open();
        checks.calendar = start._flatpickr.isOpen && !!start._flatpickr.calendarContainer.querySelector('.flatpickr-monthDropdown-months');
        start._flatpickr.close();
        end._flatpickr.setDate('2026-11-10', true);
        start.addEventListener('change', () => { end.min = start.value; });
        start._flatpickr.setDate('2026-11-12', true);
        await tick();
        checks.bounds = end.value === '2026-11-10' && !end._flatpickr.altInput.checkValidity()
            && end._flatpickr.config.minDate.getDate() === 12;
        end._flatpickr.setDate('2026-11-13', true);
        checks.corrected = end._flatpickr.altInput.checkValidity();
        end.value = '2026-02-30';
        end.dispatchEvent(new Event('input', { bubbles: true }));
        checks.invalidDate = !end._flatpickr.altInput.checkValidity();
        end._flatpickr.setDate('2026-11-13', true);
        end.setCustomValidity('Existing form validation');
        end.dispatchEvent(new Event('input', { bubbles: true }));
        checks.existingValidation = end._flatpickr.altInput.validationMessage === 'Existing form validation';
        end.setCustomValidity('');
        checks.submission = new FormData(form).get('end') === '2026-11-13'
            && new FormData(form).get('time') === '13:45';
        form.insertAdjacentHTML('beforeend', '<input id="dynamic" type="time" name="dynamic">');
        await tick();
        const dynamic = document.getElementById('dynamic');
        checks.dynamic = !!dynamic._flatpickr && dynamic._flatpickr.config.noCalendar;
        dynamic._flatpickr.setDate('00:07', true);
        checks.midnight = dynamic.value === '00:07';
        const choices = dynamic._flatpickr.calendarContainer.querySelectorAll('.lgl-time-controls select');
        choices[0].value = '9'; choices[1].value = '30'; choices[2].value = 'PM';
        choices[2].dispatchEvent(new Event('change'));
        checks.friendlyTime = dynamic.value === '21:30';
        const year = start._flatpickr.calendarContainer.querySelector('.lgl-picker-year');
        year.value = '2027'; year.dispatchEvent(new Event('change'));
        checks.yearSelect = start._flatpickr.currentYear === 2027;
        dynamic._flatpickr.open();
        [...dynamic._flatpickr.calendarContainer.querySelectorAll('button')].find(button => button.textContent === 'Done').click();
        checks.done = !dynamic._flatpickr.isOpen;
        [...dynamic._flatpickr.calendarContainer.querySelectorAll('button')].find(button => button.textContent === 'Clear').click();
        checks.clearAction = dynamic.value === '';
        dynamic.remove();
        await tick();
        checks.removal = !dynamic._flatpickr;
        form.reset();
        await tick();
        checks.reset = start.value === '2026-11-09' && end.value === '' && time.value === '13:45';
        return checks;
    })()`);
    for (const [name, passed] of Object.entries(results)) assert.equal(passed, true, name);
    console.log(`PASS: ${Object.keys(results).length} isolated browser checks (${Object.keys(results).join(', ')}).`);
} finally {
    socket?.close();
    browser.kill();
}
