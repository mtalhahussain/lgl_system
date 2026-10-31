<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReversal extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Reversals are immutable.'));
        static::deleting(fn () => throw new \LogicException('Reversals cannot be deleted.'));
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
