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
const profile = await mkdtemp(path.join(tmpdir(), 'lgl-date-ranges-'));
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
    await evaluate(`document.body.innerHTML = '<form id="fixture" data-list-filter><div class="list-field"><label for="from">From</label><input type="date" id="from" name="from" value="2026-11-12"></div><div class="list-field"><label for="to">To</label><input type="date" id="to" name="to" value="2026-11-12"></div></form><form id="report"><label for="start">From</label><input id="start" type="date" name="start_date" value="2026-11-01"><label for="end">To</label><input id="end" type="date" name="end_date"></form><form id="batch" method="post"><input type="date" name="start_date"><input type="date" name="end_date"></form><form id="generation" method="post"><div class="col-6"><label for="genfrom">From</label><input id="genfrom" type="date" name="from" min="2026-11-01" required></div><div class="col-6"><label for="gento">To</label><input id="gento" type="date" name="to" max="2026-12-31" required></div></form>'; window.submissions=0; document.getElementById('fixture').requestSubmit = () => { if (document.getElementById('fixture').checkValidity()) window.submissions++; };`);
    await evaluate(await readFile('resources/vendor/flatpickr/flatpickr.js', 'utf8'));
    const lists = (await readFile('resources/js/list.js', 'utf8')).replaceAll('export function', 'function');
    await evaluate(`(() => { ${lists}; initListFilters(); })()`);
    const source = (await readFile('resources/js/date-time-pickers.js', 'utf8'))
        .replace("import '../vendor/flatpickr/flatpickr.js';", '').replace('export function', 'function');
    await evaluate(`(() => { ${source}; initDateTimePickers(); })()`);
    const results = await evaluate(`(async () => {
        const checks = {}, form = document.getElementById('fixture');
        const from = document.getElementById('from'), to = document.getElementById('to');
        const display = form.querySelector('.lgl-date-range'), picker = display._flatpickr;
        const tick = () => new Promise(resolve => setTimeout(resolve, 30));
        checks.singleControl = form.querySelectorAll('.lgl-date-range').length === 1 && from.hidden && to.hidden;
        checks.initialValues = new FormData(form).get('from') === '2026-11-12' && new FormData(form).get('to') === '2026-11-12';
        checks.initialDisplay = display.value.includes('12-11-2026') && picker.config.mode === 'range';
        checks.label = form.querySelector('label').htmlFor === display.id;
        picker.setDate(['13-11-2026'], true);
        checks.partialDoesNotSubmit = window.submissions === 0 && from.value === '2026-11-12' && !display.checkValidity();
        picker.setDate(['13-11-2026','16-11-2026'], true);
        checks.completedRange = from.value === '2026-11-13' && to.value === '2026-11-16' && display.checkValidity();
        checks.oneSubmission = window.submissions === 1;
        checks.displayDates = display.value === '13-11-2026 to 16-11-2026';
        checks.canonicalOnly = [...new FormData(form).keys()].join(',') === 'from,to';
        display.value = '18-11-2026 to 20-11-2026'; display.dispatchEvent(new FocusEvent('blur'));
        checks.typedRange = from.value === '2026-11-18' && to.value === '2026-11-20' && window.submissions === 2;
        display.value = '31-02-2026 to 03-03-2026'; display.dispatchEvent(new FocusEvent('blur'));
        checks.invalidTypedPreserved = !display.checkValidity() && from.value === '2026-11-18' && to.value === '2026-11-20';
        picker.setDate(['21-11-2026','21-11-2026'], true);
        checks.singleDay = from.value === '2026-11-21' && to.value === '2026-11-21' && display.checkValidity();
        picker.calendarContainer.querySelectorAll('button').forEach(button => {if(button.textContent==='Today') button.click();});
        checks.todayAction = from.value === to.value && from.value !== '' && display.checkValidity();
        picker.clear();
        checks.clear = from.value === '' && to.value === '' && display.checkValidity();
        form.reset(); await tick();
        checks.reset = from.value === '2026-11-12' && to.value === '2026-11-12' && display.value.includes('12-11-2026');
        const report = document.getElementById('report');
        checks.reportPair = !!report.querySelector('.lgl-date-range') && new FormData(report).get('end_date') === '';
        checks.batchDatesSeparate = !document.getElementById('batch').querySelector('.lgl-date-range');
        const generation = document.getElementById('generation'), gen = generation.querySelector('.lgl-date-range');
        checks.generationPair = !!gen && !generation.checkValidity();
        gen._flatpickr.setDate(['15-11-2026','16-11-2026'], true);
        checks.requiredSelection = generation.checkValidity() && new FormData(generation).get('from') === '2026-11-15';
        document.getElementById('genfrom').min = '2026-11-17'; await tick();
        checks.bounds = !gen.checkValidity() && new FormData(generation).get('from') === '2026-11-15';
        from.disabled=true; await tick(); checks.disabled = display.disabled;
        from.disabled=false; await tick(); checks.enabled = !display.disabled;
        document.body.insertAdjacentHTML('beforeend','<form id="dynamic"><input type="date" name="from"><input type="date" name="to"></form>'); await tick();
        const dynamic = document.getElementById('dynamic'), dynamicDisplay=dynamic.querySelector('.lgl-date-range');
        checks.dynamic = !!dynamicDisplay;
        dynamic.remove(); await tick(); checks.cleanup = !dynamicDisplay._flatpickr;
        return checks;
    })()`);
    const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
    const css = await readFile(`public/build/${manifest['resources/css/app.css'].file}`, 'utf8');
    await evaluate(`{const style=document.createElement('style');style.textContent=${JSON.stringify(css)};document.head.append(style);document.head.insertAdjacentHTML('beforeend','<meta name="viewport" content="width=device-width,initial-scale=1">');}`);
    for (const width of [375, 320]) {
        await call('Emulation.setDeviceMetricsOverride', {width, height:844, deviceScaleFactor:1, mobile:true});
        results[`mobile${width}`] = await evaluate(`(() => {
            const field=document.querySelector('#fixture .lgl-date-range');field._flatpickr.open();
            const box=field._flatpickr.calendarContainer.getBoundingClientRect();
            return box.width <= window.innerWidth && box.left >= 0 && box.right <= window.innerWidth + 1;
        })()`);
    }
    for (const [name, passed] of Object.entries(results)) assert.equal(passed, true, name);
    console.log(`PASS: ${Object.keys(results).length} isolated date-range browser checks (${Object.keys(results).join(', ')}).`);
} finally {
    socket?.close();
    browser.kill();
}
