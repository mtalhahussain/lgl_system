<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchExam;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentCertificate;
use App\Models\StudentExamResult;
use App\Models\User;
use App\Services\ExamGradingService;
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

    public function test_admin_issues_a_certificate_and_is_redirected_to_print(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);

        $response = $this->actingAs($admin)->post('/certificates', [
            'student_id' => $student->id,
            'student_name' => 'Sara Khan',
            'father_name' => 'Imran Khan',
            'level' => 'A1',
            'completion_date' => '2026-10-27',
        ]);

        $cert = StudentCertificate::sole();
        $response->assertRedirect('/certificates/'.$cert->id.'/print');
        $this->assertSame('Sara Khan', $cert->student_name);
        $this->assertSame('Imran Khan', $cert->father_name);
        $this->assertSame('A1', $cert->level);
        $this->assertSame($admin->id, $cert->issued_by);
        $this->assertSame('LGL-A1-'.now()->year.'-001', $cert->certificate_number);
        $this->assertSame(1, AuditLog::where('action', 'certificate.issued')
            ->where('subject_type', 'student_certificate')->where('subject_id', $cert->id)->count());
    }

    public function test_issue_form_prefills_level_from_the_students_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);
        $course = Course::create(['name' => 'German B1', 'level' => 'B1', 'total_fee' => 30000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $batch = Batch::create(['name' => 'B1 Morning', 'course_id' => $course->id, 'teacher_id' => $admin->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
        Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);

        $this->actingAs($admin)->get('/certificates?student='.$student->id)
            ->assertOk()->assertSee('Sara')->assertSee('B1');
    }

    public function test_store_validates_level_and_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post('/certificates', [
            'student_id' => $student->id, 'student_name' => '', 'level' => 'Z9', 'completion_date' => 'nope',
        ])->assertSessionHasErrors(['student_name', 'level', 'completion_date']);

        $this->assertSame(0, StudentCertificate::count());
    }

    public function test_snapshot_survives_a_later_student_name_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Old Name']);

        $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
            'student_name' => 'Old Name', 'father_name' => 'Dad', 'level' => 'A1', 'completion_date' => '2026-10-27']);
        $student->update(['name' => 'Renamed']);

        $this->assertSame('Old Name', StudentCertificate::sole()->student_name);
    }

    public function test_non_admins_cannot_issue_or_view_certificates(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($teacher)->get('/certificates')->assertForbidden();
        $this->actingAs($student)->get('/certificates')->assertForbidden();
        $this->actingAs($teacher)->post('/certificates', ['student_id' => $student->id,
            'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-10-27'])->assertForbidden();
    }

    public function test_non_admins_cannot_open_or_reprint_a_certificate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $cert = StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara',
            'father_name' => 'Imran', 'level' => 'A1', 'completion_date' => '2026-10-27',
            'certificate_number' => 'LGL-A1-2026-050', 'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($teacher)->get('/certificates/'.$cert->id.'/print')->assertForbidden();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $this->actingAs($otherStudent)->get('/certificates/'.$cert->id.'/print')->assertForbidden();
        $this->actingAs($admin)->get('/certificates/'.$cert->id.'/print')->assertOk();
    }

    public function test_index_shows_the_form_and_the_issued_list_with_reprint_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);
        $cert = StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara Khan',
            'father_name' => 'Imran', 'level' => 'A1', 'completion_date' => '2026-10-27',
            'certificate_number' => 'LGL-A1-2026-007', 'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($admin)->get('/certificates')->assertOk()
            ->assertSee('Issue Certificate')
            ->assertSee('LGL-A1-2026-007')
            ->assertSee('Sara Khan')
            ->assertSee(route('certificates.print', $cert), false);
    }

    public function test_print_view_renders_the_snapshot_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cert = StudentCertificate::create(['student_id' => null, 'student_name' => 'Syed Saad',
            'father_name' => 'Syed Mehboob', 'level' => 'A1', 'completion_date' => '2026-10-27',
            'certificate_number' => 'LGL-A1-2026-009', 'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($admin)->get('/certificates/'.$cert->id.'/print')->assertOk()
            ->assertSee('Teilnahmebescheinigung')
            ->assertSee('Syed Saad')
            ->assertSee('Syed Mehboob')
            ->assertSee('Niveau A1')
            ->assertSee('27.10.2026');
    }

    private function passingResult(User $admin, User $student, string $level = 'A1'): StudentExamResult
    {
        $course = Course::create(['name' => 'German '.$level, 'level' => $level, 'total_fee' => 30000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $batch = Batch::create(['name' => $level.' Morning', 'course_id' => $course->id, 'teacher_id' => $admin->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
        Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);
        $exam = BatchExam::create(['batch_id' => $batch->id, 'name' => $level.' Final', 'type' => 'final',
            'exam_date' => '2026-12-20', 'status' => 'scheduled']);
        $exam->seedDefaultSections(['Hören' => ['max' => 25, 'pass' => 15], 'Lesen' => ['max' => 25, 'pass' => 15],
            'Schreiben' => ['max' => 25, 'pass' => 15], 'Sprechen' => ['max' => 25, 'pass' => 15]]);
        $marks = $exam->load('sections')->sections->mapWithKeys(fn ($s) => [$s->id => 20])->all();

        return app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $marks, $admin);
    }

    public function test_issuing_from_a_passing_result_links_and_prefills_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);
        $result = $this->passingResult($admin, $student, 'B1');

        $response = $this->actingAs($admin)->post('/certificates', [
            'student_id' => $student->id, 'student_exam_result_id' => $result->id,
            'student_name' => 'Sara Khan', 'father_name' => 'Imran', 'level' => 'B1', 'completion_date' => '2026-12-20',
        ]);

        $cert = StudentCertificate::sole();
        $response->assertRedirect('/certificates/'.$cert->id.'/print');
        $this->assertSame($result->id, $cert->student_exam_result_id);
        $this->assertSame('B1', $cert->level);
    }

    public function test_cannot_issue_a_second_active_certificate_for_same_student_and_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);

        StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-001',
            'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
            'student_name' => 'Sara', 'level' => 'A1', 'completion_date' => '2026-10-02'])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(1, StudentCertificate::count());
    }

    public function test_a_revoked_certificate_does_not_block_reissue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Sara']);

        StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-001',
            'issued_by' => $admin->id, 'issued_at' => now(), 'revoked_at' => now(),
            'revoked_by' => $admin->id, 'revoke_reason' => 'error']);

        $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
            'student_name' => 'Sara', 'level' => 'A1', 'completion_date' => '2026-10-02'])->assertRedirect();

        $this->assertSame(2, StudentCertificate::count());
    }

    public function test_owning_student_can_view_their_certificate_but_not_others(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'student']);
        $stranger = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $cert = StudentCertificate::create(['student_id' => $owner->id, 'student_name' => 'Owner', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-777',
            'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($owner)->get('/certificates/'.$cert->id.'/print')->assertOk();
        $this->actingAs($stranger)->get('/certificates/'.$cert->id.'/print')->assertForbidden();
        $this->actingAs($teacher)->get('/certificates/'.$cert->id.'/print')->assertForbidden();
        $this->actingAs($admin)->get('/certificates/'.$cert->id.'/print')->assertOk();
    }

    public function test_student_profile_shows_issue_certificate_shortcut_for_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->get('/students/'.$student->id)
            ->assertOk()
            ->assertSee('Issue Certificate')
            ->assertSee(route('certificates.index', ['student' => $student->id]), false);

        $teacherViewer = User::factory()->create(['role' => 'teacher']);
        // Enroll the student in the teacher's batch so the teacher may view the profile.
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $batch = Batch::create(['name' => 'A1', 'course_id' => $course->id, 'teacher_id' => $teacherViewer->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
        Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);

        $this->actingAs($teacherViewer)->get('/students/'.$student->id)
            ->assertOk()->assertDontSee('Issue Certificate');
    }

    public function test_admin_revokes_a_certificate_with_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $cert = StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Sara', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-001',
            'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'Marks corrected to a fail.'])
            ->assertRedirect();

        $cert->refresh();
        $this->assertTrue($cert->isRevoked());
        $this->assertSame($admin->id, $cert->revoked_by);
        $this->assertSame('Marks corrected to a fail.', $cert->revoke_reason);
        $this->assertSame(1, AuditLog::where('action', 'certificate.revoked')
            ->where('subject_id', $cert->id)->count());
    }

    public function test_revoke_requires_a_reason_and_rejects_double_revoke(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cert = StudentCertificate::create(['student_name' => 'Sara', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-002',
            'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'no'])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($cert->fresh()->isRevoked());

        $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'First valid revoke reason.'])
            ->assertRedirect();

        $this->actingAs($admin)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'A different second reason.'])
            ->assertSessionHasErrors('reason');

        $cert->refresh();
        $this->assertSame('First valid revoke reason.', $cert->revoke_reason);
        $this->assertSame(1, AuditLog::where('action', 'certificate.revoked')->where('subject_id', $cert->id)->count());
    }

    public function test_non_admins_cannot_revoke(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $cert = StudentCertificate::create(['student_id' => $student->id, 'student_name' => 'Owner', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-010',
            'issued_by' => $admin->id, 'issued_at' => now()]);

        $this->actingAs($teacher)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'A valid reason here.'])->assertForbidden();
        $this->actingAs($student)->post('/certificates/'.$cert->id.'/revoke', ['reason' => 'A valid reason here.'])->assertForbidden();
        $this->assertFalse($cert->fresh()->isRevoked());
    }

    public function test_issue_requires_a_student_when_a_result_is_linked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $result = $this->passingResult($admin, $student, 'A1');

        $this->actingAs($admin)->post('/certificates', ['student_exam_result_id' => $result->id,
            'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-12-20'])
            ->assertSessionHasErrors('student_id');
        $this->assertSame(0, StudentCertificate::count());
    }

    public function test_revoked_certificate_print_shows_the_revoked_stamp(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cert = StudentCertificate::create(['student_name' => 'Sara', 'level' => 'A1',
            'completion_date' => '2026-10-01', 'certificate_number' => 'LGL-A1-'.now()->year.'-003',
            'issued_by' => $admin->id, 'issued_at' => now(), 'revoked_at' => now(),
            'revoked_by' => $admin->id, 'revoke_reason' => 'Issued in error.']);

        $this->actingAs($admin)->get('/certificates/'.$cert->id.'/print')->assertOk()
            ->assertSee('REVOKED')->assertSee('Issued in error.');
    }

    public function test_issue_rejects_a_failing_exam_result(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $result = $this->passingResult($admin, $student, 'A1');
        $result->sectionMarks()->update(['marks_obtained' => 5, 'passed' => false]);
        $result->update(['overall_passed' => false]);

        $this->actingAs($admin)->post('/certificates', ['student_id' => $student->id,
            'student_exam_result_id' => $result->id, 'student_name' => 'X', 'level' => 'A1',
            'completion_date' => '2026-12-20'])->assertSessionHasErrors('student_exam_result_id');
        $this->assertSame(0, StudentCertificate::count());
    }

    public function test_issue_rejects_a_result_belonging_to_another_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'student']);
        $someoneElse = User::factory()->create(['role' => 'student']);
        $result = $this->passingResult($admin, $owner, 'A1');

        $this->actingAs($admin)->post('/certificates', ['student_id' => $someoneElse->id,
            'student_exam_result_id' => $result->id, 'student_name' => 'X', 'level' => 'A1',
            'completion_date' => '2026-12-20'])->assertSessionHasErrors('student_exam_result_id');
        $this->assertSame(0, StudentCertificate::count());
    }
}
