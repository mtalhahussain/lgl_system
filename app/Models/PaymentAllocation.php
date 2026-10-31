<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Posted allocations are immutable.'));
        static::deleting(fn () => throw new \LogicException('Posted allocations cannot be deleted.'));
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function installment()
    {
        return $this->belongsTo(FeeInstallment::class, 'fee_installment_id');
    }
}
