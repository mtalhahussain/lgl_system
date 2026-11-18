# Admin Teachers' Next Classes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each teacher's next class (today / tomorrow / later this week) as cards on the admin dashboard.

**Architecture:** A read-only `TeacherScheduleService` builds slots for the next 7 days from `batch_schedules` (ongoing batches within their dates), removes cancelled `class_sessions` and approved-leave days, adds extra/makeup sessions, and returns per-teacher `next` / `laterToday` / `onLeaveUntil`. `AdminController::dashboard` passes the result to a new Blade partial included in `dashboards/admin.blade.php`.

**Tech Stack:** Laravel, Blade, Carbon, PHPUnit on SQLite `:memory:` (forced by `phpunit.xml`).

Spec: `docs/superpowers/specs/2026-11-18-admin-teacher-next-classes-design.md`

---

## Project rules (from AGENTS.md / CLAUDE.md)

- Before editing, read `docs/PROJECT.md` and `docs/TASKS.md`; record a reservation for task **D01 admin teachers' next classes** and the files below in `docs/TASKS.md` "Current state".
- One writer; preserve unrelated changes; tests only on the isolated SQLite DB (never the working DB).
- Record evidence in `docs/TASKS.md` before releasing the reservation.
- Commit only if the owner asks.

## Files

- Create: `app/Services/TeacherScheduleService.php` — slot computation (pure read).
- Modify: `app/Http/Controllers/AdminController.php` (`dashboard()`, lines 20-29) — pass `$teacherNextClasses`.
- Create: `resources/views/dashboards/partials/teacher-next-classes.blade.php` — cards.
- Modify: `resources/views/dashboards/admin.blade.php` — `@include` after the batch-reminders `@endif`.
- Create: `tests/Feature/AdminTeacherNextClassTest.php`.
- Modify: `docs/TASKS.md` — reservation + evidence.

## Facts the engineer needs

- `User::teachers()` scope = `role = teacher`; `User::batches()` = `hasMany(Batch, 'teacher_id')`.
- `Batch` has `name`, `status` (`upcoming|ongoing|completed|cancelled`), `start_date`, `end_date` (date casts), `course()`, `schedules()`.
- `BatchSchedule`: `weekday` in `mon..sun`, `start_time`/`end_time` cast `datetime:H:i`. Raw DB value may be `09:00`, `09:00:00` or a full datetime, so always `Carbon::parse($row->getRawOriginal('start_time'))->format('H:i')`.
- `ClassSession`: `batch_id`, `session_date` (date), `start_time`, `end_time` (same cast), `status` in `scheduled|ongoing|completed|cancelled`. S02 `BatchSessionScheduler` already creates sessions matching the timetable, so a non-cancelled session with the same batch/date/start must NOT add a second slot.
- `TeacherLeave`: `teacher_id`, `start_date`, `end_date`, `type` (`paid|unpaid|sick|emergency`), `reason` (required text), `status`; scope `approved()`.
- `Course::create` needs `name, level, total_fee, teacher_per_student_amount, duration_weeks` in tests.
- `/admin/dashboard` → `AdminController::dashboard`, `role:admin` middleware aborts 403 for others.
- 18 Nov 2026 is a Wednesday.

---

### Task 1: TeacherScheduleService — timetable, live, later today

**Files:**
- Create: `app/Services/TeacherScheduleService.php`
- Test: `tests/Feature/AdminTeacherNextClassTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AdminTeacherNextClassTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\TeacherLeave;
use App\Models\User;
use App\Services\TeacherScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminTeacherNextClassTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Batch $batch;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 18 Nov 2026, 15:15
        Carbon::setTestNow(Carbon::parse('2026-11-18 15:15:00', config('app.timezone')));
        $this->course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $this->teacher = User::factory()->create(['name' => 'Ali Raza', 'role' => 'teacher', 'is_active' => true]);
        $this->batch = $this->makeBatch($this->teacher, 'Batch B-12');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeBatch(User $teacher, string $name, array $overrides = []): Batch
    {
        return Batch::create(array_merge([
            'name' => $name, 'course_id' => $this->course->id, 'teacher_id' => $teacher->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20,
        ], $overrides));
    }

    private function slot(Batch $batch, string $weekday, string $start, string $end): void
    {
        BatchSchedule::create(['batch_id' => $batch->id, 'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end]);
    }

    private function rowFor(User $teacher): ?array
    {
        return app(TeacherScheduleService::class)->upcomingForTeachers()
            ->first(fn ($row) => $row['teacher']->id === $teacher->id);
    }

    public function test_upcoming_slot_today_is_next_with_minutes_label(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:30');

        $row = $this->rowFor($this->teacher);

        $this->assertSame('2026-11-18 16:00', $row['next']['start']->format('Y-m-d H:i'));
        $this->assertSame('2026-11-18 17:30', $row['next']['end']->format('Y-m-d H:i'));
        $this->assertFalse($row['next']['live']);
        $this->assertSame($this->batch->id, $row['next']['batch']->id);
        $this->assertSame('in 45 min', TeacherScheduleService::relativeLabel($row['next']));
        $this->assertSame('Today', TeacherScheduleService::dayLabel($row['next']));
    }

    public function test_slot_ended_today_moves_next_to_following_day(): void
    {
        $this->slot($this->batch, 'wed', '09:00', '10:30');
        $this->slot($this->batch, 'thu', '10:00', '11:00');

        $row = $this->rowFor($this->teacher);

        $this->assertSame('2026-11-19 10:00', $row['next']['start']->format('Y-m-d H:i'));
        $this->assertSame('Tomorrow', TeacherScheduleService::dayLabel($row['next']));
        $this->assertSame('Tomorrow', TeacherScheduleService::relativeLabel($row['next']));
    }

    public function test_running_slot_is_live(): void
    {
        $this->slot($this->batch, 'wed', '15:00', '16:30');

        $row = $this->rowFor($this->teacher);

        $this->assertTrue($row['next']['live']);
        $this->assertSame('LIVE', TeacherScheduleService::relativeLabel($row['next']));
    }

    public function test_later_today_lists_up_to_two_more_slots(): void
    {
        foreach (['16:00', '18:00', '19:00', '20:00'] as $start) {
            $this->slot($this->batch, 'wed', $start, Carbon::parse($start)->addMinutes(45)->format('H:i'));
        }

        $row = $this->rowFor($this->teacher);

        $this->assertSame('16:00', $row['next']['start']->format('H:i'));
        $this->assertSame(['18:00', '19:00'], collect($row['laterToday'])->map(fn ($s) => $s['start']->format('H:i'))->all());
    }

    public function test_hours_label_and_weekday_label(): void
    {
        $this->slot($this->batch, 'wed', '18:00', '19:00');
        $this->assertSame('in 2 h', TeacherScheduleService::relativeLabel($this->rowFor($this->teacher)['next']));

        BatchSchedule::query()->delete();
        $this->slot($this->batch, 'sat', '10:00', '11:00');
        $next = $this->rowFor($this->teacher)['next'];
        $this->assertSame('Sat 21 Nov', TeacherScheduleService::dayLabel($next));
        $this->assertSame('Sat 21 Nov', TeacherScheduleService::relativeLabel($next));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AdminTeacherNextClassTest`
Expected: FAIL — `Class "App\Services\TeacherScheduleService" not found`.

- [ ] **Step 3: Write the service**

Create `app/Services/TeacherScheduleService.php`:

```php
<?php

namespace App\Services;

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
                'onLeaveUntil' => $currentLeave->isEmpty() ? null : $currentLeave->max('end_date'),
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
                $timetableStarts[] = $start;
                if (in_array($start, $cancelled, true)) {
                    continue;
                }
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
        return [
            'batch' => $batch,
            'date' => $date->copy(),
            'start' => Carbon::parse($date->toDateString().' '.$start),
            'end' => Carbon::parse($date->toDateString().' '.$end),
        ];
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

    /** "LIVE", "in 45 min", "in 2 h", or the day label for later days. */
    public static function relativeLabel(array $slot): string
    {
        if ($slot['live']) {
            return 'LIVE';
        }
        if (! $slot['start']->isToday()) {
            return self::dayLabel($slot);
        }
        $minutes = (int) floor(Carbon::now()->diffInMinutes($slot['start'], true));

        return $minutes < 60 ? "in {$minutes} min" : 'in '.intdiv($minutes, 60).' h';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AdminTeacherNextClassTest`
Expected: 5 passed.

---

### Task 2: Sessions, leave, batch filters

**Files:**
- Test: `tests/Feature/AdminTeacherNextClassTest.php` (append methods)
- (Service from Task 1 should already satisfy these; fix it if any fail.)

- [ ] **Step 1: Append tests**

Add inside `AdminTeacherNextClassTest`:

```php
    public function test_cancelled_session_removes_timetable_slot(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:00');
        ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-18', 'start_time' => '16:00', 'end_time' => '17:00', 'topic' => 'Off', 'status' => 'cancelled']);

        $row = $this->rowFor($this->teacher);

        $this->assertSame('2026-11-25 16:00', $row['next']['start']->format('Y-m-d H:i'));
    }

    public function test_makeup_session_added_and_matching_session_not_duplicated(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:00');
        ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-18', 'start_time' => '16:00', 'end_time' => '17:00', 'topic' => 'Regular', 'status' => 'scheduled']);
        ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-18', 'start_time' => '18:00', 'end_time' => '19:00', 'topic' => 'Makeup', 'status' => 'scheduled']);

        $row = $this->rowFor($this->teacher);

        $this->assertSame('16:00', $row['next']['start']->format('H:i'));
        $this->assertSame(['18:00'], collect($row['laterToday'])->map(fn ($s) => $s['start']->format('H:i'))->all());
    }

    public function test_approved_leave_sets_badge_and_skips_days(): void
    {
        foreach (['wed', 'fri', 'mon'] as $day) {
            $this->slot($this->batch, $day, '16:00', '17:00');
        }
        TeacherLeave::create(['teacher_id' => $this->teacher->id, 'start_date' => '2026-11-18', 'end_date' => '2026-11-20', 'type' => 'sick', 'reason' => 'Flu', 'status' => 'approved']);

        $row = $this->rowFor($this->teacher);

        $this->assertSame('2026-11-20', $row['onLeaveUntil']->toDateString());
        $this->assertSame('2026-11-23 16:00', $row['next']['start']->format('Y-m-d H:i'));
    }

    public function test_pending_leave_is_ignored(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:00');
        TeacherLeave::create(['teacher_id' => $this->teacher->id, 'start_date' => '2026-11-18', 'end_date' => '2026-11-20', 'type' => 'paid', 'reason' => 'Trip', 'status' => 'pending']);

        $row = $this->rowFor($this->teacher);

        $this->assertNull($row['onLeaveUntil']);
        $this->assertSame('2026-11-18 16:00', $row['next']['start']->format('Y-m-d H:i'));
    }

    public function test_batch_past_end_date_gives_no_next_and_non_ongoing_teacher_excluded(): void
    {
        $this->batch->update(['end_date' => '2026-11-17']);
        $this->slot($this->batch, 'wed', '16:00', '17:00');

        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $upcoming = $this->makeBatch($other, 'Batch B-20', ['status' => 'upcoming']);
        $this->slot($upcoming, 'wed', '16:00', '17:00');

        $this->assertNull($this->rowFor($this->teacher)['next']);
        $this->assertNull($this->rowFor($other));
    }
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --filter=AdminTeacherNextClassTest`
Expected: 10 passed. If a test fails, fix `TeacherScheduleService` (not the test) and rerun.

---

### Task 3: Dashboard wiring and cards

**Files:**
- Modify: `app/Http/Controllers/AdminController.php:20-29`
- Create: `resources/views/dashboards/partials/teacher-next-classes.blade.php`
- Modify: `resources/views/dashboards/admin.blade.php` (after the batch-reminders `@endif`, before `<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3 tw:mb-6">`)
- Test: `tests/Feature/AdminTeacherNextClassTest.php` (append)

- [ ] **Step 1: Append failing tests**

```php
    public function test_admin_dashboard_shows_teacher_cards(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:30');
        $idle = User::factory()->create(['name' => 'Sara Khan', 'role' => 'teacher', 'is_active' => true]);
        $idleBatch = $this->makeBatch($idle, 'Batch B-07');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertSee("Teachers' next classes", false)
            ->assertSee('Ali Raza')
            ->assertSee('Batch B-12')
            ->assertSee('in 45 min')
            ->assertSee('Sara Khan')
            ->assertSee('No class in next 7 days');
    }

    public function test_teacher_cannot_open_admin_dashboard(): void
    {
        $this->actingAs($this->teacher)->get('/admin/dashboard')->assertForbidden();
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=test_admin_dashboard_shows_teacher_cards`
Expected: FAIL — response does not contain "Teachers' next classes".

- [ ] **Step 3: Controller**

In `app/Http/Controllers/AdminController.php`, replace:

```php
        $reminders = app(\App\Services\OverviewService::class)->batchReminders();

        return view('dashboards.admin', compact('stats', 'finance', 'recent', 'reminders'));
```

with:

```php
        $reminders = app(\App\Services\OverviewService::class)->batchReminders();
        $teacherNextClasses = app(\App\Services\TeacherScheduleService::class)->upcomingForTeachers();

        return view('dashboards.admin', compact('stats', 'finance', 'recent', 'reminders', 'teacherNextClasses'));
```

- [ ] **Step 4: Partial**

Create `resources/views/dashboards/partials/teacher-next-classes.blade.php`:

```blade
@php use App\Services\TeacherScheduleService as TSS; @endphp
<section class="panel tw:mb-6" aria-labelledby="teacher-next-heading">
    <div class="panel-heading">
        <h2 id="teacher-next-heading"><i class="fas fa-chalkboard-teacher me-2 text-navy" aria-hidden="true"></i>Teachers' next classes</h2>
        <span class="page-subtitle">{{ now()->format('D d M Y, g:i A') }} · from batch timetables</span>
    </div>
    @if($teacherNextClasses->isEmpty())
        <p class="empty-state">No teacher has an ongoing batch.</p>
    @else
    <div class="tw:grid tw:grid-cols-1 tw:gap-4 tw:p-4 tw:sm:grid-cols-2 tw:xl:grid-cols-4">
        @foreach($teacherNextClasses as $row)
            @php $next = $row['next']; @endphp
            <article class="tw:rounded-lg tw:border tw:border-slate-200 tw:p-4 {{ $next && $next['live'] ? 'border-left-gold' : '' }}">
                <div class="tw:flex tw:items-center tw:justify-between tw:gap-2 tw:mb-2">
                    <strong>{{ $row['teacher']->name }}</strong>
                    @if($row['onLeaveUntil'])<span class="status-pill">On leave · until {{ $row['onLeaveUntil']->format('D j M') }}</span>@endif
                </div>
                @if($next)
                    <div class="metric-label">NEXT · {{ TSS::dayLabel($next) }}</div>
                    <div class="tw:text-lg tw:font-semibold">{{ $next['start']->format('g:i A') }} – {{ $next['end']->format('g:i A') }}</div>
                    <div class="small text-muted">
                        {{ $next['batch']->course?->name }} ·
                        <a href="{{ route('batches.show', $next['batch']) }}">{{ $next['batch']->name }}</a>
                    </div>
                    <span class="status-pill tw:mt-2">{{ TSS::relativeLabel($next) }}</span>
                    @if($row['laterToday'])
                        <div class="small text-muted tw:mt-3">Later today:
                            @foreach($row['laterToday'] as $slot){{ $slot['start']->format('g:i A') }} {{ $slot['batch']->name }}@if(! $loop->last), @endif @endforeach
                        </div>
                    @endif
                @else
                    <p class="small text-muted mb-0">No class in next 7 days</p>
                @endif
            </article>
        @endforeach
    </div>
    @endif
</section>
```

- [ ] **Step 5: Include in admin dashboard**

In `resources/views/dashboards/admin.blade.php`, find the line that is exactly `@endif` closing the batch-reminders section, immediately followed by `<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3 tw:mb-6">`. Insert between them:

```blade
@include('dashboards.partials.teacher-next-classes')
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=AdminTeacherNextClassTest`
Expected: 12 passed.

Run: `php artisan test --filter="BatchStatusSyncTest|CoreWorkflowTest"`
Expected: same pass/fail as before the change (both hit `/admin/dashboard`).

---

### Task 4: Full verification and evidence

**Files:**
- Modify: `docs/TASKS.md`

- [ ] **Step 1: Lint and diff check**

Run: `php -l app/Services/TeacherScheduleService.php && php -l app/Http/Controllers/AdminController.php && git diff --check`
Expected: "No syntax errors detected" twice, no diff-check output.

- [ ] **Step 2: Full suite**

Run: `php artisan test`
Expected: all new tests pass; the only failures are the 2 pre-existing ones recorded under L01 (SeederConsistencyTest demo user count, WhatsAppReminderTest "Staff Attendance"). Any other failure must be fixed.

- [ ] **Step 3: Record evidence**

In `docs/TASKS.md` "Current state", add a D01 entry: owner-approved design (teacher cards; timetable + cancelled/makeup sessions + approved leave; 7-day window; admin only), files changed, test counts from Steps 1-2, note no migration/no data change and no browser check unless one was run. Mark the reservation released.

- [ ] **Step 4: Commit (only if owner asks)**

```bash
git add app/Services/TeacherScheduleService.php app/Http/Controllers/AdminController.php resources/views/dashboards/partials/teacher-next-classes.blade.php resources/views/dashboards/admin.blade.php tests/Feature/AdminTeacherNextClassTest.php docs/TASKS.md
git commit -m "feat(dashboard): show teachers' next classes on admin dashboard (D01)"
```
