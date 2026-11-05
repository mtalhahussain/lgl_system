<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchExam;
use App\Models\Course;
use App\Models\StudentExamResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
