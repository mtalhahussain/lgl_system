<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BatchExam;
use App\Models\StudentExamResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamGradingService
{
    /**
     * Record (or update) one student's section marks for an exam.
     *
     * @param  array<int,float|string>  $sectionMarks  exam_section_id => marks_obtained
     */
    public function saveStudentResult(BatchExam $exam, int $studentId, array $sectionMarks, User $actor): StudentExamResult
    {
        $exam->loadMissing('sections');

        if ($exam->sections->isEmpty()) {
            throw ValidationException::withMessages([
                'marks' => 'This exam has no sections to grade.',
            ]);
        }

        // Validate every section and compute its pass once, rounding to 2dp to
        // avoid float-boundary misclassification on decimal(…,2) values.
        $computed = []; // section_id => [marks, passed]
        $newOverall = true;
        foreach ($exam->sections as $section) {
            if (! array_key_exists($section->id, $sectionMarks)) {
                throw ValidationException::withMessages([
                    'marks' => "Marks for {$section->name} are required.",
                ]);
            }
            $value = $sectionMarks[$section->id];
            $marks = is_numeric($value) ? round((float) $value, 2) : null;
            if ($marks === null || $marks < 0 || $marks > round((float) $section->max_marks, 2)) {
                throw ValidationException::withMessages([
                    "marks.{$section->id}" => "{$section->name}: marks must be between 0 and {$section->max_marks}.",
                ]);
            }
            $passed = $marks >= round((float) $section->passing_marks, 2);
            $computed[$section->id] = [$marks, $passed];
            if (! $passed) {
                $newOverall = false;
            }
        }

        return DB::transaction(function () use ($exam, $studentId, $actor, $newOverall, $computed) {
            // Lock the existing result row so the guard's check and the write are atomic.
            $existing = StudentExamResult::where('batch_exam_id', $exam->id)
                ->where('student_id', $studentId)->lockForUpdate()->first();

            if ($existing && $existing->overall_passed && ! $newOverall) {
                $liveCert = $existing->certificate()->whereNull('revoked_at')->first();
                if ($liveCert) {
                    throw ValidationException::withMessages([
                        'marks' => "This result has certificate {$liveCert->certificate_number}. Revoke it before lowering the student below a pass.",
                    ]);
                }
            }

            $result = StudentExamResult::updateOrCreate(
                ['batch_exam_id' => $exam->id, 'student_id' => $studentId],
                ['overall_passed' => $newOverall, 'graded_by' => $actor->id, 'graded_at' => now()],
            );

            foreach ($computed as $sectionId => [$marks, $passed]) {
                $result->sectionMarks()->updateOrCreate(
                    ['exam_section_id' => $sectionId],
                    ['marks_obtained' => $marks, 'passed' => $passed],
                );
            }

            AuditLog::create(['actor_id' => $actor->id, 'action' => 'exam.result_graded',
                'subject_type' => 'student_exam_result', 'subject_id' => $result->id, 'changes' => []]);

            return $result;
        });
    }
}
