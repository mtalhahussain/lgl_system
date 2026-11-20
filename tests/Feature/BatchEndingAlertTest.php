<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BatchEndingAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', config('app.timezone')));
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['name' => 'Ali Raza', 'role' => 'teacher', 'is_active' => true]);
        $this->course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function batch(string $name, string $start, string $end, string $status = 'ongoing'): Batch
    {
        return Batch::create(['name' => $name, 'course_id' => $this->course->id, 'teacher_id' => $this->teacher->id,
            'start_date' => $start, 'end_date' => $end, 'status' => $status, 'max_students' => 20]);
    }

    public function test_dashboard_shows_teacher_slots_for_batches_ending_within_a_week(): void
    {
        $ending = $this->batch('Batch B-12', '2026-10-01', '2026-11-23');
        BatchSchedule::create(['batch_id' => $ending->id, 'weekday' => 'wed', 'start_time' => '18:00', 'end_time' => '20:00']);
        BatchSchedule::create(['batch_id' => $ending->id, 'weekday' => 'mon', 'start_time' => '09:00', 'end_time' => '10:30']);
        $this->batch('Batch Far', '2026-10-01', '2026-12-31');
        $this->batch('Batch Done', '2026-09-01', '2026-11-20', 'completed');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Teacher slots freeing up')->assertSee('Batch B-12')->assertSee('Ends in 5 days')
            ->assertSee('Mon 9:00 AM–10:30 AM, Wed 6:00 PM–8:00 PM')
            ->assertSee('No next batch assigned')->assertSee('Assign next batch')
            ->assertSee(route('batches.create', ['teacher_id' => $this->teacher->id, 'course_id' => $this->course->id]))
            ->assertDontSee('Batch Far')->assertDontSee('Batch Done');

        // Assigning the next batch during the final week is allowed while the current one is still ongoing.
        $this->batch('Batch B-13', '2026-11-25', '2027-01-31', 'upcoming');
        $this->get(route('admin.dashboard'))->assertSee('Next: Batch B-13 · starts 25 Nov')->assertDontSee('Assign next batch');
    }

    public function test_admins_are_notified_once_per_batch_without_a_next_batch(): void
    {
        $ending = $this->batch('Batch B-12', '2026-10-01', '2026-11-20');
        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $covered = Batch::create(['name' => 'Batch C', 'course_id' => $this->course->id, 'teacher_id' => $other->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-11-21', 'status' => 'ongoing', 'max_students' => 20]);
        Batch::create(['name' => 'Batch C2', 'course_id' => $this->course->id, 'teacher_id' => $other->id,
            'start_date' => '2026-11-22', 'end_date' => '2027-01-31', 'status' => 'upcoming', 'max_students' => 20]);

        $this->artisan('batches:ending-alerts')->expectsOutput('Batch ending alerts sent: 1')->assertSuccessful();
        $this->artisan('batches:ending-alerts')->expectsOutput('Batch ending alerts sent: 0')->assertSuccessful();

        $notification = $this->admin->notifications()->sole();
        $this->assertSame('batch_ending', $notification->data['type']);
        $this->assertSame("Ali Raza's batch ends in 2 days", $notification->data['title']);
        $this->assertSame($ending->id, $notification->data['meta']['batch_id']);
        $this->assertNotSame($covered->id, $notification->data['meta']['batch_id']);
    }

    public function test_new_batch_form_preselects_the_teacher_from_the_alert_link(): void
    {
        User::factory()->create(['name' => 'Aaa First', 'role' => 'teacher', 'is_active' => true]);
        $this->actingAs($this->admin)->get(route('batches.create', ['teacher_id' => $this->teacher->id]))->assertOk()
            ->assertSee('<option value="'.$this->teacher->id.'" selected>', false);
    }
}
