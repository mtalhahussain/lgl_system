<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw machine punch and what the system did with it. Immutable. */
class BiometricPunch extends Model
{
    public const RESULTS = [
        'present' => 'Marked present',
        'late' => 'Marked late',
        'already_marked' => 'Already marked',
        'no_session' => 'No class at this time',
        'teacher' => 'Teacher punch',
        'staff' => 'Staff punch',
        'unknown_code' => 'Unknown employee no.',
        'ignored' => 'Ignored',
        'clock_ahead' => 'Machine clock ahead',
    ];

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Biometric punches are immutable.'));
        static::deleting(fn () => throw new \LogicException('Biometric punch history cannot be deleted.'));
    }

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }
}
