<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherPayoutReversal extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Reversals are immutable.'));
        static::deleting(fn () => throw new \LogicException('Reversals cannot be deleted.'));
    }

    public function payout()
    {
        return $this->belongsTo(TeacherPayout::class, 'teacher_payout_id');
    }
}
