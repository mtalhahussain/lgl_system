<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'payment_method',
        'transaction_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    // Relationships
    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereRaw('fee_installments.amount > (SELECT COALESCE(SUM(payment_allocations.amount), 0)
            FROM payment_allocations JOIN payments ON payments.id = payment_allocations.payment_id
            LEFT JOIN payment_reversals ON payment_reversals.payment_id = payments.id
            WHERE payment_allocations.fee_installment_id = fee_installments.id AND payment_reversals.id IS NULL)');
    }

    public function getOutstandingAmountAttribute(): string
    {
        if ($this->relationLoaded('allocations')) {
            $paidMinor = $this->allocations->filter(fn ($row) => $row->payment->reversal === null)
                ->sum(fn ($row) => \App\Support\Money::minor($row->amount));
        } else {
            $paidMinor = $this->allocations()->whereHas('payment', fn ($query) => $query->posted())->pluck('amount')
                ->sum(fn ($amount) => \App\Support\Money::minor($amount));
        }

        return \App\Support\Money::decimal(max(0, \App\Support\Money::minor($this->amount) - $paidMinor));
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

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                    ->where('due_date', '<', now()->toDateString());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', 'pending')
                    ->where('due_date', now()->toDateString());
    }

    // Helper methods
    public function isOverdue()
    {
        return $this->status === 'pending' && $this->due_date < now();
    }

    public function isDueToday()
    {
        return $this->status === 'pending' && $this->due_date->isToday();
    }

    public function markAsPaid($paymentMethod = null, $transactionRef = null)
    {
        throw new \LogicException('Use PaymentService with a submission key to record payments.');
    }
}
