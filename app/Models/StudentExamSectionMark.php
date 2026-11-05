<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExamSectionMark extends Model
{
    protected $fillable = ['student_exam_result_id', 'exam_section_id', 'marks_obtained', 'passed'];

    protected function casts(): array
    {
        return ['marks_obtained' => 'decimal:2', 'passed' => 'boolean'];
    }

    public function result()
    {
        return $this->belongsTo(StudentExamResult::class, 'student_exam_result_id');
    }

    public function section()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }
}
