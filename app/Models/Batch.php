<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    public function teacherPayments()
    {
        return $this->hasManyThrough(\App\Models\TeacherPayment::class, \App\Models\BatchTeacherEarning::class, 'batch_id', 'batch_teacher_earning_id');
    }

    public function batchTeacherEarnings()
    {
        return $this->hasMany(\App\Models\BatchTeacherEarning::class);
    }
    use HasFactory;

    protected $fillable = [
        'course_id',
        'teacher_id',
        'name',
        'start_date',
        'end_date',
        'max_students',
        'status',
        'meeting_platform',
        'meeting_link',
        'meeting_password',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'max_students' => 'integer',
        ];
    }

    // Relationships
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->enrollments()->where('status', 'active');
    }

    public function classSessions()
    {
        return $this->hasMany(ClassSession::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function teacherEarnings()
    {
        return $this->hasMany(TeacherEarning::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function feeInstallments()
    {
        return $this->hasManyThrough(FeeInstallment::class, Enrollment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper methods
    // Date-derived status; the end date itself still counts as ongoing. Cancellation is handled by callers.
    public static function statusForDates($startDate, $endDate, $today = null): string
    {
        $today = \Illuminate\Support\Carbon::parse($today ?? today())->startOfDay();
        if ($endDate && \Illuminate\Support\Carbon::parse($endDate)->startOfDay()->lt($today)) {
            return 'completed';
        }

        return \Illuminate\Support\Carbon::parse($startDate)->startOfDay()->lte($today) ? 'ongoing' : 'upcoming';
    }

    /**
     * Keeps enrollments in step with the batch: a completed batch completes its active enrollments,
     * and a batch moved back to upcoming/ongoing reactivates them. Withdrawn and transferred ones stay.
     * Fees are untouched; any outstanding balance remains payable.
     */
    public function syncEnrollmentStatuses(?int $actorId = null): int
    {
        [$from, $to] = match ($this->status) {
            'completed' => ['active', 'completed'],
            'upcoming', 'ongoing' => ['completed', 'active'],
            default => [null, null],
        };
        if (! $from) {
            return 0;
        }
        $ids = $this->enrollments()->where('status', $from)->lockForUpdate()->pluck('id');
        foreach ($ids as $id) {
            Enrollment::whereKey($id)->update(['status' => $to]);
            AuditLog::create(['actor_id' => $actorId, 'action' => 'enrollment.status_synced', 'subject_type' => 'enrollment',
                'subject_id' => $id, 'changes' => ['before' => ['status' => $from], 'after' => ['status' => $to], 'batch_status' => $this->status]]);
        }

        return $ids->count();
    }

    public function getCurrentEnrollmentCount()
    {
        return $this->activeEnrollments()->count();
    }

    public function hasCapacity()
    {
        return $this->getCurrentEnrollmentCount() < $this->max_students;
    }

    public function getAvailableSpotsAttribute()
    {
        return $this->max_students - $this->getCurrentEnrollmentCount();
    }

    public function isOnline()
    {
        return in_array($this->meeting_platform, ['zoom', 'google_meet']);
    }
}