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
}
