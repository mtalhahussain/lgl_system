<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'batch_id',
        'year',
        'month',
        'students_count',
        'per_student_amount',
        'total_earning',
        'paid_amount',
        'paid_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'students_count' => 'integer',
            'per_student_amount' => 'decimal:2',
            'total_earning' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_date' => 'date',
        ];
    }

    // Relationships
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('status', 'partially_paid');
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    // Helper methods
    public function getRemainingAmountAttribute()
    {
        return $this->total_earning - $this->paid_amount;
    }

    public function getMonthNameAttribute()
    {
        return now()->month($this->month)->format('F');
    }

    public function isFullyPaid()
    {
        return $this->paid_amount >= $this->total_earning;
    }

    public function payPartial($amount, $notes = null)
    {
        $newPaidAmount = $this->paid_amount + $amount;
        
        if ($newPaidAmount >= $this->total_earning) {
            $this->update([
                'paid_amount' => $this->total_earning,
                'status' => 'paid',
                'paid_date' => now(),
                'notes' => $notes,
            ]);
        } else {
            $this->update([
                'paid_amount' => $newPaidAmount,
                'status' => 'partially_paid',
                'notes' => $notes,
            ]);
        }
    }

    public function payFull($notes = null)
    {
        $this->update([
            'paid_amount' => $this->total_earning,
            'status' => 'paid',
            'paid_date' => now(),
            'notes' => $notes,
        ]);
    }

    public static function calculateEarning($teacherId, $batchId, $year, $month)
    {
        $batch = Batch::find($batchId);
        if (!$batch) return null;

        $studentsCount = $batch->activeEnrollments()
            ->whereYear('enrollment_date', '<=', $year)
            ->whereMonth('enrollment_date', '<=', $month)
            ->count();

        $perStudentAmount = $batch->course->teacher_per_student_amount;
        $totalEarning = $studentsCount * $perStudentAmount;

        return [
            'students_count' => $studentsCount,
            'per_student_amount' => $perStudentAmount,
            'total_earning' => $totalEarning,
        ];
    }
}