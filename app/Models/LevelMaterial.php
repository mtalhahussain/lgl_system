<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A book/PDF/document Admin uploaded for one Level (courses row), stored on the private local disk. */
class LevelMaterial extends Model
{
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];

    public const MAX_KB = 20480;

    /** Types a browser can display inline; everything else is download-only. */
    public const INLINE_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = ['course_id', 'title', 'description', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Level IDs a user may read materials for: Admin all; Teacher levels of batches assigned to them;
     * Student levels reached through their own active or completed enrollments (dropped/transferred excluded).
     */
    public static function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->actsAsAdmin()) {
            return $query;
        }
        if ($user->isTeacher()) {
            return $query->whereIn('course_id', Batch::query()->select('course_id')->where('teacher_id', $user->id));
        }
        if ($user->isStudent()) {
            return $query->whereIn('course_id', Batch::query()->select('course_id')->whereIn('id',
                Enrollment::query()->select('batch_id')->where('student_id', $user->id)->whereIn('status', ['active', 'completed'])));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isAccessibleTo(User $user): bool
    {
        return static::query()->whereKey($this->id)->accessibleTo($user)->exists();
    }

    public function canPreview(): bool
    {
        return in_array($this->mime_type, self::INLINE_MIMES, true);
    }

    public function getReadableSizeAttribute(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }
}
