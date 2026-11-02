<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class TeacherDue extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    private const VOID_FIELDS = ['active_key', 'voided_at', 'voided_by', 'void_reason'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'amount' => 'decimal:2', 'student_count' => 'integer',
            'created_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // The amount is a snapshot; only voiding may change a due afterwards.
        static::updating(function (TeacherDue $due) {
            if (array_diff(array_keys($due->getDirty()), self::VOID_FIELDS)) {
                throw new \LogicException('Teacher dues are immutable. Void and regenerate instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Teacher dues cannot be deleted.'));
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function payouts()
    {
        return $this->hasMany(TeacherPayout::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('voided_at');
    }

    public function paidMinor(): int
    {
        return $this->payouts->filter(fn ($payout) => ! $payout->reversal)->sum(fn ($payout) => Money::minor($payout->amount));
    }

    public function outstandingMinor(): int
    {
        return $this->voided_at ? 0 : max(0, Money::minor($this->amount) - $this->paidMinor());
    }

    public function state(): string
    {
        if ($this->voided_at) {
            return 'cancelled';
        }
        $paid = $this->paidMinor();

        return $this->outstandingMinor() === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due');
    }

    public function label(): string
    {
        return $this->kind === 'salary'
            ? 'Salary '.\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->period.'-01')->format('M Y')
            : 'Batch '.($this->batch?->name ?? '#'.$this->batch_id);
    }
}
