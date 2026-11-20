<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\StudentCertificate;
use App\Models\TeacherDue;
use App\Models\TeacherPayout;
use App\Models\User;
use App\Support\PdfImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Setting::create(['key' => 'document_logo', 'value' => $path, 'label' => 'Document Logo']);
        Setting::create(['key' => 'institute_phone', 'value' => '+923001234567', 'label' => 'Institute Phone']);
        [$admin, $teacher] = $this->teacherAndAdmin();
        $payout = $this->payout($admin, $teacher)->load(['due', 'reversal']);

        $html = view('payroll.slip-pdf', ['payout' => $payout])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('+923001234567', $html);
        $this->assertStringContainsString('Received by', $html);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $cert = $this->certificate($student);
        foreach (['certificates.pdf', 'certificates.print'] as $view) {
            $certificateHtml = view($view, ['cert' => $cert])->render();
            $this->assertStringContainsString('class="certificate-logo"', $certificateHtml);
            $this->assertStringContainsString(\App\Support\PdfImage::instituteLogo(), $certificateHtml);
        }
        $this->actingAs($admin)->get(route('certificates.pdf', $cert))->assertOk();
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
}
