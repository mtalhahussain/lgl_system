<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'date_of_birth',
        'address',
        'emergency_contact',
        'hourly_rate',
        'is_active',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function teachingBatches()
    {
        return $this->hasMany(Batch::class, 'teacher_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function teacherLeaves()
    {
        return $this->hasMany(TeacherLeave::class, 'teacher_id');
    }

    public function teacherEarnings()
    {
        return $this->hasMany(TeacherEarning::class, 'teacher_id');
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class, 'student_id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'student_id');
    }

    // Scopes
    public function scopeTeachers($query)
    {
        return $query->where('role', 'teacher');
    }

    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTeacher()
    {
        return $this->role === 'teacher';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isAccountant()
    {
        return $this->role === 'accountant';
    }
}
