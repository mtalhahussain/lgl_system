<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class BatchSchedule extends Model
{
    protected $fillable = [
        'batch_id',
        'weekday',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public const WEEKDAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    /** Duration of this class slot in minutes. */
    public function minutes(): int
    {
        return Carbon::parse($this->getRawOriginal('start_time'))
            ->diffInMinutes(Carbon::parse($this->getRawOriginal('end_time')));
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? ucfirst($this->weekday);
    }
}
