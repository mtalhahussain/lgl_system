<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeRefund extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refunded_at' => 'datetime', 'created_at' => 'datetime', 'slip_details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Refunds are immutable.'));
        static::deleting(fn () => throw new \LogicException('Refunds cannot be deleted.'));
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
