// Run after npm run build; checks browser CSS delivery without Laravel or a database.
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';

const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
for (const entry of ['app', 'public-application', 'certificate-print']) {
    const file = manifest[`resources/css/${entry}.css`]?.file;
    assert.ok(file, `${entry} must be a CSS manifest entry`);
    const css = await readFile(`public/build/${file}`, 'utf8');
    assert.ok(css.length > 100, `${entry} CSS must be present`);
    assert.ok(!css.includes('@import'), `${entry} imports must be bundled`);
    if (entry === 'app') {
        for (const selector of ['.admission-student-picker', '.admission-student-picker-row',
            '.select2-container--bootstrap-5', '.exam-form-grid', '.certificate-workspace', '.conn-grid']) {
            assert.ok(css.includes(selector), `${selector} must be compiled`);
        }
    }
}

// Retained dormant screens are outside current portal routing; PDFs embed CSS for dompdf.
const exceptions = new Set([
    'admin/attendance/student.blade.php', 'admin/courses/show.blade.php', 'admin/fees/index.blade.php',
    'admin/teachers/create.blade.php', 'admin/teachers/edit.blade.php', 'admin/batches/show.blade.php',
    'admin/students/show.blade.php', 'admin/students/index.blade.php', 'layouts/student.blade.php',
    'layouts/student-nav.blade.php', 'student/fees.blade.php', 'welcome.blade.php',
    'certificates/pdf.blade.php', 'payroll/slip-pdf.blade.php',
]);
let checked = 0;
async function inspect(directory, relative = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        const name = relative + entry.name;
        if (entry.isDirectory()) await inspect(`${directory}/${entry.name}`, `${name}/`);
        else if (name.endsWith('.blade.php') && !exceptions.has(name)) {
            const source = await readFile(`${directory}/${entry.name}`, 'utf8');
            assert.ok(!/<style\b|\sstyle\s*=/i.test(source), `${name}: move browser styles into compiled CSS`);
            checked++;
        }
    }
}
await inspect('resources/views');
console.log(`PASS: 3 compiled CSS entries, module/Select2 selectors, ${checked} browser views without inline styles; ${exceptions.size} explicit PDF/legacy exceptions.`);
