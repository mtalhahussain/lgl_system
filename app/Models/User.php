<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** Roles that can sign in. */
    public const ROLES = ['admin', 'teacher', 'student'];

    /** Every stored role; staff (receptionist, cleaner, guard, …) is paid and may use the machine but never signs in. */
    public const ALL_ROLES = ['admin', 'teacher', 'student', 'staff'];

    /** People who are paid and may punch the attendance machine. */
    public const PAID_ROLES = ['teacher', 'staff'];

    public function getStudentIdAttribute(): ?string
    {
        return $this->isStudent() ? 'STD'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT) : null;
    }

    protected static function booted(): void
    {
        static::created(fn (User $user) => $user->recordAccountAudit('user.created', $user->only(['name', 'email', 'role', 'is_active'])));
        static::updated(fn (User $user) => $user->recordAccountAudit('user.updated',
            array_intersect_key($user->getChanges(), array_flip(['name', 'email', 'role', 'is_active']))));
        // Auto machine employee number and Hikvision user sync for Students and Teachers.
        static::created(fn (User $user) => app(\App\Biometric\MachineUserSync::class)->userChanged($user, true));
        static::updated(fn (User $user) => app(\App\Biometric\MachineUserSync::class)->userChanged($user, false));
    }

    private function recordAccountAudit(string $action, array $fields): void
    {
        if (! $fields || ! \Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            return;
        }
        AuditLog::create(['actor_id' => auth()->id(), 'action' => $action, 'subject_type' => 'user', 'subject_id' => $this->id,
            'changes' => ['before' => $action === 'user.created' ? null : array_intersect_key($this->getRawOriginal(), $fields),
                'after' => $fields], 'created_at' => now()]);
    }
    use HasFactory, Notifiable;

    public function teacherPayments()
    {
        return $this->hasManyThrough(\App\Models\TeacherPayment::class, \App\Models\BatchTeacherEarning::class, 'teacher_id', 'batch_teacher_earning_id');
    }

    public function batchTeacherEarnings()
    {
        return $this->hasMany(\App\Models\BatchTeacherEarning::class, 'teacher_id');
    }
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
        'per_student_rate',
        'qualification',
        'experience_years',
        'specialization',
        'employee_id',
        'is_active',
        'password',
        'hire_date',
        'bio',
        'salary_type',
        'monthly_salary',
        'designation',
        'per_batch_amount',
        'per_student_amount',
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
            'hire_date' => 'date',
            'hourly_rate' => 'decimal:2',
            'per_student_rate' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'experience_years' => 'integer',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function teachingBatches()
    {
        return $this->hasMany(Batch::class, 'teacher_id');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'teacher_id');
    }

    public function taughtBatches()
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

    public function feeInstallments()
    {
        return $this->hasManyThrough(FeeInstallment::class, Enrollment::class, 'student_id', 'enrollment_id');
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

    public function isStaff()
    {
        return $this->role === 'staff';
    }

}
