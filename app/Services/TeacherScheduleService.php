<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassSession;
use App\Models\TeacherLeave;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only: each active teacher's upcoming classes over a short window,
 * built from batch timetables, adjusted by class sessions and approved leave.
 */
class TeacherScheduleService
{
    /**
     * Batches whose end date falls within the next $days days (status not required to be completed),
     * with the teacher's weekly slots that free up and the teacher's next assigned batch, if any.
     *
     * @return Collection<int, array{batch: Batch, teacher: ?User, daysLeft: int, slots: array, nextBatch: ?Batch}>
     */
    public function batchesEndingSoon(int $days = 7): Collection
    {
        $today = Carbon::today();
        $batches = Batch::whereIn('status', ['upcoming', 'ongoing'])
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()])
            ->with(['teacher:id,name', 'course:id,name,level', 'schedules'])
            ->orderBy('end_date')->orderBy('name')->get();
        $later = Batch::whereIn('teacher_id', $batches->pluck('teacher_id')->filter()->unique())
            ->whereIn('status', ['upcoming', 'ongoing'])->whereDate('start_date', '>=', $today->toDateString())->orderBy('start_date')
            ->get(['id', 'name', 'teacher_id', 'start_date', 'end_date'])->groupBy('teacher_id');

        return $batches->map(fn (Batch $batch) => [
            'batch' => $batch,
            'teacher' => $batch->teacher,
            'daysLeft' => (int) $today->diffInDays($batch->end_date, false),
            'slots' => $batch->schedules->sortBy(fn ($s) => array_search($s->weekday, array_keys(BatchSchedule::WEEKDAYS)))
                ->map(fn ($s) => substr($s->weekdayLabel(), 0, 3).' '.Carbon::parse($s->getRawOriginal('start_time'))->format('g:i A')
                    .'–'.Carbon::parse($s->getRawOriginal('end_time'))->format('g:i A'))->values()->all(),
            // Next batch: another batch of the same teacher starting today or later (batches already running alongside don't count).
            'nextBatch' => $later->get($batch->teacher_id, collect())->first(fn ($other) => $other->id !== $batch->id),
        ])->values();
    }

    /**
     * @return Collection<int, array{teacher: User, next: ?array, laterToday: array, onLeaveUntil: ?Carbon}>
     */
    public function upcomingForTeachers(int $days = 7): Collection
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $last = $today->copy()->addDays($days);

        $teachers = User::teachers()->where('is_active', true)
            ->whereHas('batches', fn ($q) => $q->where('status', 'ongoing'))
            ->with(['batches' => fn ($q) => $q->where('status', 'ongoing')->with(['course:id,name,level', 'schedules'])])
            ->orderBy('name')
            ->get();

        $batchIds = $teachers->flatMap(fn ($t) => $t->batches->pluck('id'))->all();

        $sessions = ClassSession::whereIn('batch_id', $batchIds)
            ->whereDate('session_date', '>=', $today->toDateString())
            ->whereDate('session_date', '<=', $last->toDateString())
            ->get()
            ->groupBy('batch_id');

        $leaves = TeacherLeave::approved()
            ->whereIn('teacher_id', $teachers->pluck('id'))
            ->whereDate('start_date', '<=', $last->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->get()
            ->groupBy('teacher_id');

        return $teachers->map(function (User $teacher) use ($now, $today, $days, $sessions, $leaves) {
            $teacherLeaves = $leaves->get($teacher->id, collect());
            $onLeave = fn (Carbon $date) => $teacherLeaves->contains(
                fn ($leave) => $leave->start_date->lte($date) && $leave->end_date->gte($date)
            );

            $slots = collect();
            for ($i = 0; $i <= $days; $i++) {
                $date = $today->copy()->addDays($i);
                if ($onLeave($date)) {
                    continue;
                }
                foreach ($teacher->batches as $batch) {
                    $slots = $slots->merge($this->slotsForBatchOnDate($batch, $date, $sessions->get($batch->id, collect())));
                }
            }

            $slots = $slots
                ->filter(fn ($slot) => $slot['end']->gt($now))
                ->map(fn ($slot) => $slot + ['live' => $slot['start']->lte($now)])
                ->sortBy(fn ($slot) => $slot['start']->timestamp)
                ->values();

            $next = $slots->first();
            $laterToday = $next && $next['start']->isSameDay($now)
                ? $slots->slice(1)->filter(fn ($slot) => $slot['start']->isSameDay($now))->take(2)->values()->all()
                : [];

            $currentLeave = $teacherLeaves->filter(fn ($leave) => $leave->start_date->lte($today) && $leave->end_date->gte($today));

            return [
                'teacher' => $teacher,
                'next' => $next,
                'laterToday' => $laterToday,
                'onLeaveUntil' => $this->leaveUntil($currentLeave, $teacherLeaves),
            ];
        })
            ->sortBy(fn ($row) => $row['next'] ? $row['next']['start']->timestamp : PHP_INT_MAX)
            ->values();
    }

    /** Timetable slots for one batch on one date, minus cancelled sessions, plus extra/makeup sessions. */
    private function slotsForBatchOnDate($batch, Carbon $date, Collection $batchSessions): Collection
    {
        $daySessions = $batchSessions->filter(fn ($s) => $s->session_date->isSameDay($date));
        $sessionKey = fn ($s) => self::hm($s->getRawOriginal('start_time'));
        $cancelled = $daySessions->where('status', 'cancelled')->map($sessionKey)->all();

        $slots = collect();
        $timetableStarts = [];

        $inRange = $batch->start_date->lte($date) && ($batch->end_date === null || $batch->end_date->gte($date));
        if ($inRange) {
            $weekday = strtolower($date->format('D'));
            foreach ($batch->schedules->where('weekday', $weekday) as $row) {
                $start = self::hm($row->getRawOriginal('start_time'));
                if (in_array($start, $cancelled, true)) {
                    continue;
                }
                $timetableStarts[] = $start;
                $slots->push($this->makeSlot($batch, $date, $start, self::hm($row->getRawOriginal('end_time'))));
            }
        }

        foreach ($daySessions as $session) {
            $start = $sessionKey($session);
            if ($session->status === 'cancelled' || in_array($start, $timetableStarts, true)) {
                continue;
            }
            $slots->push($this->makeSlot($batch, $date, $start, self::hm($session->getRawOriginal('end_time'))));
        }

        return $slots;
    }

    private function makeSlot($batch, Carbon $date, string $start, string $end): array
    {
        $startAt = Carbon::parse($date->toDateString().' '.$start);
        $endAt = Carbon::parse($date->toDateString().' '.$end);
        if ($endAt->lte($startAt)) {
            $endAt->addDay(); // class runs past midnight
        }

        return ['batch' => $batch, 'date' => $date->copy(), 'start' => $startAt, 'end' => $endAt];
    }

    /** Last day of the leave covering today, following back-to-back approved leaves. */
    private function leaveUntil(Collection $currentLeave, Collection $teacherLeaves): ?Carbon
    {
        if ($currentLeave->isEmpty()) {
            return null;
        }
        $until = $currentLeave->max('end_date')->copy();
        do {
            $extended = false;
            foreach ($teacherLeaves as $leave) {
                if ($leave->start_date->lte($until->copy()->addDay()) && $leave->end_date->gt($until)) {
                    $until = $leave->end_date->copy();
                    $extended = true;
                }
            }
        } while ($extended);

        return $until;
    }

    private static function hm(?string $raw): string
    {
        return Carbon::parse($raw)->format('H:i');
    }

    /** "Today", "Tomorrow" or "Sat 21 Nov". */
    public static function dayLabel(array $slot): string
    {
        if ($slot['start']->isToday()) {
            return 'Today';
        }
        if ($slot['start']->isTomorrow()) {
            return 'Tomorrow';
        }

        return $slot['start']->format('D j M');
    }

    /** "LIVE", "starting now", "in 45 min", "in 2 h 15 min", or the day label for later days. */
    public static function relativeLabel(array $slot): string
    {
        if ($slot['live']) {
            return 'LIVE';
        }
        if (! $slot['start']->isToday()) {
            return self::dayLabel($slot);
        }
        $minutes = (int) floor(Carbon::now()->diffInMinutes($slot['start'], true));

        if ($minutes < 1) {
            return 'starting now';
        }
        if ($minutes < 60) {
            return "in {$minutes} min";
        }
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest ? "in {$hours} h {$rest} min" : "in {$hours} h";
    }
}
