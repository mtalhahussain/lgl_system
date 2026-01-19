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
        $this->update([
            'status' => 'paid',
            'paid_date' => now(),
            'payment_method' => $paymentMethod,
            'transaction_reference' => $transactionRef,
        ]);

        // Update enrollment paid amount
        $this->enrollment->increment('paid_amount', $this->amount);
    }
}