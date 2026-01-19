<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
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