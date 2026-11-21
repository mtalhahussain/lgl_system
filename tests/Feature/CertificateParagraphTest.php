<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Support\CertificateParagraphs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateParagraphTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_can_set_all_level_paragraphs_once_and_see_them_in_issue_preview(): void
    {
        $this->actingAs($this->admin());
        $response = $this->get(route('settings.certificate-paragraphs.edit'))->assertOk();
        foreach (array_keys(config('certificates.paragraphs')) as $level) {
            $response->assertSee('name="paragraphs['.$level.']"', false);
        }
        $paragraphs = config('certificates.paragraphs');
        $paragraphs['A2'] = "Auf dem A2-Niveau haben Sie Ihre Kenntnisse vertieft.\n Sie können einfache Gespräche führen.";
        $this->from(route('settings.certificate-paragraphs.edit'))
            ->put(route('settings.certificate-paragraphs.update'), ['paragraphs' => $paragraphs])
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.certificate-paragraphs.edit'));
        $this->assertSame(6, Setting::where('group', 'certificates')->count());
        $this->assertSame('Auf dem A2-Niveau haben Sie Ihre Kenntnisse vertieft. Sie können einfache Gespräche führen.', CertificateParagraphs::forLevel('A2'));
        $this->assertSame(1, AuditLog::where('action', 'certificate.paragraphs_updated')->count());
        $this->get('/certificates')->assertOk()->assertSee('id="preview-paragraph"', false)
            ->assertSee('data-paragraphs=', false)->assertViewHas('paragraphs', fn ($saved) => str_contains($saved['A2'], 'einfache Gespräche führen.'));
    }

    public function test_each_level_issues_the_correct_server_selected_paragraph_and_keeps_its_snapshot(): void
    {
        $this->actingAs($this->admin());
        foreach (config('certificates.paragraphs') as $level => $paragraph) {
            $this->post('/certificates', ['student_name' => 'Student '.$level, 'level' => $level,
                'completion_date' => '2026-11-20', 'paragraph_text' => 'Tampered wording'])->assertSessionHasNoErrors()->assertRedirect();
            $certificate = StudentCertificate::where('level', $level)->sole();
            $this->assertSame($paragraph, $certificate->paragraph_text);
            Setting::updateOrCreate(['key' => CertificateParagraphs::key($level)], ['value' => 'Neue Formulierung für '.$level.'.', 'label' => 'Paragraph']);
            $this->assertSame($paragraph, $certificate->fresh()->paragraphText());
            foreach (['certificates.print', 'certificates.pdf'] as $view) {
                $html = view($view, ['cert' => $certificate])->render();
                $this->assertStringContainsString(e($paragraph), $html);
                $this->assertStringNotContainsString('Neue Formulierung', $html);
            }
            $this->post('/certificates', ['student_name' => 'Next '.$level, 'level' => $level,
                'completion_date' => '2026-11-20'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('Neue Formulierung für '.$level.'.', StudentCertificate::where('level', $level)->latest('id')->first()->paragraph_text);
        }
    }

    public function test_old_certificates_keep_the_original_paragraph_after_templates_change(): void
    {
        $admin = $this->admin();
        $certificate = StudentCertificate::create(['student_name' => 'Old student', 'level' => 'B2',
            'completion_date' => '2026-10-01', 'certificate_number' => 'OLD-B2', 'issued_by' => $admin->id, 'issued_at' => now()]);
        Setting::create(['key' => CertificateParagraphs::key('B2'), 'value' => 'New B2 wording.', 'label' => 'B2']);
        $this->actingAs($admin)->get(route('certificates.print', $certificate))->assertOk()
            ->assertSee(CertificateParagraphs::legacy('B2'))->assertDontSee('New B2 wording.');
        $this->assertNull($certificate->fresh()->paragraph_text);
    }

    public function test_editor_validates_all_levels_plain_text_length_and_print_clearance_atomically(): void
    {
        $this->actingAs($this->admin());
        foreach (['', '<b>Text</b>', str_repeat('A', 351)] as $invalid) {
            $paragraphs = config('certificates.paragraphs');
            $paragraphs['A2'] = $invalid;
            $this->put(route('settings.certificate-paragraphs.update'), ['paragraphs' => $paragraphs])
                ->assertSessionHasErrors('paragraphs.A2');
        }
        $paragraphs = config('certificates.paragraphs');
        $paragraphs['A1'] = str_repeat('W', 340);
        $this->put(route('settings.certificate-paragraphs.update'), ['paragraphs' => $paragraphs])->assertSessionHasErrors('paragraphs.A1');
        unset($paragraphs['C2']);
        $this->put(route('settings.certificate-paragraphs.update'), ['paragraphs' => $paragraphs])->assertSessionHasErrors('paragraphs.C2');
        $this->assertSame(0, Setting::where('group', 'certificates')->count());
        $this->assertSame(0, AuditLog::where('action', 'certificate.paragraphs_updated')->count());
    }

    public function test_only_admin_can_read_or_change_templates_even_when_staff_has_certificate_permissions(): void
    {
        foreach (['view', 'create', 'edit'] as $action) {
            RolePermission::create(['role' => 'staff', 'module' => 'certificates', 'action' => $action, 'allowed' => true]);
        }
        foreach (['teacher', 'student', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('settings.certificate-paragraphs.edit'))->assertForbidden();
            $this->put(route('settings.certificate-paragraphs.update'), ['paragraphs' => config('certificates.paragraphs')])->assertForbidden();
            if ($role === 'staff') {
                $this->get('/certificates')->assertOk()->assertDontSee('Level paragraphs');
            }
        }
    }
}
