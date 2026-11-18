# Certificate and Pay Slip PDFs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Real PDF print/download for student certificates and teacher pay slips; pay slip shows the Settings logo.

**Architecture:** `barryvdh/laravel-dompdf` renders dedicated dompdf-safe Blade views (`certificates/pdf`, `payroll/slip-pdf`). Two new GET routes reuse the existing authorization of the HTML print/slip pages. Images (certificate template, logo) are embedded as base64 via a tiny `PdfImage` helper; remote loading stays off.

**Tech Stack:** Laravel 12, PHP 8.2+, barryvdh/laravel-dompdf ^3, PHPUnit on SQLite `:memory:`.

Spec: `docs/superpowers/specs/2026-11-18-pdf-certificate-payslip-design.md` (task D02).

## Project rules

- Branch `feat/pdf-certificate-payslip`. One writer. Tests only on isolated SQLite (phpunit.xml forces it). Preserve unrelated changes. Don't commit unless the controller says so.
- `docs/TASKS.md` reservation/evidence is handled by the controller, not implementers.

## Facts

- `StudentCertificate`: `student_id, student_name, father_name, level, completion_date (date), certificate_number, issued_by, issued_at, revoked_at, revoke_reason`; `isRevoked()`. Route model binding name `{certificate}`.
- `CertificateController::print(StudentCertificate $certificate)`: `abort_if($user->isStudent() && (int)$certificate->student_id !== (int)$user->id, 403)`; route middleware `role:admin,student`.
- `TeacherPayout`: `amount (decimal:2), paid_at (datetime), voucher_number, method, reference, slip_details (array: teacher_name, teacher_reference, description, due_amount, institute_name, currency_code)`, relations `due`, `reversal` (`TeacherPayoutReversal` with `reason`). Immutable (updating throws).
- `PayrollController::slip()` loads `['due','reversal']` and calls private `authorizeDue($user, $due)` (admin, or teacher owning the due). Route middleware `role:admin,teacher`. Payment method labels: `\App\Services\PayrollService::METHODS`.
- Settings: `setting($key)` / `Setting::set($key, $value)`; logo stored as a path on the `public` disk (`settings/xxx.png`); keys `institute_address`, `institute_phone`, `institute_email`; helper `institute_name()`.
- Certificate template file: `public_path('template.png')`.
- PayrollTest fixtures: admin + teacher with `salary_type => 'monthly', monthly_salary => 50000`; `POST /payroll/salaries` with `['period' => now()->format('Y-m')]` creates a `TeacherDue`; `POST /payroll/dues/{due}/payouts` with `['amount'=>'20000','method'=>'bank','reference'=>'TX-1','submission_key'=>'payout-key-0000000001']` creates a payout; `POST /payroll/payouts/{payout}/reverse` with `['reason'=>'Wrong bank account']` reverses.

---

### Task 1: Install dompdf and PdfImage helper

**Files:**
- Modify: `composer.json`, `composer.lock` (via composer)
- Create: `app/Support/PdfImage.php`
- Test: `tests/Feature/PdfDocumentsTest.php`

- [ ] **Step 1:** `composer require barryvdh/laravel-dompdf:^3.1` — expect success. Package auto-discovers; no config publish needed.

- [ ] **Step 2: Failing test** — create `tests/Feature/PdfDocumentsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\PdfImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_image_data_uri(): void
    {
        $this->assertNull(PdfImage::dataUri(null));
        $this->assertNull(PdfImage::dataUri(base_path('does-not-exist.png')));
        $png = tempnam(sys_get_temp_dir(), 'img').'.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->assertStringStartsWith('data:image/png;base64,', PdfImage::dataUri($png));
        @unlink($png);
    }
}
```

- [ ] **Step 3:** `php artisan test --filter=PdfDocumentsTest` → FAIL (class not found).

- [ ] **Step 4: Implement** `app/Support/PdfImage.php`:

```php
<?php

namespace App\Support;

/** Embed local images in dompdf output (remote loading stays disabled). */
class PdfImage
{
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public static function dataUri(?string $absolutePath): ?string
    {
        if (! $absolutePath || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }
        $mime = self::MIME[strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))] ?? null;

        return $mime ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absolutePath)) : null;
    }

    /** Institute logo from Settings (public disk), or null. */
    public static function instituteLogo(): ?string
    {
        $path = setting('institute_logo');

        return $path ? self::dataUri(\Illuminate\Support\Facades\Storage::disk('public')->path($path)) : null;
    }
}
```

- [ ] **Step 5:** run filter → PASS.

---

### Task 2: Certificate PDF

**Files:**
- Modify: `app/Http/Controllers/CertificateController.php` (add `pdf`), `routes/web.php` (after `certificates.print` route), `resources/views/certificates/print.blade.php` (toolbar), `resources/views/certificates/index.blade.php:112`, `resources/views/exams/show.blade.php:49`, `resources/views/exams/student-results.blade.php:53` (leave links to print page — no change needed unless trivial).
- Create: `resources/views/certificates/pdf.blade.php`
- Test: `tests/Feature/PdfDocumentsTest.php`

- [ ] **Step 1: Failing tests** (append; add `use App\Models\StudentCertificate; use App\Models\User;`):

```php
    private function certificate(User $student, array $extra = []): StudentCertificate
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        return StudentCertificate::create(array_merge([
            'student_id' => $student->id, 'student_name' => $student->name, 'father_name' => 'Father', 'level' => 'A1',
            'completion_date' => '2026-11-10', 'certificate_number' => 'LGL-A1-2026-001', 'issued_by' => $admin->id, 'issued_at' => now(),
        ], $extra));
    }

    public function test_certificate_pdf_inline_and_download_for_admin(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $cert = $this->certificate($student);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $inline = $this->actingAs($admin)->get(route('certificates.pdf', $cert))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $inline->getContent());
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));

        $download = $this->get(route('certificates.pdf', [$cert, 'download' => 1]))->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('certificate-LGL-A1-2026-001.pdf', $download->headers->get('Content-Disposition'));
    }

    public function test_certificate_pdf_access(): void
    {
        $owner = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $cert = $this->certificate($owner);

        $this->actingAs($owner)->get(route('certificates.pdf', $cert))->assertOk();
        $this->actingAs($other)->get(route('certificates.pdf', $cert))->assertForbidden();
        $this->actingAs($teacher)->get(route('certificates.pdf', $cert))->assertForbidden();
    }

    public function test_revoked_certificate_pdf_view_shows_stamp(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $cert = $this->certificate($student, ['revoked_at' => now(), 'revoke_reason' => 'Issued in error']);

        $html = view('certificates.pdf', ['cert' => $cert])->render();

        $this->assertStringContainsString('REVOKED', $html);
        $this->assertStringContainsString('Issued in error', $html);
    }
```

- [ ] **Step 2:** run → FAIL (route `certificates.pdf` not defined).

- [ ] **Step 3: Route** in `routes/web.php` right after the `certificates.print` route:

```php
    Route::get('/certificates/{certificate}/pdf', [CertificateController::class, 'pdf'])
        ->name('certificates.pdf')->middleware('role:admin,student');
```

- [ ] **Step 4: Controller** — in `CertificateController` add (import `Barryvdh\DomPDF\Facade\Pdf` and `Illuminate\Http\Request` if missing):

```php
    public function pdf(Request $request, StudentCertificate $certificate)
    {
        $user = auth()->user();
        abort_if($user->isStudent() && (int) $certificate->student_id !== (int) $user->id, 403);

        $pdf = Pdf::loadView('certificates.pdf', ['cert' => $certificate])->setPaper('a4', 'landscape');
        $name = 'certificate-'.$certificate->certificate_number.'.pdf';

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }
```

- [ ] **Step 5: View** `resources/views/certificates/pdf.blade.php` (dompdf-safe: no flexbox, image as `<img>`):

```blade
@php
    $templateSrc = \App\Support\PdfImage::dataUri(public_path('template.png'));
    if (! $templateSrc) {
        \Illuminate\Support\Facades\Log::warning('Certificate template image missing; rendering on a plain background.', ['path' => public_path('template.png')]);
    }
    $soLine = trim($cert->student_name.($cert->father_name ? ' S/O '.$cert->father_name : ''));
    $dateDe = $cert->completion_date->format('d.m.Y');
@endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Certificate {{ $cert->certificate_number }}</title>
<style>
    @page { size: A4 landscape; margin: 0; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'DejaVu Serif', Georgia, serif; color: #1a1a1a; }
    .sheet { position: relative; width: 297mm; height: 210mm; overflow: hidden; }
    .bg { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
    .cert-body { position: absolute; top: 46mm; left: 120mm; width: 159mm; }
    .cert-title { font-size: 30pt; font-weight: bold; margin: 0 0 10mm; }
    .cert-grats { font-size: 15pt; font-style: italic; margin: 0 0 2mm; }
    .cert-name { color: #c0202a; font-weight: bold; font-style: italic; }
    .cert-line { font-size: 14pt; margin: 0 0 1mm; }
    .cert-course { font-size: 15pt; font-weight: bold; margin: 0 0 8mm; }
    .cert-date { font-size: 13pt; margin: 0 0 8mm; }
    .cert-text { font-size: 11.5pt; line-height: 1.5; width: 150mm; margin: 0; }
    .cert-number { position: absolute; bottom: 6mm; right: 10mm; font-size: 8pt; color: #888; }
    .revoked-stamp { position: absolute; top: 80mm; left: 70mm; width: 157mm; text-align: center; border: 6px solid #c0202a; color: #c0202a; font-size: 54pt; font-weight: bold; letter-spacing: 6pt; padding: 6mm 0; transform: rotate(-18deg); opacity: .75; }
    .revoked-reason { position: absolute; bottom: 3mm; left: 10mm; font-size: 8pt; color: #c0202a; }
</style>
</head>
<body>
<div class="sheet">
    @if($templateSrc)<img class="bg" src="{{ $templateSrc }}" alt="">@endif
    <div class="cert-body">
        <h1 class="cert-title">Teilnahmebescheinigung</h1>
        <p class="cert-grats">Herzlichen Glückwunsch, <span class="cert-name">{{ $soLine }}</span></p>
        <p class="cert-line">zum erfolgreichen Abschluss des</p>
        <p class="cert-course">Deutsch-Sprachkurses &ndash; Niveau {{ $cert->level }}</p>
        <p class="cert-date">Kurs abgeschlossen am <strong>{{ $dateDe }}</strong></p>
        <p class="cert-text">
            Ihr Einsatz beim Erlernen der deutschen Sprache war lobenswert. Auf dem
            {{ $cert->level }}-Niveau haben Sie grundlegende sprachliche Fähigkeiten erworben
            und erste einfache Kommunikationssituationen sicher gemeistert.
        </p>
    </div>
    <div class="cert-number">{{ $cert->certificate_number }}</div>
    @if($cert->isRevoked())
        <div class="revoked-stamp">REVOKED</div>
        <div class="revoked-reason">Revoked: {{ $cert->revoke_reason }}</div>
    @endif
</div>
</body>
</html>
```

- [ ] **Step 6: HTML toolbar** — in `resources/views/certificates/print.blade.php` replace

```blade
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
```

with

```blade
        <a href="{{ route('certificates.pdf', [$cert, 'download' => 1]) }}" style="font:inherit; padding:8px 18px; margin:0 4px; border:1px solid #888; color:#1a1a1a; text-decoration:none;">Download PDF</a>
        <a href="{{ route('certificates.pdf', $cert) }}" target="_blank" rel="noopener" style="font:inherit; padding:8px 18px; margin:0 4px; border:1px solid #888; color:#1a1a1a; text-decoration:none;">Print PDF</a>
```

- [ ] **Step 7:** `php artisan test --filter="PdfDocumentsTest|CertificateTest"` → all pass. Then render the PDF of a sample certificate once and eyeball layout if possible (save to scratch, not repo): optional.

---

### Task 3: Pay slip PDF with logo

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (add `slipPdf`), `routes/web.php` (after `payroll.slip`), `resources/views/payroll/slip.blade.php` (buttons), `resources/views/payroll/show.blade.php:79` (PDF icon)
- Create: `resources/views/payroll/slip-pdf.blade.php`
- Test: `tests/Feature/PdfDocumentsTest.php`

- [ ] **Step 1: Failing tests** (append; imports `App\Models\Setting`, `App\Models\TeacherDue`, `App\Models\TeacherPayout`, `Illuminate\Http\UploadedFile`, `Illuminate\Support\Facades\Storage`):

```php
    private function payout(User $admin, User $teacher): TeacherPayout
    {
        $this->actingAs($admin)->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $due = TeacherDue::where('teacher_id', $teacher->id)->sole();
        $this->post("/payroll/dues/{$due->id}/payouts", ['amount' => '20000', 'method' => 'bank', 'reference' => 'TX-1', 'submission_key' => 'payout-key-0000000001'])->assertRedirect();

        return TeacherPayout::sole();
    }

    private function teacherAndAdmin(): array
    {
        return [
            User::factory()->create(['role' => 'admin', 'is_active' => true]),
            User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => 50000]),
        ];
    }

    public function test_payslip_pdf_inline_download_and_access(): void
    {
        [$admin, $teacher] = $this->teacherAndAdmin();
        $payout = $this->payout($admin, $teacher);

        $inline = $this->actingAs($admin)->get(route('payroll.slip.pdf', $payout))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $inline->getContent());
        $download = $this->get(route('payroll.slip.pdf', [$payout, 'download' => 1]))->assertOk();
        $this->assertStringContainsString('payslip-'.$payout->voucher_number.'.pdf', $download->headers->get('Content-Disposition'));

        $this->actingAs($teacher)->get(route('payroll.slip.pdf', $payout))->assertOk();
        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->get(route('payroll.slip.pdf', $payout))->assertForbidden();
    }

    public function test_payslip_shows_logo_and_contact_from_settings(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('logo.png', 40, 40)->store('settings', 'public');
        Setting::set('institute_logo', $path);
        Setting::set('institute_phone', '+923001234567');
        [$admin, $teacher] = $this->teacherAndAdmin();
        $payout = $this->payout($admin, $teacher)->load(['due', 'reversal']);

        $html = view('payroll.slip-pdf', ['payout' => $payout])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('+923001234567', $html);
        $this->assertStringContainsString('Received by', $html);
        $this->actingAs($admin)->get(route('payroll.slip.pdf', $payout))->assertOk();
    }

    public function test_payslip_without_logo_and_reversed_stamp(): void
    {
        [$admin, $teacher] = $this->teacherAndAdmin();
        $payout = $this->payout($admin, $teacher);
        $this->post("/payroll/payouts/{$payout->id}/reverse", ['reason' => 'Wrong bank account']);
        $payout = $payout->fresh()->load(['due', 'reversal']);

        $html = view('payroll.slip-pdf', ['payout' => $payout])->render();

        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringContainsString('REVERSED', $html);
        $this->assertStringContainsString('Wrong bank account', $html);
        $this->get(route('payroll.slip.pdf', $payout))->assertOk();
    }
```

(If `Setting::set` caches, the setting values are read fresh in the same request; if a cache layer interferes, clear it with `Cache::flush()` after `Setting::set`.)

- [ ] **Step 2:** run → FAIL (route not defined).

- [ ] **Step 3: Route** after the `payroll.slip` route:

```php
    Route::get('/payroll/payouts/{payout}/pdf', [PayrollController::class, 'slipPdf'])
        ->name('payroll.slip.pdf')->middleware('role:admin,teacher');
```

- [ ] **Step 4: Controller** — in `PayrollController` (import `Barryvdh\DomPDF\Facade\Pdf`):

```php
    public function slipPdf(Request $request, TeacherPayout $payout)
    {
        $payout->load(['due', 'reversal']);
        $this->authorizeDue($request->user(), $payout->due);

        $pdf = Pdf::loadView('payroll.slip-pdf', compact('payout'))->setPaper('a5', 'portrait');
        $name = 'payslip-'.$payout->voucher_number.'.pdf';

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }
```

- [ ] **Step 5: View** `resources/views/payroll/slip-pdf.blade.php` (tables only, no flexbox):

```blade
@php
    $d = $payout->slip_details ?? [];
    $currency = $d['currency_code'] ?? 'PKR';
    $logo = \App\Support\PdfImage::instituteLogo();
    $institute = $d['institute_name'] ?? institute_name();
    $contact = array_filter([setting('institute_address'), setting('institute_phone'), setting('institute_email')]);
    $teacher = ($d['teacher_name'] ?? 'Historical details unavailable').(! empty($d['teacher_reference']) ? ' ('.$d['teacher_reference'].')' : '');
    $rows = array_filter([
        'Paid to' => $teacher,
        'For' => $d['description'] ?? null,
        'Due amount' => isset($d['due_amount']) ? $currency.' '.number_format((float) $d['due_amount'], 2) : null,
        'Method' => \App\Services\PayrollService::METHODS[$payout->method] ?? $payout->method,
        'Reference' => $payout->reference,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pay slip {{ $payout->voucher_number }}</title>
<style>
    @page { size: A5 portrait; margin: 12mm; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5pt; color: #1f2937; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: middle; }
    .logo { width: 22mm; padding-right: 4mm; }
    .logo img { max-width: 22mm; max-height: 22mm; }
    .inst { font-size: 13pt; font-weight: bold; color: #0f2a4a; }
    .contact { font-size: 8pt; color: #6b7280; margin-top: 1mm; }
    .rule { border-bottom: 2px solid #0f2a4a; margin: 4mm 0 5mm; }
    .title { font-size: 12pt; font-weight: bold; letter-spacing: .5pt; text-transform: uppercase; }
    .meta { font-size: 8.5pt; color: #6b7280; }
    .amount { border: 1px solid #0f2a4a; padding: 2mm 3mm; text-align: right; }
    .amount small { display: block; font-size: 7.5pt; color: #6b7280; }
    .amount strong { font-size: 12pt; }
    .details { margin-top: 6mm; }
    .details td { padding: 2mm 0; border-bottom: 1px solid #e5e7eb; }
    .details td.label { width: 32mm; color: #6b7280; }
    .stamp { margin-top: 6mm; border: 3px solid #c0202a; color: #c0202a; text-align: center; font-size: 20pt; font-weight: bold; letter-spacing: 4pt; padding: 2mm; }
    .stamp-reason { color: #c0202a; font-size: 8.5pt; margin-top: 1mm; }
    .signs { margin-top: 18mm; }
    .signs td { width: 50%; padding-top: 1mm; font-size: 8.5pt; color: #6b7280; }
    .signs .line { border-top: 1px solid #1f2937; width: 45mm; padding-top: 1mm; }
</style>
</head>
<body>
    <table class="head"><tr>
        @if($logo)<td class="logo"><img src="{{ $logo }}" alt="Logo"></td>@endif
        <td>
            <div class="inst">{{ $institute }}</div>
            @if($contact)<div class="contact">{{ implode(' · ', $contact) }}</div>@endif
        </td>
    </tr></table>
    <div class="rule"></div>

    <table><tr>
        <td>
            <div class="title">Salary Payment Slip</div>
            <div class="meta">Voucher {{ $payout->voucher_number }}</div>
            <div class="meta">{{ $payout->paid_at->format('d M Y, h:i A') }}</div>
        </td>
        <td style="width: 45mm;">
            <div class="amount"><small>Amount</small><strong>{{ $currency }} {{ number_format((float) $payout->amount, 2) }}</strong></div>
        </td>
    </tr></table>

    <table class="details">
        @foreach($rows as $label => $value)
            <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    @if($payout->reversal)
        <div class="stamp">REVERSED</div>
        <div class="stamp-reason">Reason: {{ $payout->reversal->reason }}</div>
    @endif

    <table class="signs"><tr>
        <td><div class="line">Received by</div></td>
        <td><div class="line">Authorised by</div></td>
    </tr></table>
</body>
</html>
```

- [ ] **Step 6: HTML slip buttons** — in `resources/views/payroll/slip.blade.php` replace

```blade
        <button class="btn btn-primary ms-auto" type="button" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print slip</button>
```

with

```blade
        <a class="btn btn-outline-primary ms-auto" href="{{ route('payroll.slip.pdf', [$payout, 'download' => 1]) }}"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i>Download PDF</a>
        <a class="btn btn-primary" href="{{ route('payroll.slip.pdf', $payout) }}" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Print PDF</a>
```

- [ ] **Step 7: Payroll due page icon** — in `resources/views/payroll/show.blade.php` line ~79, inside the same `<td class="text-end">` after the existing slip link, add:

```blade
<a class="btn btn-icon" href="{{ route('payroll.slip.pdf', [$payout, 'download' => 1]) }}" aria-label="Download PDF {{ $payout->voucher_number }}"><i class="fas fa-file-pdf" aria-hidden="true"></i></a>
```

- [ ] **Step 8:** `php artisan test --filter="PdfDocumentsTest|PayrollTest"` → all pass.

---

### Task 4: Verification and evidence (controller)

- [ ] `php -l` on changed PHP; `git diff --check`.
- [ ] `php artisan test` full suite: only the 2 known pre-existing failures allowed.
- [ ] Render one certificate and one slip PDF to the scratchpad and visually check (Read the PDF).
- [ ] Record D02 evidence in `docs/TASKS.md`; release reservation.
