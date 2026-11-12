// Isolated Select2 browser fixture; no application server, accounts or database writes.
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';

const binary = ['C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
assert.ok(binary, 'Chrome or Edge required');
const profile = await mkdtemp(path.join(tmpdir(), 'lgl-select2-'));
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
    await evaluate(`document.body.innerHTML = '<form id="fixture" style="max-width:320px"><label for="teacher">Teacher</label><select id="teacher" name="teacher_id" required><option value="">Choose teacher</option><option value="1" data-meta="German A1">Teacher One</option><option value="2" data-meta="German B2">Teacher Two</option><option value="3" disabled>Unavailable</option></select><select id="status" name="status"><option>Active</option><option>Inactive</option></select><select id="native" name="course_id" data-native><option value="1">Native course</option></select><select id="teaching" class="teaching-select" data-placeholder="Select qualification"><option value=""></option><option value="BA">BA</option></select><select id="many" name="batches_ids[]" multiple><option value="1">Batch 1</option><option value="2">Batch 2</option></select><select id="disabled" name="batch_id" disabled><option>Batch</option></select><select id="meta" name="course_id"><option value="1" data-meta="&lt;img src=x onerror=alert(1)&gt;">Safe course</option></select></form><div class="modal"><select id="modal" name="student_id"><option>Student</option></select></div><div class="flatpickr-calendar"><select id="year" name="year"><option>2026</option></select></div>'; window.nativeChanges = 0; document.getElementById('teacher').addEventListener('change', () => window.nativeChanges++);`);
    await evaluate(`{
        const style = document.createElement('style');
        style.textContent = 'input.field, select { box-sizing:border-box; font:13px/1.5 Arial; padding:10px 12px; border:1px solid #d6d3cd; border-radius:8px; }';
        document.head.append(style);
        document.getElementById('fixture').insertAdjacentHTML('afterbegin', '<input class="field" id="sizing-reference"><input class="field" id="compact-reference" style="font-size:16px;padding:6px 12px"><select id="compact" name="course_id" style="font-size:16px;padding:6px 12px"><option>Course</option></select>');
    }`);
    for (const file of ['public/vendor/select2/select2.min.css', 'public/vendor/select2/select2-bootstrap-5-theme.min.css', 'public/css/search-selects.css']) {
        const css = await readFile(file, 'utf8');
        await evaluate(`{const style=document.createElement('style');style.textContent=${JSON.stringify(css)};document.head.append(style);}`);
    }
    for (const file of ['public/vendor/jquery/jquery.min.js', 'public/vendor/select2/select2.min.js', 'public/js/search-selects.js']) {
        await evaluate(await readFile(file, 'utf8'));
    }
    const results = await evaluate(`(async () => {
        const $ = window.jQuery, checks = {};
        const teacher = document.getElementById('teacher'), form = document.getElementById('fixture');
        const tick = () => new Promise(resolve => setTimeout(resolve, 40));
        const instance = select => $(select).data('select2');
        checks.recordFields = !!instance(teacher) && !!instance(document.getElementById('many'));
        const selection = instance(teacher).$selection[0];
        const reference = document.getElementById('sizing-reference');
        checks.inputHeight = Math.abs(selection.getBoundingClientRect().height - reference.getBoundingClientRect().height) < 1;
        checks.inputPadding = getComputedStyle(selection).paddingTop === getComputedStyle(reference).paddingTop;
        checks.inputFont = getComputedStyle(selection).fontSize === getComputedStyle(reference).fontSize;
        checks.compactHeight = Math.abs(instance(document.getElementById('compact')).$selection[0].getBoundingClientRect().height - document.getElementById('compact-reference').getBoundingClientRect().height) < 1;
        checks.nativeChoices = !instance(document.getElementById('status')) && !instance(document.getElementById('native')) && !instance(document.getElementById('year'));
        checks.required = !teacher.checkValidity() && instance(teacher).$selection.attr('aria-invalid') === 'true';
        checks.validationFocus = document.activeElement === instance(teacher).$selection[0];
        $(teacher).select2('open');
        const search = document.querySelector('.select2-container--open .select2-search__field');
        search.value = 'B2'; search.dispatchEvent(new Event('input', {bubbles:true}));
        await tick();
        const matches = [...document.querySelectorAll('.select2-results__option--selectable')];
        checks.metadataSearch = matches.length === 1 && matches[0].textContent.includes('Teacher Two');
        const event = $.Event('keydown'); event.which = 13; event.keyCode = 13; $(search).trigger(event);
        await tick();
        checks.keyboardAndNativeChange = teacher.value === '2' && window.nativeChanges === 1;
        checks.formSubmission = new FormData(form).get('teacher_id') === '2';
        teacher.value = '1'; teacher.dispatchEvent(new Event('change', {bubbles:true}));
        checks.programmaticChange = instance(teacher).$selection.text().includes('Teacher One') && window.nativeChanges === 2;
        const teaching = document.getElementById('teaching');
        $(teaching).val('BA').trigger('change');
        instance(teaching).$selection.find('.select2-selection__clear').trigger('mousedown');
        checks.clear = teaching.value === '';
        $(teaching).select2('close');
        $(document.getElementById('many')).val(['1','2']).trigger('change');
        checks.multiple = new FormData(form).getAll('batches_ids[]').join(',') === '1,2';
        checks.disabled = instance(document.getElementById('disabled')).$selection.attr('aria-disabled') === 'true';
        teacher.disabled = true; await tick();
        checks.disabledUpdates = instance(teacher).$selection.attr('aria-disabled') === 'true';
        teacher.disabled = false;
        teacher.insertAdjacentHTML('beforeend', '<option value="4">New Teacher</option>');
        form.insertAdjacentHTML('beforeend', '<select id="dynamic" name="enrollment_id"><option value="9">New enrollment</option></select>');
        await tick();
        checks.dynamicSelect = !!instance(document.getElementById('dynamic'));
        $(teacher).val('4').trigger('change');
        checks.dynamicOptions = instance(teacher).$selection.text().includes('New Teacher');
        checks.singleEnhancement = teacher.parentElement.querySelectorAll('#teacher + .select2-container').length === 1;
        checks.modalParent = instance(document.getElementById('modal')).options.get('dropdownParent')[0].classList.contains('modal');
        const meta = document.getElementById('meta'); $(meta).select2('open');
        checks.escapedMetadata = !document.querySelector('.select2-results img') && document.querySelector('.select2-results').textContent.includes('<img src=x');
        $(meta).select2('close');
        form.reset(); await tick();
        checks.reset = teacher.value === '' && instance(teacher).$selection.text().includes('Choose teacher') && document.getElementById('many').selectedOptions.length === 0;
        checks.labels = instance(teacher).$selection.attr('aria-labelledby').includes('select2-teacher-container');
        return checks;
    })()`);
    for (const [name, passed] of Object.entries(results)) assert.equal(passed, true, name);
    await call('Emulation.setDeviceMetricsOverride', {width:375,height:844,deviceScaleFactor:1,mobile:true});
    const fits = await evaluate(`document.querySelector('#teacher + .select2-container').getBoundingClientRect().width <= document.getElementById('fixture').getBoundingClientRect().width + 1`);
    assert.equal(fits, true, 'mobile width');
    console.log(`PASS: ${Object.keys(results).length + 1} isolated Select2 browser checks (${Object.keys(results).join(', ')}, mobile width).`);
} finally {
    socket?.close();
    browser.kill();
}

