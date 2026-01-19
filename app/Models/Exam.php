<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'name',
        'type',
        'exam_date',
        'start_time',
        'end_time',
        'max_marks',
        'passing_marks',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'max_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
        ];
    }

    // Relationships
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('exam_date', '>', now()->toDateString())
                    ->where('status', 'scheduled');
    }

    public function scopeToday($query)
    {
        return $query->where('exam_date', now()->toDateString());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Helper methods
    public function getDurationInMinutesAttribute()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    public function getPassingPercentageAttribute()
    {
        if ($this->max_marks == 0) return 0;
        return ($this->passing_marks / $this->max_marks) * 100;
    }

    public function getResultsStats()
    {
        $results = $this->examResults;
        $total = $results->count();
        
        if ($total == 0) {
            return [
                'total_students' => 0,
                'passed' => 0,
                'failed' => 0,
                'pass_rate' => 0,
                'average_marks' => 0,
                'highest_marks' => 0,
                'lowest_marks' => 0,
            ];
        }

        $passed = $results->where('is_passed', true)->count();
        $averageMarks = $results->avg('marks_obtained');
        $highestMarks = $results->max('marks_obtained');
        $lowestMarks = $results->min('marks_obtained');

        return [
            'total_students' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'pass_rate' => ($passed / $total) * 100,
            'average_marks' => round($averageMarks, 2),
            'highest_marks' => $highestMarks,
            'lowest_marks' => $lowestMarks,
        ];
    }

    public function isUpcoming()
    {
        return $this->exam_date > now() && $this->status === 'scheduled';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function markCompleted()
    {
        $this->update(['status' => 'completed']);
    }
}