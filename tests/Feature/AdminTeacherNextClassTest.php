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
        $this->assertSame('in 2 h 45 min', TeacherScheduleService::relativeLabel($this->rowFor($this->teacher)['next']));

        BatchSchedule::query()->delete();
        $this->slot($this->batch, 'sat', '10:00', '11:00');
        $next = $this->rowFor($this->teacher)['next'];
        $this->assertSame('Sat 21 Nov', TeacherScheduleService::dayLabel($next));
        $this->assertSame('Sat 21 Nov', TeacherScheduleService::relativeLabel($next));
    }

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

    public function test_admin_dashboard_shows_teacher_cards(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:30');
        $idle = User::factory()->create(['name' => 'Sara Khan', 'role' => 'teacher', 'is_active' => true]);
        $this->makeBatch($idle, 'Batch B-07');
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

    public function test_starting_now_and_whole_hour_labels(): void
    {
        $this->slot($this->batch, 'wed', '17:15', '18:00');
        $this->assertSame('in 2 h', TeacherScheduleService::relativeLabel($this->rowFor($this->teacher)['next']));

        Carbon::setTestNow(Carbon::parse('2026-11-18 17:14:30', config('app.timezone')));
        $this->assertSame('starting now', TeacherScheduleService::relativeLabel($this->rowFor($this->teacher)['next']));
    }

    public function test_session_recreated_after_cancel_still_shows(): void
    {
        $this->slot($this->batch, 'wed', '16:00', '17:00');
        ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-18', 'start_time' => '16:00', 'end_time' => '17:00', 'topic' => 'Off', 'status' => 'cancelled']);
        ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-18', 'start_time' => '16:00', 'end_time' => '17:00', 'topic' => 'Back on', 'status' => 'scheduled']);

        $this->assertSame('2026-11-18 16:00', $this->rowFor($this->teacher)['next']['start']->format('Y-m-d H:i'));
    }

    public function test_back_to_back_leaves_extend_badge(): void
    {
        $this->slot($this->batch, 'mon', '16:00', '17:00');
        TeacherLeave::create(['teacher_id' => $this->teacher->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-20', 'type' => 'sick', 'reason' => 'Flu', 'status' => 'approved']);
        TeacherLeave::create(['teacher_id' => $this->teacher->id, 'start_date' => '2026-11-21', 'end_date' => '2026-11-23', 'type' => 'paid', 'reason' => 'Rest', 'status' => 'approved']);

        $row = $this->rowFor($this->teacher);

        $this->assertSame('2026-11-23', $row['onLeaveUntil']->toDateString());
        $this->assertNull($row['next']);
    }

    public function test_class_ending_after_midnight_keeps_end_on_next_day(): void
    {
        $this->slot($this->batch, 'wed', '23:00', '00:30');

        $next = $this->rowFor($this->teacher)['next'];

        $this->assertSame('2026-11-19 00:30', $next['end']->format('Y-m-d H:i'));
    }
}
