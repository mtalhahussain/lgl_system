<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One WhatsApp template message attempt. Only the delivery outcome is written after creation. */
class WhatsappMessage extends Model
{
    public const KINDS = ['before_due' => 'Before due date', 'due_today' => 'On due date', 'manual' => 'Sent by Admin', 'test' => 'Test message'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $message) {
            if (array_diff(array_keys($message->getDirty()), ['status', 'provider_message_id', 'error', 'updated_at'])) {
                throw new \LogicException('WhatsApp message records are immutable apart from the delivery outcome.');
            }
        });
        static::deleting(fn () => throw new \LogicException('WhatsApp message history cannot be deleted.'));
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function installment()
    {
        return $this->belongsTo(FeeInstallment::class, 'fee_installment_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
