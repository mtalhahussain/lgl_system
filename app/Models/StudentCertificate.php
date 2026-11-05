<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StudentCertificate extends Model
{
    protected $fillable = [
        'student_id', 'student_name', 'father_name', 'level',
        'completion_date', 'certificate_number', 'issued_by', 'issued_at',
        'student_exam_result_id', 'revoked_at', 'revoked_by', 'revoke_reason',
    ];

    protected function casts(): array
    {
        return ['completion_date' => 'date', 'issued_at' => 'datetime',
            'revoked_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function result()
    {
        return $this->belongsTo(StudentExamResult::class, 'student_exam_result_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Next human-readable number for a level, e.g. LGL-A1-2026-001.
     * Skips any number already taken (the unique index is the final guard).
     */
    public static function nextNumber(string $level): string
    {
        $prefix = 'LGL-'.$level.'-'.Carbon::now()->year.'-';
        $seq = static::where('certificate_number', 'like', $prefix.'%')->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $seq++;
        } while (static::where('certificate_number', $candidate)->exists());

        return $candidate;
    }
}
