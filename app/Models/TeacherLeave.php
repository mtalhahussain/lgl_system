<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'start_date',
        'end_date',
        'type',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePaid($query)
    {
        return $query->where('type', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('type', 'unpaid');
    }

    // Helper methods
    public function getDurationInDaysAttribute()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function isPaid()
    {
        return $this->type === 'paid';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function approve($adminId, $notes = null)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function reject($adminId, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function isActive($date = null)
    {
        $checkDate = $date ?? now()->toDateString();
        return $this->isApproved() && 
               $this->start_date <= $checkDate && 
               $this->end_date >= $checkDate;
    }
}