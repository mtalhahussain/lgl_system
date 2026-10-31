<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $record) {
            $record->created_at ??= now();
        });
        static::updating(fn () => throw new \LogicException('Audit records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Audit records cannot be deleted.'));
    }
}
