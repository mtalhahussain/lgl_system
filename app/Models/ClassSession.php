<?php

namespace App\Models;

use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
        'makeup_for_id',
        'teaching_status',
        'teaching_started_at',
        'teaching_recorded_at',
        'teaching_recorded_by',
        'teaching_reason',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'materials' => 'array',
            'teaching_started_at' => 'datetime',
            'teaching_recorded_at' => 'datetime',
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

    public function makeupFor()
    {
        return $this->belongsTo(self::class, 'makeup_for_id');
    }

    public function makeupClasses()
    {
        return $this->hasMany(self::class, 'makeup_for_id');
    }

    public function teachingRecorder()
    {
        return $this->belongsTo(User::class, 'teaching_recorded_by');
    }

    public function teachingLabel(): string
    {
        if ($this->teaching_status === 'missed') {
            $makeups = $this->makeupClasses;
            if ($makeups->contains(fn ($class) => $class->teaching_status === 'taken')) {
                return 'Missed · Covered';
            }

            return $makeups->contains(fn ($class) => $class->status !== 'cancelled') ? 'Missed · Makeup pending' : 'Missed · Makeup needed';
        }
        if ($this->teaching_status === 'taken') {
            return 'Taken';
        }
        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }

        return $this->session_date->copy()->setTimeFromTimeString($this->getRawOriginal('end_time'))->lt(now()) ? 'Not confirmed' : 'Scheduled';
    }

    public function validateAttendanceStudents(array $studentIds): void
    {
        if ($this->status === 'cancelled' || $this->batch->status === 'cancelled') {
            throw ValidationException::withMessages(['attendance' => 'Cancelled sessions or batches cannot receive attendance.']);
        }
        $eligibleIds = $this->eligibleEnrollments()->pluck('student_id')->map(fn ($id) => (string) $id)->all();
        $submittedIds = array_map('strval', $studentIds);

        if (array_diff($submittedIds, $eligibleIds) || count($submittedIds) !== count(array_unique($submittedIds))) {
            throw ValidationException::withMessages([
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
        app(AttendanceService::class)->markBulkAttendance($this->id, [['student_id' => $studentId, 'status' => $status]]);

        return $this->attendances()->where('student_id', $studentId)->sole();
    }

    public function isOnline()
    {
        return $this->batch->isOnline();
    }

    public function startAt(): Carbon
    {
        return $this->session_date->copy()->setTimeFromTimeString($this->getRawOriginal('start_time'));
    }

    public function endAt(): Carbon
    {
        return $this->session_date->copy()->setTimeFromTimeString($this->getRawOriginal('end_time'));
    }

    /**
     * Whether a student may self check-in right now, and as what status.
     * One source of truth for both the service and the dashboard button.
     *
     * @return array{open: bool, status: ?string, message: ?string}
     */
    public function selfJoinState(?Carbon $now = null): array
    {
        $now ??= now();
        if (! $this->isOnline()) {
            return ['open' => false, 'status' => null, 'message' => 'This session is not an online class.'];
        }
        if ($this->status === 'cancelled' || $this->batch->status === 'cancelled') {
            return ['open' => false, 'status' => null, 'message' => 'This class has been cancelled.'];
        }
        if (! $this->session_date->isSameDay($now)) {
            return ['open' => false, 'status' => null, 'message' => 'Self check-in is only available on the class day.'];
        }
        $start = $this->startAt();
        if ($now->lt($start->copy()->subMinutes(15))) {
            return ['open' => false, 'status' => null, 'message' => 'Join opens 15 minutes before the class.'];
        }
        if ($now->gt($this->endAt())) {
            return ['open' => false, 'status' => null, 'message' => 'This class has ended; your teacher will mark attendance.'];
        }

        return ['open' => true, 'status' => $now->lte($start->copy()->addMinutes(10)) ? 'present' : 'late', 'message' => null];
    }

    public function getDurationInMinutesAttribute()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }
}
