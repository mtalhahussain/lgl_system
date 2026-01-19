<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'marks_obtained',
        'grade',
        'is_passed',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'is_passed' => 'boolean',
        ];
    }

    // Relationships
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // Scopes
    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('is_passed', false);
    }

    public function scopeByGrade($query, $grade)
    {
        return $query->where('grade', $grade);
    }

    // Helper methods
    public function getPercentageAttribute()
    {
        if ($this->exam->max_marks == 0) return 0;
        return ($this->marks_obtained / $this->exam->max_marks) * 100;
    }

    public function calculateGrade()
    {
        $percentage = $this->getPercentageAttribute();

        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B+';
        if ($percentage >= 60) return 'B';
        if ($percentage >= 50) return 'C+';
        if ($percentage >= 40) return 'C';
        return 'F';
    }

    public function determinePassStatus()
    {
        return $this->marks_obtained >= $this->exam->passing_marks;
    }

    public static function createResult($examId, $studentId, $marksObtained, $remarks = null)
    {
        $exam = Exam::find($examId);
        if (!$exam) return null;

        $result = new self([
            'exam_id' => $examId,
            'student_id' => $studentId,
            'marks_obtained' => $marksObtained,
            'remarks' => $remarks,
        ]);

        $result->is_passed = $result->determinePassStatus();
        $result->grade = $result->calculateGrade();
        $result->save();

        return $result;
    }

    public function updateMarks($newMarks, $remarks = null)
    {
        $this->marks_obtained = $newMarks;
        $this->is_passed = $this->determinePassStatus();
        $this->grade = $this->calculateGrade();
        if ($remarks !== null) {
            $this->remarks = $remarks;
        }
        $this->save();

        return $this;
    }
}