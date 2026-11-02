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
        'refunded_amount',
        'discount_amount',
        'notes',
        'transferred_to_batch_id',
        'transfer_date',
        'dropout_date',
        'dropout_reason',
        'transferred_from_enrollment_id',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'transfer_date' => 'date',
            'dropout_date' => 'date',
            'total_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(FeeRefund::class);
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
        if ($this->status === 'transferred') {
            return '0.00';
        }
        return \App\Support\Money::decimal(max(0, \App\Support\Money::minor($this->total_fee)
            - \App\Support\Money::minor($this->net_paid) - \App\Support\Money::minor($this->discount_amount)));
    }

    // Payments kept by the institute: posted payments less refunds.
    public function getNetPaidAttribute(): string
    {
        return \App\Support\Money::decimal(max(0, \App\Support\Money::minor($this->paid_amount) - \App\Support\Money::minor($this->refunded_amount ?? '0')));
    }

    // Derived from the stored amount (only the amount is kept); e.g. "10" or "12.5".
    public function getDiscountPercentageAttribute(): string
    {
        $gross = \App\Support\Money::minor($this->total_fee);
        if ($gross === 0) {
            return '0';
        }
        $basisPoints = intdiv(\App\Support\Money::minor($this->discount_amount) * 10000 + intdiv($gross, 2), $gross);

        return rtrim(rtrim(intdiv($basisPoints, 100).'.'.str_pad((string) ($basisPoints % 100), 2, '0', STR_PAD_LEFT), '0'), '.');
    }

    public function getPaymentProgressPercentageAttribute()
    {
        $net = \App\Support\Money::minor($this->total_fee) - \App\Support\Money::minor($this->discount_amount);
        return $net > 0 ? (\App\Support\Money::minor($this->net_paid) / $net) * 100 : 100;
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
