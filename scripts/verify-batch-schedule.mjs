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

    const form = await readFile('resources/views/admin/batches/form.blade.php', 'utf8');
    let template = form.match(/<template id="schedule-template">([\s\S]*?)<\/template>/)[1];
    template = template.replace(/@foreach[\s\S]*?@endforeach/, ['mon','tue','wed','thu','fri','sat','sun'].map(day => `<option value="${day}">${day}</option>`).join(''));
    const fixture = `<form><input id="start_date" type="date" value="2026-11-21"><input id="end_date" type="date" value="2026-11-22"><div id="schedule-rows"></div><template id="schedule-template">${template}</template><button type="button" id="schedule-add">Add</button></form>`;
    await evaluate(`document.body.innerHTML = ${JSON.stringify(fixture)}`);
    const source = (await readFile('resources/js/batch-schedule.js','utf8')).replace('export function','function');
    await evaluate(source + ';initBatchSchedule();');
    assert.equal(await evaluate(`document.getElementById('schedule-add').click(); document.querySelectorAll('.schedule-row').length`), 1);
    assert.equal(await evaluate(`document.getElementById('schedule-add').click(); new Set(Array.from(document.querySelectorAll('.schedule-row select'), s=>s.value)).size`), 2);
    assert.equal(await evaluate(`document.querySelector('.schedule-remove').click();document.querySelectorAll('.schedule-row').length`),1);
    assert.equal(await evaluate(`for(let i=0;i<6;i++) document.getElementById('schedule-add').click();document.getElementById('schedule-add').hidden`),true);
    assert.equal(await evaluate(`document.querySelector('.schedule-remove').click();document.getElementById('schedule-add').hidden`),false);
    assert.equal(await evaluate(`document.getElementById('end_date').value='2026-11-20';document.getElementById('end_date').dispatchEvent(new Event('change'));document.getElementById('end_date').validity.customError`),true);
    console.log('PASS: schedule add, unique weekdays, remove, seven-day cap, re-add and date bounds (isolated browser).');
} finally {
    socket?.close();
    browser.kill();
}
