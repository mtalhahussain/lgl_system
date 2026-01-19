<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'session_date',
        'start_time',
        'end_time',
        'topic',
        'description',
        'status',
        'meeting_link',
        'recording_link',
        'materials',
        'homework',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'materials' => 'array',
        ];
    }

    // Relationships
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->where('session_date', now()->toDateString());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('session_date', '>=', now()->toDateString())
                    ->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper methods
    public function getAttendanceRate()
    {
        $totalStudents = $this->batch->activeEnrollments()->count();
        if ($totalStudents == 0) return 0;

        $presentCount = $this->attendances()->where('status', 'present')->count();
        return ($presentCount / $totalStudents) * 100;
    }

    public function markAttendance($studentId, $status, $checkInTime = null)
    {
        return $this->attendances()->updateOrCreate(
            ['student_id' => $studentId],
            [
                'status' => $status,
                'check_in_time' => $checkInTime ?? ($status === 'present' ? now()->format('H:i:s') : null),
            ]
        );
    }

    public function isOnline()
    {
        return $this->batch->isOnline();
    }

    public function getDurationInMinutesAttribute()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }
}