<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchExam;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentCertificate;
use App\Models\StudentExamResult;
use App\Models\StudentExamSectionMark;
use App\Models\User;
use App\Services\ExamGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BatchExamTest extends TestCase
{
    use RefreshDatabase;

    private function batch(?User $teacher = null): Batch
    {
        $teacher ??= User::factory()->create(['role' => 'teacher']);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);

        return Batch::create(['name' => 'A1 Morning', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
    }

    public function test_exam_seeds_four_named_sections_with_given_marks(): void
    {
        $batch = $this->batch();
        $exam = BatchExam::create(['batch_id' => $batch->id, 'name' => 'A1 Final',
            'type' => 'final', 'exam_date' => '2026-12-20', 'status' => 'scheduled']);

        $exam->seedDefaultSections([
            'Hören' => ['max' => 25, 'pass' => 15],
            'Lesen' => ['max' => 25, 'pass' => 15],
            'Schreiben' => ['max' => 25, 'pass' => 15],
            'Sprechen' => ['max' => 25, 'pass' => 15],
        ]);

        $sections = $exam->sections()->orderBy('sort')->get();
        $this->assertSame(['Hören', 'Lesen', 'Schreiben', 'Sprechen'], $sections->pluck('name')->all());
        $this->assertSame('25.00', $sections->first()->max_marks);
        $this->assertSame('15.00', $sections->first()->passing_marks);
        $this->assertSame($batch->id, $exam->batch->id);
    }

    private function examWithSections(Batch $batch): BatchExam
    {
        $exam = BatchExam::create(['batch_id' => $batch->id, 'name' => 'A1 Final',
            'type' => 'final', 'exam_date' => '2026-12-20', 'status' => 'scheduled']);
        $exam->seedDefaultSections([
            'Hören' => ['max' => 25, 'pass' => 15], 'Lesen' => ['max' => 25, 'pass' => 15],
            'Schreiben' => ['max' => 25, 'pass' => 15], 'Sprechen' => ['max' => 25, 'pass' => 15],
        ]);

        return $exam->load('sections');
    }

    public function test_overall_pass_requires_every_section_passed(): void
    {
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);

        $result = StudentExamResult::create(['batch_exam_id' => $exam->id, 'student_id' => $student->id,
            'overall_passed' => false, 'graded_by' => null, 'graded_at' => now()]);

        foreach ($exam->sections as $i => $section) {
            $marks = $i === 0 ? 10 : 20;
            $result->sectionMarks()->create(['exam_section_id' => $section->id,
                'marks_obtained' => $marks, 'passed' => $marks >= (float) $section->passing_marks]);
        }

        $this->assertFalse($result->fresh()->computeOverallPassed());

        $result->sectionMarks()->where('exam_section_id', $exam->sections->first()->id)
            ->update(['marks_obtained' => 18, 'passed' => true]);
        $this->assertTrue($result->fresh()->load('sectionMarks', 'batchExam.sections')->computeOverallPassed());
    }

    public function test_overall_pass_is_false_when_a_section_mark_is_missing(): void
    {
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);

        $result = StudentExamResult::create(['batch_exam_id' => $exam->id, 'student_id' => $student->id,
            'overall_passed' => false, 'graded_at' => now()]);
        foreach ($exam->sections->take(3) as $section) {
            $result->sectionMarks()->create(['exam_section_id' => $section->id, 'marks_obtained' => 20, 'passed' => true]);
        }

        $this->assertFalse($result->fresh()->load('sectionMarks', 'batchExam.sections')->computeOverallPassed());
    }

    /** @return array<int,float> section_id => marks */
    private function marksFor(BatchExam $exam, float $value): array
    {
        return $exam->sections->mapWithKeys(fn ($s) => [$s->id => $value])->all();
    }

    public function test_service_saves_marks_computes_pass_and_audits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);

        $result = app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);

        $this->assertTrue($result->overall_passed);
        $this->assertSame(4, $result->sectionMarks()->count());
        $this->assertSame(4, $result->sectionMarks()->where('passed', true)->count());
        $this->assertSame($admin->id, $result->graded_by);
        $this->assertSame(1, AuditLog::where('action', 'exam.result_graded')
            ->where('subject_type', 'student_exam_result')->where('subject_id', $result->id)->count());
    }

    public function test_service_rejects_marks_outside_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);

        $over = $this->marksFor($exam, 20);
        $over[$exam->sections->first()->id] = 999;

        $this->expectException(ValidationException::class);
        app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $over, $admin);
    }

    public function test_service_is_idempotent_and_updates_on_resave(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);
        $svc = app(ExamGradingService::class);

        $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);
        $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 22), $admin);

        $this->assertSame(1, StudentExamResult::where('batch_exam_id', $exam->id)->where('student_id', $student->id)->count());
        $result = StudentExamResult::where('batch_exam_id', $exam->id)->where('student_id', $student->id)->first();
        $this->assertSame('22.00', $result->sectionMarks->first()->marks_obtained);
    }

    public function test_service_blocks_pass_to_fail_edit_while_certificate_is_live(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);
        $svc = app(ExamGradingService::class);

        $result = $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);

        StudentCertificate::create(['student_id' => $student->id, 'student_exam_result_id' => $result->id,
            'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-12-20',
            'certificate_number' => 'LGL-A1-2026-050', 'issued_by' => $admin->id, 'issued_at' => now()]);

        $fail = $this->marksFor($exam, 20);
        $fail[$exam->sections->first()->id] = 5;

        try {
            $svc->saveStudentResult($exam, $student->id, $fail, $admin);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('LGL-A1-2026-050', $e->getMessage());
        }

        $this->assertTrue($result->fresh()->overall_passed, 'Result must be unchanged after a blocked edit.');
    }

    public function test_service_allows_pass_to_fail_edit_after_certificate_revoked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);
        $svc = app(ExamGradingService::class);

        $result = $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);
        StudentCertificate::create(['student_id' => $student->id, 'student_exam_result_id' => $result->id,
            'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-12-20',
            'certificate_number' => 'LGL-A1-2026-051', 'issued_by' => $admin->id, 'issued_at' => now(),
            'revoked_at' => now(), 'revoked_by' => $admin->id, 'revoke_reason' => 'result correction']);

        $fail = $this->marksFor($exam, 20);
        $fail[$exam->sections->first()->id] = 5;
        $svc->saveStudentResult($exam, $student->id, $fail, $admin);

        $this->assertFalse($result->fresh()->overall_passed);
    }

    public function test_service_writes_no_result_when_marks_are_out_of_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);

        $over = $this->marksFor($exam, 20);
        $over[$exam->sections->first()->id] = 999;

        try {
            app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $over, $admin);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $e) {
            // expected
        }

        $this->assertSame(0, StudentExamResult::count());
        $this->assertSame(0, StudentExamSectionMark::count());
    }

    public function test_service_requires_a_mark_for_every_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);

        $partial = $this->marksFor($exam, 20);
        unset($partial[$exam->sections->last()->id]); // drop one section

        $this->expectException(ValidationException::class);
        app(ExamGradingService::class)->saveStudentResult($exam, $student->id, $partial, $admin);
    }

    public function test_blocked_edit_leaves_section_marks_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);
        $svc = app(ExamGradingService::class);

        $result = $svc->saveStudentResult($exam, $student->id, $this->marksFor($exam, 20), $admin);
        StudentCertificate::create(['student_id' => $student->id, 'student_exam_result_id' => $result->id,
            'student_name' => 'X', 'level' => 'A1', 'completion_date' => '2026-12-20',
            'certificate_number' => 'LGL-A1-2026-099', 'issued_by' => $admin->id, 'issued_at' => now()]);

        $first = $exam->sections->first()->id;
        $fail = $this->marksFor($exam, 20);
        $fail[$first] = 5;

        try {
            $svc->saveStudentResult($exam, $student->id, $fail, $admin);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $e) {
            // expected
        }

        $mark = $result->fresh()->sectionMarks()->where('exam_section_id', $first)->first();
        $this->assertSame('20.00', $mark->marks_obtained);
        $this->assertTrue($mark->passed);
    }

    private function enroll(Batch $batch, User $student): Enrollment
    {
        return Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);
    }

    public function test_admin_creates_an_exam_with_section_marks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();

        $this->actingAs($admin)->post('/exams', [
            'batch_id' => $batch->id, 'name' => 'A1 Final', 'type' => 'final', 'exam_date' => '2026-12-20',
            'sections' => [
                ['name' => 'Hören', 'max_marks' => 25, 'passing_marks' => 15],
                ['name' => 'Lesen', 'max_marks' => 25, 'passing_marks' => 15],
                ['name' => 'Schreiben', 'max_marks' => 30, 'passing_marks' => 18],
                ['name' => 'Sprechen', 'max_marks' => 20, 'passing_marks' => 12],
            ],
        ])->assertRedirect();

        $exam = BatchExam::sole();
        $this->assertSame(4, $exam->sections()->count());
        $this->assertSame('30.00', $exam->sections()->where('name', 'Schreiben')->value('max_marks'));
    }

    public function test_teacher_cannot_create_an_exam(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $batch = $this->batch($teacher);

        $this->actingAs($teacher)->post('/exams', ['batch_id' => $batch->id, 'name' => 'X',
            'type' => 'final', 'exam_date' => '2026-12-20', 'sections' => []])->assertForbidden();
    }

    public function test_teacher_grades_own_batch_but_not_another(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $batch = $this->batch($owner);
        $exam = $this->examWithSections($batch);
        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($batch, $student);

        $payload = ['student_id' => $student->id, 'marks' => $this->marksFor($exam, 20)];

        $this->actingAs($other)->post("/exams/{$exam->id}/results", $payload)->assertForbidden();
        $this->actingAs($owner)->post("/exams/{$exam->id}/results", $payload)->assertRedirect();
        $this->assertTrue(StudentExamResult::sole()->overall_passed);
    }

    public function test_only_batch_enrollees_can_be_graded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $batch = $this->batch();
        $exam = $this->examWithSections($batch);
        $outsider = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post("/exams/{$exam->id}/results",
            ['student_id' => $outsider->id, 'marks' => $this->marksFor($exam, 20)])
            ->assertSessionHasErrors('student_id');
        $this->assertSame(0, StudentExamResult::count());
    }
}
