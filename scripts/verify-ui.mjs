import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const base = new URL(process.env.LGL_UI_URL ?? 'http://127.0.0.1:8791');
if (!['127.0.0.1', 'localhost'].includes(base.hostname)) throw new Error('UI checks require a local preview.');
const environment = await readFile(path.join(root, '.env'), 'utf8');
if (!/^DB_DATABASE=lgl_system_demo_/m.test(environment) || !/^APP_ENV=local\s*$/m.test(environment)) {
    throw new Error('UI checks require the configured local demo; do not use operational data.');
}
const binary = [process.env.LGL_BROWSER_BINARY,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(item => item && existsSync(item));
if (!binary) throw new Error('Set LGL_BROWSER_BINARY to an installed Chrome/Edge browser.');
const directory = path.join(root, 'storage/app/private/ui', new Date().toISOString().replace(/[:.]/g, '-'));
const profile = path.join(directory, 'browser-profile');
await mkdir(profile, { recursive: true });
await mkdir(path.join(profile, 'Default'), { recursive: true });
await writeFile(path.join(profile, 'Default/Preferences'), JSON.stringify({ credentials_enable_service: false,
    profile: { password_manager_enabled: false, password_manager_leak_detection: false } }));
const browser = spawn(binary, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function poll(action, label, timeout = 25000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try { const value = await action(); if (value) return value; } catch { /* Navigation temporarily replaces contexts. */ }
        await delay(150);
    }
    throw new Error(`Timed out: ${label}`);
}
let socket;
let call;
const checks = [];
const record = (name, passed, details = {}) => checks.push({ name, passed: Boolean(passed), ...details });
try {
    const endpoint = await poll(async () => {
        const contents = await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8');
        return `http://127.0.0.1:${contents.split('\n')[0]}`;
    }, 'browser endpoint');
    const target = await (await fetch(`${endpoint}/json/new?about:blank`, { method: 'PUT' })).json();
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let sequence = 0;
    const pending = new Map();
    const handleMessage = event => {
        const message = JSON.parse(event.data);
        const entry = pending.get(message.id);
        if (entry) {
            pending.delete(message.id);
            clearTimeout(entry.timer);
            message.error ? entry.reject(new Error(message.error.message)) : entry.resolve(message.result);
        }
    };
    socket.onmessage = handleMessage;
    call = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error(`CDP timeout: ${method}`)); }, 30000);
        pending.set(id, { resolve, reject, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    };
    await call('Page.enable');
    await call('Page.bringToFront');
    await call('Emulation.setFocusEmulationEnabled', { enabled: true });
    await call('Network.enable');
    const viewport = async (width, height) => call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
    const navigate = async pathname => {
        await call('Page.navigate', { url: new URL(pathname, base).href });
        await poll(() => evaluate(`location.pathname === ${JSON.stringify(pathname)} && document.readyState === 'complete'`), pathname);
    };
    const screenshot = async filename => {
        const result = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await writeFile(path.join(directory, filename), Buffer.from(result.data, 'base64'));
    };
    for (const [role, email, password] of [['admin', process.env.LGL_UI_ADMIN_EMAIL ?? 'admin@admin.com', process.env.LGL_UI_PASSWORD ?? 'password'],
        ['teacher', process.env.LGL_UI_TEACHER_EMAIL ?? 'teacher@lgl.com', process.env.LGL_UI_PASSWORD ?? 'password'],
        ['student', process.env.LGL_UI_STUDENT_EMAIL ?? 'student1@lgl.com', process.env.LGL_UI_PASSWORD ?? 'password']]) {
        if (role !== 'admin') {
            const previousSocket = socket;
            const nextTarget = await (await fetch(`${endpoint}/json/new?about:blank`, { method: 'PUT' })).json();
            socket = new WebSocket(nextTarget.webSocketDebuggerUrl);
            await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
            socket.onmessage = handleMessage;
            previousSocket.close();
            await call('Page.enable');
            await call('Network.enable');
            await call('Page.bringToFront');
            await call('Emulation.setFocusEmulationEnabled', { enabled: true });
        }
        await call('Network.clearBrowserCookies');
        await viewport(1366, 900);
        await navigate('/login');
        if (role === 'admin') {
            await screenshot('login-desktop.png');
            record('login: compiled Tailwind utilities loaded', await evaluate(`getComputedStyle(document.querySelector('.login-preview > div')).display === 'flex'`));
            await evaluate(`document.querySelector('#passwordToggle').click()`);
            record('login: password visibility toggle', await evaluate(`document.querySelector('[name=password]').type === 'text' && document.querySelector('#passwordToggle').getAttribute('aria-pressed') === 'true'`));
            await evaluate(`document.querySelector('#passwordToggle').click()`);
            await viewport(390, 844);
            record('login mobile: no document overflow', await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));
            await screenshot('login-mobile.png');
            await viewport(1366, 900);
        }
        await evaluate(`document.querySelector('[name=email]').value=${JSON.stringify(email)};
            document.querySelector('[name=password]').value=${JSON.stringify(password)};
            document.querySelector('form').requestSubmit();`);
        await poll(() => evaluate(`location.pathname === '/${role}/dashboard' && document.readyState === 'complete'`), `${role} login`);
        record(`${role}: real browser login`, await evaluate(`document.querySelector('h1')?.textContent.includes('${role[0].toUpperCase() + role.slice(1)} Dashboard')`));
        record(`${role}: Bootstrap CSS loaded`, await evaluate(`getComputedStyle(document.querySelector('.navbar')).display === 'flex'`));
        await evaluate(`document.body.tabIndex = -1; document.body.focus()`);
        await call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        record(`${role}: keyboard Tab reaches skip link`, await evaluate(`document.activeElement.matches('.skip-link:focus-visible')`));
        await call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        record(`${role}: keyboard Tab reaches visible brand link`, await evaluate(`document.activeElement === document.querySelector('.navbar-brand') && document.activeElement.matches(':focus-visible')`));
        await screenshot(`${role}-desktop.png`);
        const navigationMarker = crypto.randomUUID();
        await evaluate(`window.__lglQaNavigationMarker = ${JSON.stringify(navigationMarker)}; document.querySelector('.navbar-brand').click()`);
        await poll(() => evaluate(`location.pathname === '/${role}/dashboard' && document.readyState === 'complete' && window.__lglQaNavigationMarker !== ${JSON.stringify(navigationMarker)}`), `${role} brand redirect`);
        record(`${role}: brand returns to own dashboard`, true);
        await viewport(390, 844);
        await delay(250);
        record(`${role}: mobile has no document overflow`, await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));
        await poll(() => evaluate(`typeof bootstrap !== 'undefined' && !!bootstrap.Collapse`), 'Bootstrap navigation JS');
        await call('Page.bringToFront');
        // Activate the renderer with real pointer input before sending keyboard
        // events; DOM focus alone can leave a reused headless target unfocused.
        const heading = await evaluate(`(() => { const box = document.querySelector('h1').getBoundingClientRect(); return {x: box.left + 2, y: box.top + 2}; })()`);
        await call('Input.dispatchMouseEvent', { type: 'mousePressed', ...heading, button: 'left', clickCount: 1 });
        await call('Input.dispatchMouseEvent', { type: 'mouseReleased', ...heading, button: 'left', clickCount: 1 });
        await evaluate(`window.__lglQaMenuEvents = []; ['keydown', 'keypress', 'keyup', 'click'].forEach(type =>
            document.querySelector('[aria-controls=appSidebar]').addEventListener(type, event =>
                window.__lglQaMenuEvents.push({type, key: event.key ?? null, trusted: event.isTrusted})))`);
        await evaluate(`document.querySelector('[aria-controls=appSidebar]').focus()`);
        await poll(() => evaluate(`document.hasFocus() && document.activeElement === document.querySelector('[aria-controls=appSidebar]')`), 'mobile menu keyboard focus');
        await call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r', unmodifiedText: '\r' });
        await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        let open;
        try {
            open = await poll(() => evaluate(`document.querySelector('#appSidebar').classList.contains('show')`), 'mobile menu open', 5000);
        } catch (error) {
            record(`${role}: mobile keyboard diagnostics`, false, { state: await evaluate(`({
                activeTag: document.activeElement.tagName, activeControl: document.activeElement.getAttribute('aria-controls'),
                focused: document.hasFocus(), sidebar: document.querySelector('#appSidebar').className,
                expanded: document.querySelector('[aria-controls=appSidebar]').getAttribute('aria-expanded'),
                events: window.__lglQaMenuEvents})`) });
            throw error;
        }
        record(`${role}: mobile navigation opens with keyboard Enter`, open);
        await screenshot(`${role}-mobile-menu.png`);
        await evaluate(`document.querySelector('[aria-controls=appSidebar]').click()`);
        await poll(() => evaluate(`!document.querySelector('#appSidebar').classList.contains('show') && !document.querySelector('#appSidebar').classList.contains('collapsing')`), 'mobile menu closed');
        await screenshot(`${role}-mobile.png`);
        if (role === 'admin') {
            for (const pathname of ['/reports/enrollments', '/reports/dues', '/fees/reports', '/attendance/reports']) {
                await navigate(pathname);
                record(`mobile report ${pathname}: no document overflow`, await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'));
            }
            await viewport(1366, 900);
            await navigate('/payments/1');
            await call('Emulation.setEmulatedMedia', { media: 'print' });
            record('receipt print: navigation/actions hidden', await evaluate(`
                getComputedStyle(document.querySelector('.navbar')).display === 'none' &&
                getComputedStyle(document.querySelector('#appSidebar')).display === 'none' &&
                [...document.querySelectorAll('.d-print-none')].every(item => getComputedStyle(item).display === 'none')`));
            await screenshot('receipt-print.png');
            const pdf = await call('Page.printToPDF', { printBackground: true, preferCSSPageSize: true });
            await writeFile(path.join(directory, 'receipt.pdf'), Buffer.from(pdf.data, 'base64'));
            record('receipt PDF generated', Buffer.from(pdf.data, 'base64').subarray(0, 5).toString() === '%PDF-');
            await call('Emulation.setEmulatedMedia', { media: '' });
        }
    }
} catch (error) {
    record('browser verification completed', false, { error: error.message });
} finally {
    if (call && socket?.readyState === WebSocket.OPEN) {
        try { await call('Browser.close'); } catch { /* Browser closes its socket during shutdown. */ }
        socket.close();
    } else browser.kill();
}
const report = { directory, checks, passed: checks.filter(item => item.passed).length, failed: checks.filter(item => !item.passed).length };
await writeFile(path.join(directory, 'report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
process.exitCode = report.failed === 0 ? 0 : 1;
