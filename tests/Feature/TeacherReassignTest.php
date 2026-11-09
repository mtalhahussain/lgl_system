<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherReassignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $leaving;

    private User $target;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->leaving = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->target = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 10000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
    }

    private function batch(string $name, string $status, ?int $teacherId = null): Batch
    {
        return Batch::create(['name' => $name, 'course_id' => $this->course->id,
            'teacher_id' => $teacherId ?? $this->leaving->id,
            'start_date' => today()->subWeek(), 'end_date' => today()->addMonth(),
            'max_students' => 10, 'status' => $status]);
    }

    public function test_reassign_moves_only_active_and_upcoming_batches(): void
    {
        $up = $this->batch('Upcoming', 'upcoming');
        $on = $this->batch('Ongoing', 'ongoing');
        $done = $this->batch('Done', 'completed');
        $cancelled = $this->batch('Cancelled', 'cancelled');

        $this->actingAs($this->admin)
            ->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $this->target->id, 'reason' => 'Teacher resigned'])
            ->assertRedirect(route('teachers.show', $this->leaving));

        $this->assertSame($this->target->id, $up->fresh()->teacher_id);
        $this->assertSame($this->target->id, $on->fresh()->teacher_id);
        $this->assertSame($this->leaving->id, $done->fresh()->teacher_id, 'Completed batch keeps the original teacher.');
        $this->assertSame($this->leaving->id, $cancelled->fresh()->teacher_id, 'Cancelled batch keeps the original teacher.');
        $this->assertTrue($this->leaving->fresh()->is_active, 'Teacher stays active without the deactivate option.');
        $this->assertSame(2, AuditLog::where('action', 'batch.teacher_reassigned')->count());
    }

    public function test_reassign_can_deactivate_the_leaving_teacher(): void
    {
        $this->batch('Ongoing', 'ongoing');

        $this->actingAs($this->admin)
            ->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $this->target->id, 'deactivate' => 1])
            ->assertRedirect(route('teachers.show', $this->leaving));

        $this->assertFalse($this->leaving->fresh()->is_active);
    }

    public function test_reassign_rejects_inactive_or_same_target(): void
    {
        $this->batch('Ongoing', 'ongoing');
        $inactive = User::factory()->create(['role' => 'teacher', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $inactive->id])
            ->assertSessionHasErrors('new_teacher_id');

        $this->actingAs($this->admin)
            ->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $this->leaving->id])
            ->assertSessionHasErrors('new_teacher_id');
    }

    public function test_reassign_errors_when_no_open_batches(): void
    {
        $this->batch('Done', 'completed');

        $this->actingAs($this->admin)
            ->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $this->target->id])
            ->assertSessionHasErrors('new_teacher_id');
    }

    public function test_teacher_cannot_reassign(): void
    {
        $this->batch('Ongoing', 'ongoing');

        $this->actingAs($this->target)
            ->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $this->target->id])
            ->assertForbidden();
    }

    public function test_deactivation_blocked_until_batches_reassigned_via_users_page(): void
    {
        $this->batch('Ongoing', 'ongoing');

        // Users page (AccountController) refuses while the teacher owns an open batch.
        $this->actingAs($this->admin)
            ->patch(route('accounts.update', $this->leaving), ['role' => 'teacher', 'is_active' => 0])
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($this->leaving->fresh()->is_active);

        // After reassigning, deactivation succeeds.
        $this->actingAs($this->admin)->post(route('teachers.reassign', $this->leaving), ['new_teacher_id' => $this->target->id]);
        $this->actingAs($this->admin)
            ->patch(route('accounts.update', $this->leaving), ['role' => 'teacher', 'is_active' => 0])
            ->assertSessionDoesntHaveErrors('is_active');
        $this->assertFalse($this->leaving->fresh()->is_active);
    }

    public function test_deactivation_blocked_on_teacher_destroy(): void
    {
        $this->batch('Ongoing', 'ongoing');

        $this->actingAs($this->admin)
            ->delete(route('teachers.destroy', $this->leaving))
            ->assertRedirect(route('teachers.show', $this->leaving));
        $this->assertTrue($this->leaving->fresh()->is_active);
    }
}
