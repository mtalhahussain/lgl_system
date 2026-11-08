<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-02 10:00:00'));
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 10000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12, 'is_active' => true]);
    }

    private function batchPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'A1-Eve',
            'course_id' => $this->course->id,
            'teacher_id' => $this->teacher->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addWeeks(3)->toDateString(),
            'max_students' => 10,
            'status' => 'auto',
            'meeting_platform' => 'zoom',
            'meeting_link' => 'https://zoom.us/j/123',
            'schedule' => [
                ['weekday' => 'mon', 'start' => '18:00', 'end' => '20:00'],
                ['weekday' => 'wed', 'start' => '16:00', 'end' => '18:00'],
            ],
        ], $overrides);
    }

    public function test_admin_creates_batch_with_schedule_and_weekly_hours_are_computed(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload())
            ->assertRedirect(route('batches.index'));

        $batch = Batch::firstWhere('name', 'A1-Eve');
        $this->assertCount(2, $batch->schedules);
        $this->assertEqualsCanonicalizing(['mon', 'wed'], $batch->schedules->pluck('weekday')->all());
        $this->assertSame(4.0, $batch->weeklyHours());
        $this->assertSame(240, $batch->weeklyMinutes());
        $this->assertSame(7, $batch->classSessions()->count());
        $this->assertSame('https://zoom.us/j/123', $batch->classSessions()->first()->meeting_link);
    }

    public function test_editing_a_batch_replaces_its_schedule(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload());
        $batch = Batch::firstWhere('name', 'A1-Eve');

        $this->actingAs($this->admin)->put(route('batches.update', $batch), $this->batchPayload([
            'schedule' => [['weekday' => 'fri', 'start' => '10:00', 'end' => '11:30']],
        ]))->assertRedirect(route('batches.show', $batch));

        $batch->refresh()->load('schedules');
        $this->assertCount(1, $batch->schedules);
        $this->assertSame('fri', $batch->schedules->first()->weekday);
        $this->assertSame(1.5, $batch->weeklyHours());
    }

    public function test_blank_schedule_rows_are_ignored_and_incomplete_rows_rejected(): void
    {
        // A fully blank row is dropped; one valid row remains.
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload([
            'schedule' => [
                ['weekday' => 'mon', 'start' => '18:00', 'end' => '20:00'],
                ['weekday' => '', 'start' => '', 'end' => ''],
            ],
        ]))->assertRedirect(route('batches.index'));
        $this->assertCount(1, Batch::firstWhere('name', 'A1-Eve')->schedules);

        // An incomplete row (day + start but no end) is rejected.
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload([
            'name' => 'Bad',
            'schedule' => [['weekday' => 'tue', 'start' => '09:00', 'end' => '']],
        ]))->assertSessionHasErrors('schedule');
        $this->assertNull(Batch::firstWhere('name', 'Bad'));
    }

    public function test_schedule_end_must_be_after_start(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload([
            'schedule' => [['weekday' => 'mon', 'start' => '20:00', 'end' => '18:00']],
        ]))->assertSessionHasErrors('schedule');
    }

    public function test_generate_from_schedule_creates_one_session_per_day_with_its_own_time(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload([
            'start_date' => today()->startOfWeek()->toDateString(),
            'end_date' => today()->startOfWeek()->addWeek()->toDateString(),
        ]));
        $batch = Batch::firstWhere('name', 'A1-Eve');

        $this->actingAs($this->admin)->post(route('class-sessions.generate-from-schedule', $batch), [
            'from' => today()->startOfWeek()->toDateString(),
            'to' => today()->startOfWeek()->addWeek()->toDateString(),
        ])->assertRedirect(route('batches.show', $batch));

        $sessions = ClassSession::where('batch_id', $batch->id)->get();
        $this->assertGreaterThanOrEqual(2, $sessions->count());
        foreach ($sessions as $session) {
            $weekday = strtolower($session->session_date->format('D'));
            $start = Carbon::parse($session->getRawOriginal('start_time'))->format('H:i');
            $this->assertSame($weekday === 'mon' ? '18:00' : '16:00', $start);
        }

        // Re-running creates no duplicates.
        $before = ClassSession::count();
        $this->actingAs($this->admin)->post(route('class-sessions.generate-from-schedule', $batch), [
            'from' => today()->startOfWeek()->toDateString(),
            'to' => today()->startOfWeek()->addWeek()->toDateString(),
        ]);
        $this->assertSame($before, ClassSession::count());
    }

    public function test_generate_from_schedule_requires_a_schedule(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload(['schedule' => []]));
        $batch = Batch::firstWhere('name', 'A1-Eve');

        $this->actingAs($this->admin)->post(route('class-sessions.generate-from-schedule', $batch), [])
            ->assertSessionHasErrors('schedule');
        $this->assertSame(0, ClassSession::where('batch_id', $batch->id)->count());
    }

    public function test_generate_from_schedule_blocked_for_other_teacher(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload());
        $batch = Batch::firstWhere('name', 'A1-Eve');
        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($other)->post(route('class-sessions.generate-from-schedule', $batch), [])->assertForbidden();
    }

    public function test_teacher_dashboard_shows_planned_weekly_hours(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload());

        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));
        $response->assertOk();
        $response->assertSee('Teaching load');
        $response->assertSee('4 h / week');
    }

    public function test_student_timetable_shows_own_classes_and_join_link_only(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload());
        $batch = Batch::firstWhere('name', 'A1-Eve');

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        app(EnrollmentService::class)->enrollStudent($student->id, $batch->id, 0, 1);

        $mine = $this->actingAs($student)->get(route('student.timetable'));
        $mine->assertOk();
        $mine->assertSee('A1-Eve');
        $mine->assertSee('Join class');
        $mine->assertSee('https://zoom.us/j/123');

        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($other)->get(route('student.timetable'))->assertOk()->assertDontSee('A1-Eve');
    }

    public function test_teacher_cannot_create_a_batch(): void
    {
        $this->actingAs($this->teacher)->post(route('batches.store'), $this->batchPayload())->assertForbidden();
        $this->assertSame(0, BatchSchedule::count());
    }

    public function test_saving_again_keeps_existing_classes_and_cancelled_dates_and_extends_duration(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload());
        $batch = Batch::firstWhere('name', 'A1-Eve');
        $first = $batch->classSessions()->orderBy('session_date')->first();
        $first->update(['status' => 'cancelled', 'topic' => 'Holiday']);
        $ids = $batch->classSessions()->pluck('id')->all();

        $this->put(route('batches.update', $batch), $this->batchPayload())->assertSessionHasNoErrors();
        $this->assertSame($ids, $batch->classSessions()->pluck('id')->all());
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->put(route('batches.update', $batch), $this->batchPayload([
            'end_date' => today()->addWeeks(4)->toDateString(),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(9, $batch->classSessions()->count());
        $this->assertSame('Holiday', $first->fresh()->topic);
    }

    public function test_auto_schedule_requires_end_date_and_rolls_back_excessive_work(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload(['end_date' => null]))
            ->assertSessionHasErrors('end_date');
        $this->assertSame(0, Batch::count());
        $this->post(route('batches.store'), $this->batchPayload([
            'end_date' => today()->addYears(8)->toDateString(),
        ]))->assertSessionHasErrors('end_date');
        $this->assertSame(0, Batch::count());
        $this->assertSame(0, BatchSchedule::count());
        $this->assertSame(0, ClassSession::count());
        $this->post(route('batches.store'), $this->batchPayload(['schedule' => [], 'end_date' => null]))
            ->assertSessionHasErrors('end_date');
        $this->assertSame(0, Batch::count());
    }

    public function test_teacher_sees_today_attendance_and_own_timing_without_bulk_generator(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload());
        $batch = Batch::firstWhere('name', 'A1-Eve');
        $today = $batch->classSessions()->whereDate('session_date', today())->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        app(EnrollmentService::class)->enrollStudent($student->id, $batch->id, 0, 1);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee("Today's classes", false)->assertSee('18:00–20:00')
            ->assertSee(route('class-sessions.attendance', $today), false);
        $this->get(route('batches.index'))->assertOk()->assertSee('Weekly timing')->assertSee('18:00–20:00');
        $this->get(route('batches.show', $batch))->assertOk()->assertSee('Extra / makeup class')
            ->assertDontSee('Advanced: generate missing classes');
        $this->post(route('class-sessions.attendance.store', $today), ['attendance' => [$student->id => 'present']])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('attendances', ['class_session_id' => $today->id, 'student_id' => $student->id, 'status' => 'present']);
        $this->actingAs($this->admin)->put(route('batches.update', $batch), $this->batchPayload())
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', ['class_session_id' => $today->id, 'student_id' => $student->id, 'status' => 'present']);
        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->get(route('teacher.dashboard'))->assertOk()->assertDontSee('A1-Eve');
        $this->get(route('class-sessions.attendance', $today))->assertForbidden();
    }

    public function test_auto_schedule_skips_past_and_cancelled_batches_and_rejects_duplicate_slots(): void
    {
        $this->actingAs($this->admin)->post(route('batches.store'), $this->batchPayload([
            'status' => 'cancelled',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0, ClassSession::count());
        $this->post(route('batches.store'), $this->batchPayload([
            'name' => 'Past', 'start_date' => today()->subMonth()->toDateString(), 'end_date' => today()->subDay()->toDateString(),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0, ClassSession::count());
        $slot = ['weekday' => 'mon', 'start' => '18:00', 'end' => '20:00'];
        $this->post(route('batches.store'), $this->batchPayload(['name' => 'Duplicate', 'schedule' => [$slot, $slot]]))
            ->assertSessionHasErrors('schedule');
        $this->assertNull(Batch::firstWhere('name', 'Duplicate'));
    }

    public function test_multiple_slots_are_generated_without_past_classes_or_rewriting_existing_times(): void
    {
        $payload = $this->batchPayload([
            'start_date' => today()->subWeek()->toDateString(),
            'end_date' => today()->addWeek()->toDateString(),
            'schedule' => [
                ['weekday' => 'mon', 'start' => '09:00', 'end' => '10:00'],
                ['weekday' => 'mon', 'start' => '18:00', 'end' => '20:00'],
            ],
        ]);
        $this->actingAs($this->admin)->post(route('batches.store'), $payload)->assertSessionHasNoErrors();
        $batch = Batch::firstWhere('name', 'A1-Eve');
        $this->assertSame(4, $batch->classSessions()->count());
        $this->assertSame(0, $batch->classSessions()->whereDate('session_date', '<', today())->count());
        $before = $batch->classSessions()->orderBy('id')->get()->toArray();
        $payload['schedule'][0]['start'] = '08:00';
        $this->put(route('batches.update', $batch), $payload)->assertSessionHasNoErrors();
        $this->assertSame($before, $batch->classSessions()->orderBy('id')->get()->toArray());
        $this->actingAs($this->teacher)->get(route('courses.show', $this->course))->assertOk()
            ->assertSee('08:00–10:00')->assertSee(today()->addWeek()->format('d M Y'));
    }
}
