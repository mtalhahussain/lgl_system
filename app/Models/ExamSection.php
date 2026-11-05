<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSection extends Model
{
    protected $fillable = ['batch_exam_id', 'name', 'max_marks', 'passing_marks', 'sort'];

    protected function casts(): array
    {
        return ['max_marks' => 'decimal:2', 'passing_marks' => 'decimal:2'];
    }

    public function batchExam()
    {
        return $this->belongsTo(BatchExam::class);
    }
}
