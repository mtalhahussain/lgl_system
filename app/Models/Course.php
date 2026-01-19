<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
        'description',
        'total_fee',
        'teacher_per_student_amount',
        'duration_weeks',
        'sessions_per_week',
        'session_duration_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_fee' => 'decimal:2',
            'teacher_per_student_amount' => 'decimal:2',
            'duration_weeks' => 'integer',
            'sessions_per_week' => 'integer',
            'session_duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    // Helper methods
    public function getFormattedFeeAttribute()
    {
        return currency_format($this->total_fee);
    }

    public function getTotalSessionsAttribute()
    {
        return $this->duration_weeks * $this->sessions_per_week;
    }
}