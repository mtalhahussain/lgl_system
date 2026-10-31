<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'created_at' => 'datetime', 'receipt_details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Posted payments are immutable. Use a reversal.'));
        static::deleting(fn () => throw new \LogicException('Posted payments cannot be deleted.'));
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversal()
    {
        return $this->hasOne(PaymentReversal::class);
    }

    public function scopePosted($query)
    {
        return $query->whereDoesntHave('reversal');
    }
}
