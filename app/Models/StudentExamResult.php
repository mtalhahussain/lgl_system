<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExamResult extends Model
{
    protected $fillable = ['batch_exam_id', 'student_id', 'overall_passed', 'graded_by', 'graded_at'];

    protected function casts(): array
    {
        return ['overall_passed' => 'boolean', 'graded_at' => 'datetime'];
    }

    public function batchExam()
    {
        return $this->belongsTo(BatchExam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function sectionMarks()
    {
        return $this->hasMany(StudentExamSectionMark::class);
    }

    public function certificate()
    {
        return $this->hasOne(StudentCertificate::class, 'student_exam_result_id');
    }

    /**
     * True only when every one of the exam's sections has a recorded,
     * passing mark. A missing section counts as not passed.
     */
    public function computeOverallPassed(): bool
    {
        $sections = $this->batchExam->sections;
        if ($sections->isEmpty()) {
            return false;
        }

        $marks = $this->sectionMarks->keyBy('exam_section_id');
        foreach ($sections as $section) {
            $mark = $marks->get($section->id);
            if (! $mark || ! $mark->passed) {
                return false;
            }
        }

        return true;
    }
}
