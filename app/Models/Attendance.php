<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'status',
        'source',
        'check_in_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_time' => 'datetime:H:i:s',
        ];
    }

    // Relationships
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // Scopes
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    // Helper methods
    public function isPresent()
    {
        return in_array($this->status, ['present', 'late']);
    }

    public function wasLate()
    {
        return $this->status === 'late';
    }

    public static function getAttendanceStats($studentId, $batchId = null)
    {
        $query = static::where('student_id', $studentId)->whereHas('classSession', function ($query) use ($batchId) {
            $query->where('status', '!=', 'cancelled')->when($batchId, fn ($query) => $query->where('batch_id', $batchId));
        });

        return app(\App\Services\AttendanceService::class)->statistics($query);
    }
}