<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;

    protected $attributes = ['auto_absent_enabled' => false];

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

    public function validateAttendanceStudents(array $studentIds): void
    {
        if ($this->status === 'cancelled' || $this->batch->status === 'cancelled') {
            throw \Illuminate\Validation\ValidationException::withMessages(['attendance' => 'Cancelled sessions or batches cannot receive attendance.']);
        }
        $eligibleIds = $this->eligibleEnrollments()->pluck('student_id')->map(fn ($id) => (string) $id)->all();
        $submittedIds = array_map('strval', $studentIds);

        if (array_diff($submittedIds, $eligibleIds) || count($submittedIds) !== count(array_unique($submittedIds))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attendance' => 'Attendance must contain distinct students enrolled in this batch on the session date.',
            ]);
        }
    }

    public function eligibleEnrollments()
    {
        return $this->batch->enrollments()
            ->where(function ($query) {
                $query->whereIn('status', ['active', 'completed'])
                    ->orWhere(fn ($query) => $query->where('status', 'transferred')->whereDate('transfer_date', '>=', $this->session_date))
                    ->orWhere(fn ($query) => $query->where('status', 'dropped')->whereDate('dropout_date', '>=', $this->session_date));
            })
            ->whereDate('enrollment_date', '<=', $this->session_date)
            ->whereHas('student', fn ($query) => $query->where('role', 'student'));
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
        if ($totalStudents == 0) {
            return 0;
        }

        $presentCount = $this->attendances()->where('status', 'present')->count();

        return ($presentCount / $totalStudents) * 100;
    }

    public function markAttendance($studentId, $status, $checkInTime = null)
    {
        app(\App\Services\AttendanceService::class)->markBulkAttendance($this->id, [['student_id' => $studentId, 'status' => $status]]);

        return $this->attendances()->where('student_id', $studentId)->sole();
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
