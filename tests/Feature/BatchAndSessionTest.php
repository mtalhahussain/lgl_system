<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchAndSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 10000,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
    }

    private function batchData(): array
    {
        return ['name' => 'Morning', 'teacher_id' => $this->teacher->id, 'course_id' => $this->course->id,
            'start_date' => today()->toDateString(), 'end_date' => today()->addWeeks(12)->toDateString(), 'max_students' => 10,
            'status' => 'ongoing', 'meeting_platform' => 'in_person'];
    }

    private function sessionData(Batch $batch): array
    {
        return ['batch_id' => $batch->id, 'session_date' => today()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'topic' => 'Introduction'];
    }

    public function test_admin_batch_forms_create_and_update_without_salary_side_effects(): void
    {
        $this->get('/batches/create')->assertOk();
        $this->post('/batches', $this->batchData())->assertRedirect('/batches');
        $batch = Batch::sole();
        $this->assertDatabaseCount('batch_teacher_earnings', 0);
        $this->get('/batches/'.$batch->id)->assertOk();
        $this->get('/batches/'.$batch->id.'/edit')->assertOk();
        $this->assertSame('ongoing', $batch->status);
        $this->put('/batches/'.$batch->id, array_merge($this->batchData(), ['status' => 'auto',
            'start_date' => today()->subMonth()->toDateString(), 'end_date' => today()->subDay()->toDateString()]))->assertRedirect('/batches/'.$batch->id);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_batch_dates_are_required_and_end_cannot_precede_start(): void
    {
        $data = array_replace($this->batchData(), ['start_date' => '2026-11-10', 'end_date' => '2026-11-10']);
        foreach ([null, '', '2026-10-31', '2026-11-09', '2025-12-31', 'invalid'] as $end) {
            $this->postJson('/batches', array_replace($data, ['end_date' => $end]))
                ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        }
        $this->postJson('/batches', array_replace($data, ['start_date' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertDatabaseCount('batches', 0);
        $this->post('/batches', $data)->assertRedirect('/batches');
        $batch = Batch::sole();
        $this->putJson('/batches/'.$batch->id, array_replace($data, ['end_date' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->putJson('/batches/'.$batch->id, array_replace($data, ['end_date' => '2026-10-31']))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->put('/batches/'.$batch->id, array_replace($data, ['end_date' => '2027-01-05']))
            ->assertRedirect('/batches/'.$batch->id);
    }

    public function test_batch_rejects_non_teacher_and_inactive_teacher(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->postJson('/batches', array_merge($this->batchData(), ['teacher_id' => $student->id]))->assertUnprocessable();
        $this->teacher->update(['is_active' => false]);
        $this->postJson('/batches', $this->batchData())->assertUnprocessable();
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_assigned_teacher_session_workflow_and_cancellation_preserve_attendance(): void
    {
        $batch = Batch::create($this->batchData());
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id,
            'enrollment_date' => today(), 'total_fee' => 10000]);
        $this->actingAs($this->teacher)->get('/class-sessions/create')->assertOk()->assertSee('Morning');
        $this->post('/class-sessions', $this->sessionData($batch))->assertRedirect('/class-sessions');
        $session = ClassSession::sole();
        $this->get('/class-sessions')->assertOk()->assertSee('Introduction');
        $this->get('/class-sessions/'.$session->id)->assertOk();
        $this->get('/class-sessions/'.$session->id.'/edit')->assertOk();
        $this->put('/class-sessions/'.$session->id, array_merge($this->sessionData($batch), ['topic' => 'Updated']))->assertRedirect('/class-sessions');
        $this->get('/class-sessions/'.$session->id.'/attendance')->assertOk()->assertSee($student->name);
        $this->post('/class-sessions/'.$session->id.'/attendance', ['attendance' => [$student->id => 'present']])->assertRedirect();
        $this->get('/attendance/'.$session->id)->assertOk();
        $this->delete('/class-sessions/'.$session->id)->assertRedirect('/class-sessions');
        $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('attendances', ['class_session_id' => $session->id, 'student_id' => $student->id]);
        $this->postJson('/class-sessions/'.$session->id.'/attendance', ['attendance' => [$student->id => 'absent']])->assertUnprocessable();
    }

    public function test_existing_attendance_cannot_be_moved_to_another_batch(): void
    {
        $batch = Batch::create($this->batchData());
        $other = Batch::create(array_merge($this->batchData(), ['name' => 'Evening']));
        $student = User::factory()->create(['role' => 'student']);
        $session = ClassSession::create($this->sessionData($batch));
        Attendance::create(['class_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present']);
        $this->putJson('/class-sessions/'.$session->id, $this->sessionData($other))->assertUnprocessable();
        $this->assertSame($batch->id, $session->fresh()->batch_id);
    }
}
