<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 10000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
    }

    private function batch(string $name, string $status, $start, $end = null): Batch
    {
        return Batch::create(['name' => $name, 'course_id' => $this->course->id, 'teacher_id' => $this->teacher->id,
            'start_date' => $start, 'end_date' => $end, 'max_students' => 10, 'status' => $status]);
    }

    public function test_status_for_dates_counts_end_date_as_ongoing(): void
    {
        $this->assertSame('upcoming', Batch::statusForDates(today()->addDay(), null));
        $this->assertSame('ongoing', Batch::statusForDates(today(), today()));
        $this->assertSame('completed', Batch::statusForDates(today()->subWeek(), today()->subDay()));
    }

    public function test_job_advances_statuses_forward_and_is_idempotent(): void
    {
        $starts = $this->batch('Starts', 'upcoming', today());
        $future = $this->batch('Future', 'upcoming', today()->addDay(), today()->addMonth());
        $ends = $this->batch('Ends', 'ongoing', today()->subMonth(), today()->subDay());
        $lastDay = $this->batch('LastDay', 'ongoing', today()->subMonth(), today());
        $missed = $this->batch('Missed', 'upcoming', today()->subMonth(), today()->subWeek());
        $open = $this->batch('OpenEnded', 'ongoing', today()->subYear());
        $cancelled = $this->batch('Cancelled', 'cancelled', today()->subMonth(), today()->subDay());
        $early = $this->batch('ManualEarly', 'ongoing', today()->addWeek());

        $this->artisan('batches:sync-status')->assertSuccessful();

        $this->assertSame('ongoing', $starts->fresh()->status);
        $this->assertSame('upcoming', $future->fresh()->status);
        $this->assertSame('completed', $ends->fresh()->status);
        $this->assertSame('ongoing', $lastDay->fresh()->status);
        $this->assertSame('completed', $missed->fresh()->status);
        $this->assertSame('ongoing', $open->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('ongoing', $early->fresh()->status);
        $this->assertSame(3, AuditLog::where('action', 'batch.status_synced')->count());

        $this->artisan('batches:sync-status')->assertSuccessful();
        $this->assertSame(3, AuditLog::where('action', 'batch.status_synced')->count());
    }

    public function test_completed_batches_complete_their_active_enrollments_and_fees_stay_payable(): void
    {
        $this->actingAs($this->admin);
        $ending = $this->batch('Ending', 'ongoing', today()->subMonth(), today()->addWeek());
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Finisher']);
        $enrollment = app(\App\Services\EnrollmentService::class)->enrollStudent($student->id, $ending->id, 0, 2);
        $withdrawn = \App\Models\Enrollment::create(['student_id' => User::factory()->create(['role' => 'student'])->id,
            'batch_id' => $ending->id, 'enrollment_date' => today(), 'total_fee' => 10000, 'status' => 'dropped']);
        $ending->forceFill(['end_date' => today()->subDay()])->save();
        $old = $this->batch('AlreadyDone', 'completed', today()->subYear(), today()->subMonths(6));
        $stale = \App\Models\Enrollment::create(['student_id' => User::factory()->create(['role' => 'student'])->id,
            'batch_id' => $old->id, 'enrollment_date' => today()->subYear(), 'total_fee' => 10000, 'status' => 'active']);

        $this->artisan('batches:sync-status')->assertSuccessful();

        $this->assertSame('completed', $ending->fresh()->status);
        $this->assertSame(['completed', 'dropped', 'completed'], [$enrollment->fresh()->status, $withdrawn->fresh()->status, $stale->fresh()->status]);
        $this->assertSame(2, AuditLog::where('action', 'enrollment.status_synced')->count());
        $this->artisan('batches:sync-status');
        $this->assertSame(2, AuditLog::where('action', 'enrollment.status_synced')->count());

        $payment = app(\App\Services\PaymentService::class)->pay($enrollment->id, '10000', 'cash', (string) \Illuminate\Support\Str::uuid(), $this->admin);
        $this->assertSame('0.00', $enrollment->fresh()->remaining_fee);
        $this->assertSame('completed', $payment->enrollment->fresh()->status);
        $this->get('/batches/'.$ending->id)->assertOk()->assertSee('Finisher')->assertSee('completed');
    }

    public function test_editing_batch_dates_moves_enrollments_with_the_batch(): void
    {
        $this->actingAs($this->admin);
        $batch = $this->batch('Editable', 'ongoing', today()->subMonth(), today()->addMonth());
        $enrollment = \App\Models\Enrollment::create(['student_id' => User::factory()->create(['role' => 'student'])->id,
            'batch_id' => $batch->id, 'enrollment_date' => today(), 'total_fee' => 10000, 'status' => 'active']);
        $data = ['name' => 'Editable', 'course_id' => $this->course->id, 'teacher_id' => $this->teacher->id,
            'start_date' => today()->subMonth()->toDateString(), 'max_students' => 10, 'status' => 'auto', 'meeting_platform' => 'in_person'];

        $this->put('/batches/'.$batch->id, $data + ['end_date' => today()->subDay()->toDateString()])->assertRedirect();
        $this->assertSame(['completed', 'completed'], [$batch->fresh()->status, $enrollment->fresh()->status]);

        $this->put('/batches/'.$batch->id, $data + ['end_date' => today()->addWeek()->toDateString()])->assertRedirect();
        $this->assertSame(['ongoing', 'active'], [$batch->fresh()->status, $enrollment->fresh()->status]);

        $this->put('/batches/'.$batch->id, array_merge($data, ['end_date' => today()->addWeek()->toDateString(), 'status' => 'cancelled']));
        $this->assertSame(['cancelled', 'active'], [$batch->fresh()->status, $enrollment->fresh()->status]);
    }

    public function test_job_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'batches:sync-status'));
        $this->assertCount(1, $events);
        $this->assertSame('5 0 * * *', $events->first()->expression);
    }

    public function test_saving_derives_status_from_dates_and_keeps_manual_cancel(): void
    {
        $this->actingAs($this->admin);
        $data = ['name' => 'Morning', 'course_id' => $this->course->id, 'teacher_id' => $this->teacher->id,
            'start_date' => today()->addDays(5)->toDateString(), 'max_students' => 10,
            'status' => 'auto', 'meeting_platform' => 'in_person'];
        $this->post('/batches', $data)->assertRedirect('/batches');
        $batch = Batch::sole();
        $this->assertSame('upcoming', $batch->status);

        $this->put('/batches/'.$batch->id, array_merge($data, ['start_date' => today()->toDateString()]));
        $this->assertSame('ongoing', $batch->fresh()->status);

        $this->put('/batches/'.$batch->id, array_merge($data, ['status' => 'cancelled']));
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->artisan('batches:sync-status');
        $this->assertSame('cancelled', $batch->fresh()->status);

        $this->post('/batches', array_merge($data, ['status' => 'upcoming', 'start_date' => today()->subDay()->toDateString()]));
        $this->assertSame('ongoing', Batch::latest('id')->first()->status);
    }

    public function test_admin_dashboard_lists_batches_starting_or_ending_within_two_days(): void
    {
        $this->batch('StartsInTwo', 'upcoming', today()->addDays(2));
        $this->batch('StartsInThree', 'upcoming', today()->addDays(3));
        $this->batch('EndsTomorrow', 'ongoing', today()->subMonth(), today()->addDay());
        $this->batch('EndsInThree', 'ongoing', today()->subMonth(), today()->addDays(3));
        $this->batch('CancelledSoon', 'cancelled', today()->addDay());

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('Batch reminders')->assertSee('StartsInTwo')->assertSee('in 2 days')
            ->assertSee('EndsTomorrow')->assertSee('Ends tomorrow')
            ->assertDontSee('StartsInThree')->assertDontSee('EndsInThree')->assertDontSee('CancelledSoon');
    }

    public function test_reminder_panel_hidden_when_empty_and_never_shown_to_teacher_or_student(): void
    {
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertDontSee('Batch reminders');

        $this->batch('StartsTomorrow', 'upcoming', today()->addDay());
        $this->actingAs($this->teacher)->get('/teacher/dashboard')->assertOk()->assertDontSee('Batch reminders');
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get('/student/dashboard')->assertOk()->assertDontSee('Batch reminders');
    }
}
