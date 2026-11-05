<?php

namespace Tests\Feature;

use App\Models\StudentCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_row_persists_its_snapshot_fields(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $cert = StudentCertificate::create([
            'student_id' => $student->id,
            'student_name' => 'Syed Saad',
            'father_name' => 'Syed Mehboob',
            'level' => 'A1',
            'completion_date' => '2026-10-27',
            'certificate_number' => 'LGL-A1-2026-001',
            'issued_by' => $admin->id,
            'issued_at' => now(),
        ]);

        $fresh = $cert->fresh();
        $this->assertSame('Syed Saad', $fresh->student_name);
        $this->assertSame('Syed Mehboob', $fresh->father_name);
        $this->assertSame('A1', $fresh->level);
        $this->assertSame('2026-10-27', $fresh->completion_date->format('Y-m-d'));
        $this->assertSame($student->id, $fresh->student->id);
        $this->assertSame($admin->id, $fresh->issuer->id);
    }

    public function test_next_number_is_sequential_per_level_and_year_and_skips_taken(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = ['student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-10-27',
            'issued_by' => $admin->id, 'issued_at' => now()];

        $this->assertSame('LGL-A1-'.now()->year.'-001', StudentCertificate::nextNumber('A1'));

        StudentCertificate::create($base + ['certificate_number' => 'LGL-A1-'.now()->year.'-001']);
        $this->assertSame('LGL-A1-'.now()->year.'-002', StudentCertificate::nextNumber('A1'));

        // A gap/collision on the computed sequence is skipped.
        StudentCertificate::create($base + ['certificate_number' => 'LGL-A1-'.now()->year.'-002']);
        StudentCertificate::create($base + ['level' => 'A2', 'certificate_number' => 'LGL-A1-'.now()->year.'-003']);
        $this->assertSame('LGL-A1-'.now()->year.'-004', StudentCertificate::nextNumber('A1'));

        // A different level starts its own sequence. Numbering is by the
        // certificate_number prefix, and no LGL-A2-... number exists yet, so A2 starts at 001.
        $this->assertSame('LGL-A2-'.now()->year.'-001', StudentCertificate::nextNumber('A2'));
    }
}
