<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherPayout extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'created_at' => 'datetime', 'slip_details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Posted payouts are immutable. Use a reversal.'));
        static::deleting(fn () => throw new \LogicException('Posted payouts cannot be deleted.'));
    }

    public function due()
    {
        return $this->belongsTo(TeacherDue::class, 'teacher_due_id');
    }

    public function reversal()
    {
        return $this->hasOne(TeacherPayoutReversal::class);
    }

    public function scopePosted($query)
    {
        return $query->whereDoesntHave('reversal');
    }
}
