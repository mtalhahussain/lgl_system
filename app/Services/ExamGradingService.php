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

        foreach ($exam->sections as $section) {
            if (! array_key_exists($section->id, $sectionMarks)) {
                throw ValidationException::withMessages([
                    'marks' => "Marks for {$section->name} are required.",
                ]);
            }
            $value = $sectionMarks[$section->id];
            if (! is_numeric($value) || $value < 0 || $value > (float) $section->max_marks) {
                throw ValidationException::withMessages([
                    "marks.{$section->id}" => "{$section->name}: marks must be between 0 and {$section->max_marks}.",
                ]);
            }
        }

        $newOverall = true;
        foreach ($exam->sections as $section) {
            if ((float) $sectionMarks[$section->id] < (float) $section->passing_marks) {
                $newOverall = false;
                break;
            }
        }

        $existing = StudentExamResult::where('batch_exam_id', $exam->id)
            ->where('student_id', $studentId)->first();

        if ($existing && $existing->overall_passed && ! $newOverall) {
            $liveCert = $existing->certificate()->whereNull('revoked_at')->first();
            if ($liveCert) {
                throw ValidationException::withMessages([
                    'marks' => "This result has certificate {$liveCert->certificate_number}. Revoke it before lowering the student below a pass.",
                ]);
            }
        }

        return DB::transaction(function () use ($exam, $studentId, $sectionMarks, $actor, $newOverall) {
            $result = StudentExamResult::updateOrCreate(
                ['batch_exam_id' => $exam->id, 'student_id' => $studentId],
                ['overall_passed' => $newOverall, 'graded_by' => $actor->id, 'graded_at' => now()],
            );

            foreach ($exam->sections as $section) {
                $marks = $sectionMarks[$section->id];
                $result->sectionMarks()->updateOrCreate(
                    ['exam_section_id' => $section->id],
                    ['marks_obtained' => $marks, 'passed' => (float) $marks >= (float) $section->passing_marks],
                );
            }

            AuditLog::create(['actor_id' => $actor->id, 'action' => 'exam.result_graded',
                'subject_type' => 'student_exam_result', 'subject_id' => $result->id, 'changes' => []]);

            return $result;
        });
    }
}
