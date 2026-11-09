<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTeachingTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $admin;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(18, 0));
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 10000, 'duration_weeks' => 12, 'teacher_per_student_amount' => 0]);
        $this->batch = Batch::create(['name' => 'Morning', 'teacher_id' => $this->teacher->id, 'course_id' => $course->id,
            'start_date' => today()->subMonth(), 'end_date' => today()->addMonth(), 'max_students' => 10, 'status' => 'ongoing', 'meeting_platform' => 'in_person']);
        $this->actingAs($this->teacher);
    }

    private function classRecord(array $overrides = []): ClassSession
    {
        return ClassSession::create(array_merge(['batch_id' => $this->batch->id, 'session_date' => today(), 'start_time' => '09:00', 'end_time' => '10:00', 'topic' => 'German class', 'status' => 'scheduled'], $overrides));
    }

    private function makeupData(ClassSession $source): array
    {
        return ['batch_id' => $source->batch_id, 'session_date' => today()->addDay()->toDateString(),
            'start_time' => '11:00', 'end_time' => '12:00', 'topic' => 'Makeup German', 'makeup_for_id' => $source->id];
    }

    public function test_class_detail_times_use_am_pm_without_changing_stored_values(): void
    {
        $session = $this->classRecord(['start_time' => '12:00', 'end_time' => '23:30',
            'teaching_started_at' => '2026-11-05 23:23:00']);
        $this->get(route('class-sessions.show', $session))->assertOk()
            ->assertSee('Start recorded: 05 Nov 2026 11:23 PM')
            ->assertSee('12:00 PM')->assertSee('11:30 PM')
            ->assertDontSee('05 Nov 2026 23:23');
        $this->assertSame('23:23', $session->fresh()->teaching_started_at->format('H:i'));
    }

    public function test_taken_is_separate_from_attendance_records_actor_and_is_idempotent(): void
    {
        $session = $this->classRecord(['status' => 'completed']); // Existing student register may already mark the session completed.
        $this->assertSame('Not confirmed', $session->teachingLabel());
        $this->post(route('class-sessions.end', $session))->assertSessionHasNoErrors()->assertRedirect();
        $session->refresh();
        $recordedAt = $session->teaching_recorded_at->toDateTimeString();
        $this->assertSame('taken', $session->teaching_status);
        $this->assertSame($this->teacher->id, $session->teaching_recorded_by);
        $this->assertSame(0, Attendance::count());
        $this->travel(1)->hours();
        $this->actingAs($this->admin)->post(route('class-sessions.end', $session))->assertSessionHasNoErrors();
        $this->assertSame($recordedAt, $session->fresh()->teaching_recorded_at->toDateTimeString());
        $this->assertSame($this->teacher->id, $session->fresh()->teaching_recorded_by);
        $this->assertSame(1, AuditLog::where('action', 'class.taken')->count());
        $this->get(route('class-sessions.show', $session))->assertOk()->assertSee('Taken')->assertSee($this->teacher->name);
        $this->delete(route('class-sessions.destroy', $session))->assertSessionHasErrors('teaching');
    }

    public function test_missed_makeup_flow_preserves_original_and_marks_covered_only_after_teacher_confirmation(): void
    {
        $source = $this->classRecord();
        $this->post(route('class-sessions.missed', $source), ['reason' => 'Teacher on leave'])->assertSessionHasNoErrors();
        $source->refresh();
        $this->assertSame('cancelled', $source->status);
        $this->assertSame('Missed · Makeup needed', $source->teachingLabel());
        $this->get(route('class-sessions.create', ['makeup_for_id' => $source->id]))->assertOk()->assertSee('original missed class stays on record', false);
        $this->post(route('class-sessions.store'), $this->makeupData($source))->assertSessionHasNoErrors();
        $makeup = ClassSession::where('makeup_for_id', $source->id)->sole();
        $this->assertSame('Missed · Makeup pending', $source->fresh()->teachingLabel());
        $this->post(route('class-sessions.store'), $this->makeupData($source))->assertSessionHasErrors('makeup_for_id');
        $this->travel(1)->days();
        $makeup->update(['status' => 'completed']); // A filled student register alone must not cover the missed class.
        $this->assertSame('Missed · Makeup pending', $source->fresh()->teachingLabel());
        $this->post(route('class-sessions.end', $makeup))->assertSessionHasNoErrors();
        $this->assertSame('Missed · Covered', $source->fresh()->teachingLabel());
        $this->assertSame('missed', $source->fresh()->teaching_status);
        $this->assertSame('Teacher on leave', $source->fresh()->teaching_reason);
        $this->assertSame('2026-11-05', $source->fresh()->session_date->toDateString());
        $this->get(route('class-sessions.index', ['from' => '2026-11-02', 'to' => '2026-11-08']))->assertOk()->assertSee('Missed · Covered');
        $this->get(route('class-sessions.show', $source))->assertOk()->assertSee('Makeup:');
        $this->get(route('class-sessions.show', $makeup))->assertOk()->assertSee('Original missed class:');
    }

    public function test_cancelled_makeup_can_be_replaced_but_recorded_classes_cannot_be_edited(): void
    {
        $source = $this->classRecord();
        $this->post(route('class-sessions.missed', $source), ['reason' => 'Teacher on leave']);
        $this->post(route('class-sessions.store'), $this->makeupData($source));
        $first = ClassSession::where('makeup_for_id', $source->id)->sole();
        $this->put(route('class-sessions.update', $source), $this->makeupData($source))->assertSessionHasErrors('teaching');
        $this->put(route('class-sessions.update', $first), $this->makeupData($source))->assertSessionHasErrors('teaching');
        $this->delete(route('class-sessions.destroy', $first))->assertSessionHasNoErrors();
        $this->post(route('class-sessions.store'), $this->makeupData($source))->assertSessionHasNoErrors();
        $this->assertSame(2, $source->makeupClasses()->count());
        $this->assertSame('cancelled', $first->fresh()->status);
    }

    public function test_role_and_batch_scope_apply_to_recording_and_makeup_links(): void
    {
        $source = $this->classRecord();
        $this->post(route('class-sessions.missed', $source), ['reason' => 'Teacher on leave']);
        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->get(route('class-sessions.create', ['makeup_for_id' => $source->id]))->assertForbidden();
        $this->post(route('class-sessions.end', $source))->assertForbidden();
        $this->post(route('class-sessions.missed', $source), ['reason' => 'Other reason'])->assertForbidden();
        $this->post(route('class-sessions.store'), $this->makeupData($source))->assertForbidden();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->post(route('class-sessions.end', $source))->assertForbidden();
        $this->post(route('class-sessions.missed', $source), ['reason' => 'Other reason'])->assertForbidden();
        $this->get(route('class-sessions.index'))->assertForbidden();
    }

    public function test_invalid_outcomes_attendance_conflicts_and_wrong_batch_makeup_are_rejected(): void
    {
        $future = $this->classRecord(['session_date' => today()->addDay()]);
        $this->post(route('class-sessions.end', $future))->assertSessionHasErrors('teaching');
        $this->post(route('class-sessions.missed', $future), ['reason' => 'Teacher on leave'])->assertSessionHasErrors('teaching');
        $source = $this->classRecord();
        $this->post(route('class-sessions.missed', $source), ['reason' => 'no'])->assertSessionHasErrors('reason');
        $student = User::factory()->create(['role' => 'student']);
        Attendance::create(['class_session_id' => $source->id, 'student_id' => $student->id, 'status' => 'present']);
        $this->post(route('class-sessions.missed', $source), ['reason' => 'Teacher on leave'])->assertSessionHasErrors('teaching');
        $missed = $this->classRecord(['start_time' => '13:00']);
        $this->post(route('class-sessions.missed', $missed), ['reason' => 'Teacher on leave']);
        $other = $this->batch->replicate();
        $other->name = 'Other batch';
        $other->save();
        $data = $this->makeupData($missed);
        $data['batch_id'] = $other->id;
        $this->post(route('class-sessions.store'), $data)->assertSessionHasErrors('makeup_for_id');
        $this->assertSame(0, ClassSession::whereNotNull('makeup_for_id')->count());
    }

    public function test_start_records_time_for_both_platforms_without_claiming_class_taken(): void
    {
        $session = $this->classRecord();
        $this->post(route('class-sessions.start', $session))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($session->fresh()->teaching_started_at);
        $this->assertNull($session->fresh()->teaching_status);
        $this->batch->update(['meeting_platform' => 'zoom']);
        $online = $this->classRecord(['meeting_link' => 'https://example.org/meeting']);
        $this->post(route('class-sessions.start', $online))->assertRedirect('https://example.org/meeting');
        $this->assertNull($online->fresh()->teaching_status);
    }
}
