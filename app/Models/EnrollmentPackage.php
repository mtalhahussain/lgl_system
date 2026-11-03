<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentPackage extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'receipt_details' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Package receipts are immutable. Reverse the linked payments.'));
        static::deleting(fn () => throw new \LogicException('Package history cannot be deleted.'));
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function payments()
    {
        return Payment::whereIn('id', array_column($this->receipt_details['modules'], 'payment_id'));
    }
}
