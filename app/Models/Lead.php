<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    // Lifecycle statuses.
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    // Intake sources.
    public const SOURCE_PUBLIC = 'public';

    public const SOURCE_META = 'meta';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'source',
        'status',
        'name',
        'father_name',
        'cnic',
        'phone',
        'email',
        'program_interest',
        'message',
        'raw_payload',
        'assigned_to',
        'verified_by',
        'verified_at',
        'rejected_reason',
        'submitted_payload',
        'submitted_at',
        'student_id',
        'converted_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'submitted_payload' => 'array',
        'verified_at' => 'datetime',
        'submitted_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** Still in the pipeline (not rejected, not converted). */
    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_CONVERTED, self::STATUS_REJECTED], true);
    }

    public function isQualified(): bool
    {
        return $this->status === self::STATUS_QUALIFIED;
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    /** Open leads only (for dedup against in-flight applicants). */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CONVERTED, self::STATUS_REJECTED]);
    }
}
