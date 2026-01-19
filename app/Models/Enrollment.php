<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'student_id',
        'enrollment_date',
        'status',
        'total_fee',
        'paid_amount',
        'discount_amount',
        'notes',
        'transferred_to_batch_id',
        'transfer_date',
        'dropout_date',
        'dropout_reason',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'transfer_date' => 'date',
            'dropout_date' => 'date',
            'total_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function transferredToBatch()
    {
        return $this->belongsTo(Batch::class, 'transferred_to_batch_id');
    }

    public function feeInstallments()
    {
        return $this->hasMany(FeeInstallment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeDropped($query)
    {
        return $query->where('status', 'dropped');
    }

    // Helper methods
    public function getRemainingFeeAttribute()
    {
        return $this->total_fee - $this->paid_amount - $this->discount_amount;
    }

    public function getPaymentProgressPercentageAttribute()
    {
        if ($this->total_fee == 0) return 100;
        return ($this->paid_amount / $this->total_fee) * 100;
    }

    public function isFullyPaid()
    {
        return $this->remaining_fee <= 0;
    }

    public function hasOverdueInstallments()
    {
        return $this->feeInstallments()
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->exists();
    }
}