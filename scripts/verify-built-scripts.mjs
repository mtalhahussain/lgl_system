import { createServer } from 'node:http';
// Isolated browser fixture: no Laravel connection, accounts or database writes.
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdtemp, readFile, readdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';


// Every Blade view, including dormant templates, must keep executable code in Vite.
let viewCount = 0;
async function inspect(directory) {
    for (const item of await readdir(directory, {withFileTypes: true})) {
        const file = `${directory}/${item.name}`;
        if (item.isDirectory()) await inspect(file);
        else if (file.endsWith('.blade.php')) {
            const source = await readFile(file, 'utf8');
            assert.ok(!/<script\b(?![^>]*\bsrc\s*=)[^>]*>[\s\S]*?<\/script>/i.test(source), `${file}: inline script`);
            assert.ok(!/\bon[a-z]+\s*=\s*["']/i.test(source), `${file}: inline event handler`);
            assert.ok(!/javascript\s*:/i.test(source), `${file}: executable URL`);
            viewCount++;
        }
    }
}
await inspect('resources/views');
const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
const entry = '/build/' + manifest['resources/js/app.js'].file;
const fixtures = new Map();
const server = createServer(async (request, response) => {
    const url = new URL(request.url, 'http://localhost');
    try {
        if (fixtures.has(url.pathname)) {
            response.setHeader('Content-Type', 'text/html');
            response.setHeader('Content-Security-Policy', "script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'");
            response.end(`<!doctype html><html><head><meta name="csrf-token" content="fixture-token"><script type="module" src="${entry}"></script></head><body>${fixtures.get(url.pathname)}</body></html>`);
        } else if (/^\/build\/assets\/[\w.-]+$/.test(url.pathname)) {
            response.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript');
            response.end(await readFile('public' + url.pathname));
        } else { response.statusCode = 404; response.end(); }
    } catch { response.statusCode = 500; response.end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;

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


    const errors = [];
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
    });
    await call('Runtime.enable', {});
    await call('Page.enable', {});
    await call('Page.addScriptToEvaluateOnNewDocument', {source: `
        window.__requests = []; window.__prints = 0; window.__alerts = [];
        window.print = () => window.__prints++;
        window.alert = message => window.__alerts.push(message);
        window.confirm = () => false;
        window.fetch = async (url, options = {}) => {
            window.__requests.push({url: String(url), options});
            return {ok: true, json: async () => ({unread: 2, items: [{id: 7, title: 'Class', body: 'Tomorrow', time: 'Now', url: '#', level: 'info', read: false}]})};
        };
        window.bootstrap = {Modal: class { constructor(element) {this.element = element;} show() {this.element.dataset.shown = 'true';} }};
        localStorage.setItem('lgl:sidebar', 'collapsed');
    `});
    let count = 0;
    async function open(name, html) {
        fixtures.set('/' + name, html);
        await call('Page.navigate', {url: origin + '/' + name});
        for (let i=0; i<100; i++) {
            try {
                if (await evaluate(`location.pathname === ${JSON.stringify('/'+name)} && document.readyState === 'complete'`)) break;
            } catch { /* Navigation replaces the context. */ }
            await new Promise(resolve => setTimeout(resolve, 30));
        }
        assert.equal(await evaluate('document.readyState'), 'complete', name);
    }
    async function check(expression, name) {
        assert.equal(await evaluate(expression), true, name);
        assert.deepEqual(errors, [], `${name}: no runtime exceptions`);
        count++;
    }
    await open('login', `<form><input id="password" type="password"><button id="passwordToggle" type="button"><i></i></button><button type="submit">Sign in</button></form><template data-page-module="auth-login"></template>`);
    await check(`document.getElementById('passwordToggle').click(); document.getElementById('password').type === 'text'`, 'compiled login reveal');
    await check(`document.querySelector('form').dispatchEvent(new Event('submit', {cancelable:true})); document.querySelector('[type=submit]').disabled`, 'login loading state');

    await open('admission', `<input id="custom_schedule" type="checkbox"><div id="schedule_rows"><input name="schedule[0][amount]"></div><template data-page-module="partials-admission-schedule"></template>`);
    await check(`document.getElementById('schedule_rows').hidden && document.querySelector('#schedule_rows input').disabled`, 'custom schedule initial state');
    await check(`document.getElementById('custom_schedule').click();!document.getElementById('schedule_rows').hidden && !document.querySelector('#schedule_rows input').disabled`, 'custom schedule toggle');

    await open('teacher', `<select id="salary_type"><option value="per_student">Per student</option><option value="monthly">Monthly</option></select><div id="monthly_salary_group"></div><div id="per_student_amount_group"><input id="per_student_amount"></div><template data-page-module="admin-people-form"></template>`);
    await check(`document.getElementById('monthly_salary_group').hidden && document.getElementById('per_student_amount').required`, 'teacher salary initial state');
    await check(`document.getElementById('salary_type').value='monthly';document.getElementById('salary_type').dispatchEvent(new Event('change'));!document.getElementById('monthly_salary_group').hidden && document.getElementById('per_student_amount').disabled`, 'teacher salary switch');

    await open('accounts', `<form id="accountCreateForm"><select id="role"><option value="staff">Staff</option><option value="teacher">Teacher</option></select><select id="salary_type"><option value="per_student">Per student</option></select><input id="email"><input id="phone"><input id="designation"><div data-role-section="staff teacher" data-pay-field="monthly"><input id="monthly_salary"></div><div data-role-section="teacher" data-pay-field="per_student"><input id="per_student_amount"></div></form><template data-page-module="accounts-create"></template>`);
    await check(`document.getElementById('monthly_salary').required && document.getElementById('per_student_amount').disabled && !document.getElementById('email').required`, 'staff account fields');
    await check(`document.getElementById('role').value='teacher';document.getElementById('role').dispatchEvent(new Event('change'));document.getElementById('per_student_amount').required && !document.getElementById('per_student_amount').disabled && document.getElementById('monthly_salary').disabled`, 'teacher account fields');

    const meta = JSON.stringify({'5': {name: `Ali O'Reilly "A"`, level: 'A2'}}).replaceAll('"', '&quot;');
    await open('certificates', `<select id="student_id"><option value="">None</option><option value="5">Student</option></select><input id="student_name"><input id="father_name"><select id="level"><option>A1</option><option>A2</option></select><input id="completion_date"><div id="preview-name"></div><div id="preview-father"></div><div id="preview-level"></div><div id="preview-date"></div><div id="preview-paragraph"></div><template data-page-module="certificates-index" data-students="${meta}" data-paragraphs='{"A1":"A1 wording.","A2":"A2 wording <escaped>"}'></template>`);
    await check(`document.getElementById('student_id').value='5';document.getElementById('student_id').dispatchEvent(new Event('change'));document.getElementById('preview-name').textContent === ${JSON.stringify(`Ali O'Reilly "A"`)} && document.getElementById('preview-level').textContent === 'A2'`, 'certificate metadata and preview');
    await check(`document.getElementById('preview-paragraph').textContent === 'A2 wording <escaped>' && !document.getElementById('preview-paragraph').querySelector('escaped')`, 'certificate level paragraph and safe text rendering');
    await check(`document.getElementById('level').value='A1';document.getElementById('level').dispatchEvent(new Event('change'));document.getElementById('preview-paragraph').textContent === 'A1 wording.'`, 'certificate manual level paragraph switch');
    await open('certificate-view-only', '<template data-page-module="certificates-index" data-students="{}"></template>');
    await check('true', 'certificate without issue permission');

    await open('package', `<select data-package-batch data-level="A1"><option value="1" data-fee="10000">A1</option></select><select data-package-batch data-level="A2"><option value="2" data-fee="10000">A2</option></select><div id="package-allocation"></div><input id="price" value="150"><input id="installments" value="3"><input type="radio" name="plan" value="full" checked><input type="radio" name="plan" value="installments"><div data-plan="full"><select id="method"><option>cash</option></select></div><div data-plan="installments"><input></div><template data-page-module="enrollment-packages-create" data-currency="PKR"></template>`);
    await check(`document.getElementById('package-allocation').textContent.includes('A1: PKR 75.00') && document.getElementById('method').required`, 'package exact preview split');
    await check(`document.querySelector('[value=installments]').click();document.getElementById('installments').required && document.getElementById('method').disabled`, 'package payment plan switch');
    await open('package-view-only', '<template data-page-module="enrollment-packages-create" data-currency="PKR"></template>');
    await check('true', 'package without create permission');

    await open('notifications', `<div id="notifBell" data-feed="/base/feed" data-readall="/base/read-all" data-read-base="/base/notifications" data-user="5" data-csrf="test-csrf"></div><div id="notifCount"></div><div id="notifList"></div><button id="notifReadAll">Read</button><template data-page-module="partials-notification-bell"></template>`);
    await check(`document.getElementById('notifCount').textContent === '2' && document.querySelector('.notif-item').textContent.includes('Tomorrow')`, 'notification feed');
    await check(`document.querySelector('.notif-item').click();__requests.some(r => r.url === '/base/notifications/7/read' && r.options.headers['X-CSRF-TOKEN'] === 'test-csrf')`, 'notification escaped read base and csrf');
    await check(`document.getElementById('notifReadAll').click();__requests.some(r => r.url === '/base/read-all' && r.options.method === 'POST')`, 'notification mark all read');


    const batchForm = await readFile('resources/views/admin/batches/form.blade.php', 'utf8');
    const batchTemplate = batchForm.match(/<template id="schedule-template">([\s\S]*?)<\/template>/)[1]
        .replace(/@foreach[\s\S]*?@endforeach/, ['mon','tue','wed','thu','fri','sat','sun'].map(day => `<option value="${day}">${day}</option>`).join(''));
    await open('batch', `<form><input id="start_date" type="date" value="2026-11-21"><input id="end_date" type="date" value="2026-11-25"><div id="schedule-rows"></div><template id="schedule-template">${batchTemplate}</template><button type="button" id="schedule-add">Add</button></form>`);
    await check(`document.getElementById('schedule-add').click();document.getElementById('schedule-add').click();document.querySelectorAll('.schedule-row').length === 2 && new Set(Array.from(document.querySelectorAll('.schedule-row select'), el=>el.value)).size === 2`, 'compiled batch add and distinct weekdays');
    await check(`document.querySelector('.schedule-remove').click();document.getElementById('schedule-add').click();new Set(Array.from(document.querySelectorAll('#schedule-rows select'), el=>el.name)).size === 2`, 'compiled batch remove and unique field names');

    await open('legacy-fee', `<button data-action="pay-installment" data-installment-id="17" data-student-name="O&#39;Reilly &quot;A&quot;" data-amount="PKR 100.00">Pay</button><div id="studentName"></div><div id="installmentAmount"></div><form id="paymentForm"></form><div id="paymentModal"></div><template data-page-module="admin-fees-student"></template>`);
    await check(`document.querySelector('[data-action]').click();document.getElementById('studentName').textContent === ${JSON.stringify(`O'Reilly "A"`)} && document.getElementById('paymentForm').action.endsWith('/fees/17/pay') && document.getElementById('paymentModal').dataset.shown === 'true'`, 'retained fee modal escaped attributes');
    await open('legacy-student', `<button data-action="enroll-fingerprint" data-student-id="5" data-student-name="O&#39;Reilly">Enroll</button><button data-action="remove-fingerprint" data-student-id="5" data-student-name="O&#39;Reilly">Remove</button><div id="enrollStudentName"></div><div id="removeStudentName"></div><div id="enrollFingerprintModal"></div><div id="removeFingerprintModal"></div><template data-page-module="admin-students-edit"></template>`);
    await check(`document.querySelector('[data-action=enroll-fingerprint]').click();document.getElementById('enrollStudentName').textContent === "O'Reilly" && document.getElementById('enrollFingerprintModal').dataset.shown === 'true'`, 'retained fingerprint modal only, no device call');
    await check(`document.querySelector('[data-action=remove-fingerprint]').click();document.getElementById('removeFingerprintModal').dataset.shown === 'true'`, 'retained fingerprint removal dialog only');

    await open('common', `<button data-print>Print</button><a href="/unsafe" data-confirm-click="Sure?">Confirm</a><button id="sidebarCollapse"></button><nav id="appSidebar"><a class="nav-link">Students</a></nav><form data-notifications-read-all action="/base/read-all"><input name="_token" value="form-csrf"><button type="submit">Read</button></form>`);
    await check(`document.querySelector('[data-print]').click();__prints === 1`, 'shared print');
    await check(`!document.querySelector('[data-confirm-click]').dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true}))`, 'cancelled confirmation');
    await check(`document.documentElement.classList.contains('sidebar-collapsed') && document.getElementById('sidebarCollapse').getAttribute('aria-expanded') === 'false'`, 'sidebar persisted state');
    await check(`document.getElementById('sidebarCollapse').click();!document.documentElement.classList.contains('sidebar-collapsed')`, 'sidebar toggle');
    // Keep this last: successful mark-all reloads the page.
    await check(`document.querySelector('form').dispatchEvent(new Event('submit', {cancelable:true}));__requests.some(r => r.options.headers['X-CSRF-TOKEN'] === 'form-csrf')`, 'notification history form csrf');
    console.log(`PASS: ${viewCount} Blade views free of executable inline JS; ${count} production-bundle browser checks under script-src self (mocked requests, no Laravel/database).`);
} finally {
    socket?.close();
    browser.kill();
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
}
