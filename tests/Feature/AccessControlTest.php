<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private User $otherStudent;

    private Batch $batch;

    private Batch $otherBatch;

    private ClassSession $session;

    private ClassSession $otherSession;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'name' => 'Assigned Student', 'is_active' => true]);
        $this->otherStudent = User::factory()->create(['role' => 'student', 'name' => 'Unrelated Student', 'is_active' => true]);
        $this->batch = $this->makeBatch($this->teacher, 'Assigned Batch');
        $this->otherBatch = $this->makeBatch($this->otherTeacher, 'Unrelated Batch');
        foreach ([[$this->batch, $this->student], [$this->otherBatch, $this->student], [$this->otherBatch, $this->otherStudent]] as [$batch, $student]) {
            Enrollment::create(['batch_id' => $batch->id, 'student_id' => $student->id,
                'enrollment_date' => today()->subDay(), 'status' => 'active', 'total_fee' => 98765, 'paid_amount' => 0]);
        }
        $this->session = $this->makeSession($this->batch);
        $this->otherSession = $this->makeSession($this->otherBatch);
        foreach ([[$this->session, $this->student, 'present'], [$this->otherSession, $this->student, 'absent']] as [$session, $student, $status]) {
            Attendance::create(['class_session_id' => $session->id, 'student_id' => $student->id, 'status' => $status]);
        }
    }

    private function makeBatch(User $teacher, string $name): Batch
    {
        $course = Course::create(['name' => $name.' Course', 'level' => 'A1', 'total_fee' => 98765,
            'teacher_per_student_amount' => 5555, 'duration_weeks' => 12]);

        return Batch::create(['course_id' => $course->id, 'teacher_id' => $teacher->id,
            'name' => $name, 'start_date' => today()->subWeek(), 'max_students' => 20, 'status' => 'ongoing']);
    }

    private function makeSession(Batch $batch): ClassSession
    {
        return ClassSession::create(['batch_id' => $batch->id, 'session_date' => today(),
            'start_time' => '09:00', 'end_time' => '10:00', 'topic' => 'Lesson', 'status' => 'scheduled']);
    }

    public function test_global_reports_require_admin_including_direct_requests(): void
    {
        foreach ([$this->teacher, $this->student] as $user) {
            $this->actingAs($user)->get('/reports')->assertForbidden();
            $this->get('/reports/financial')->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/reports')->assertOk();
    }

    public function test_students_cannot_read_other_students_or_fees_or_change_records(): void
    {
        $this->actingAs($this->student)->get('/students/'.$this->otherStudent->id)->assertForbidden();
        $this->get('/fees/student/'.$this->otherStudent->id)->assertForbidden();
        $this->get('/attendance/student/'.$this->otherStudent->id)->assertForbidden();
        $this->get('/students')->assertForbidden();
        $installment = FeeInstallment::create(['enrollment_id' => $this->student->enrollments()->first()->id,
            'amount' => 100, 'due_date' => today(), 'status' => 'pending']);
        $this->post('/fees/'.$installment->id.'/pay', ['payment_method' => 'cash'])->assertForbidden();
        $this->put('/students/'.$this->student->id, ['role' => 'admin'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->student->id, 'role' => 'student']);
        $this->get('/fees/student/'.$this->student->id)->assertOk();
    }

    public function test_teacher_roster_and_student_detail_exclude_unrelated_records_and_finances(): void
    {
        $this->actingAs($this->teacher)->get('/students')->assertOk()
            ->assertSee('Assigned Student')->assertDontSee('Unrelated Student')
            ->assertDontSee('98765')->assertViewHas('students', function ($students) {
                return $students->count() === 1 && array_keys($students->first()->getAttributes()) === ['id', 'name'];
            });
        $this->get('/students/'.$this->student->id)->assertOk()->assertSee('Assigned Batch')
            ->assertDontSee('Unrelated Batch')->assertDontSee('98765')
            ->assertViewHas('enrollments', fn ($rows) => $rows->count() === 1 && ! array_key_exists('total_fee', $rows->first()->getAttributes()));
        $this->get('/students/'.$this->otherStudent->id)->assertForbidden();
        $this->get('/fees/student/'.$this->student->id)->assertForbidden();
        $this->post('/students', [])->assertForbidden();
        $this->put('/students/'.$this->student->id, [])->assertForbidden();
    }

    public function test_teacher_courses_and_batches_are_scoped_and_do_not_expose_fees(): void
    {
        $this->actingAs($this->teacher)->get('/courses')->assertOk()->assertDontSee('Unrelated Batch')->assertDontSee('98765');
        $this->get('/courses/'.$this->otherBatch->course_id)->assertForbidden();
        $this->get('/courses/'.$this->batch->course_id)->assertOk()->assertDontSee('Unrelated Batch');
        $this->get('/batches')->assertOk()->assertDontSee('Unrelated Batch');
        $this->get('/batches/'.$this->batch->id)->assertOk()->assertSee('Assigned Student')->assertDontSee('98765');
        $this->get('/batches/'.$this->otherBatch->id)->assertForbidden();
    }

    public function test_attendance_reports_and_aggregates_only_include_assigned_batches(): void
    {
        $this->actingAs($this->teacher)->get('/attendance')->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total_sessions'] === 1 && $stats['average_attendance'] === 100.0);
        $this->get('/attendance/reports')->assertOk()->assertSee('Assigned Batch')->assertDontSee('Unrelated Batch');
        $this->get('/attendance/student/'.$this->student->id)->assertOk()->assertDontSee('Unrelated Batch');
        $this->get('/attendance/student/'.$this->otherStudent->id)->assertForbidden();
        $this->get('/attendance/'.$this->otherSession->id)->assertForbidden();
    }

    public function test_teacher_cannot_mark_another_batch_through_either_endpoint(): void
    {
        $this->actingAs($this->teacher)->post('/attendance/mark', ['session_id' => $this->otherSession->id,
            'attendance' => [['student_id' => $this->student->id, 'status' => 'present']]])->assertForbidden();
        $this->post('/class-sessions/'.$this->otherSession->id.'/attendance',
            ['attendance' => [$this->student->id => 'present']])->assertForbidden();
        $this->assertDatabaseHas('attendances', ['class_session_id' => $this->otherSession->id, 'status' => 'absent']);
    }

    public function test_attendance_rejects_nonmembers_atomically_on_both_endpoints(): void
    {
        $this->actingAs($this->teacher)->postJson('/attendance/mark', ['session_id' => $this->session->id,
            'attendance' => [['student_id' => $this->student->id, 'status' => 'absent'],
                ['student_id' => $this->otherStudent->id, 'status' => 'present']]])->assertUnprocessable();
        $this->postJson('/class-sessions/'.$this->session->id.'/attendance',
            ['attendance' => [$this->student->id => 'absent', $this->otherStudent->id => 'present']])->assertUnprocessable();
        $this->assertDatabaseHas('attendances', ['class_session_id' => $this->session->id, 'student_id' => $this->student->id, 'status' => 'present']);
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_authorized_teacher_can_mark_eligible_student(): void
    {
        $this->actingAs($this->teacher)->post('/attendance/mark', ['session_id' => $this->session->id,
            'reason' => 'Corrected check-in status',
            'attendance' => [['student_id' => $this->student->id, 'status' => 'late']]])->assertRedirect();
        $this->assertDatabaseHas('attendances', ['class_session_id' => $this->session->id, 'student_id' => $this->student->id, 'status' => 'late']);
    }

    public function test_student_filter_scopes_attendance_sessions_and_cards_without_exposing_other_teacher_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/attendance?student_id='.$this->otherStudent->id)->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1 && $sessions->first()->id === $this->otherSession->id)
            ->assertViewHas('stats', fn ($stats) => $stats['total_sessions'] === 1);
        $this->actingAs($this->teacher)->get('/attendance?student_id='.$this->otherStudent->id)->assertOk()
            ->assertDontSee('Unrelated Student')->assertDontSee('Unrelated Batch')
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1 && $sessions->first()->id === $this->session->id);
        $this->get('/attendance?from='.today()->addDay()->toDateString())->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->isEmpty())
            ->assertViewHas('stats', fn ($stats) => $stats['total_sessions'] === 0 && $stats['average_attendance'] === null);
    }

    public function test_student_overview_defaults_to_today_and_counts_only_recorded_attendance(): void
    {
        foreach (['late', 'absent', 'excused'] as $status) {
            $session = $this->makeSession($this->batch);
            Attendance::create(['class_session_id' => $session->id, 'student_id' => $this->student->id, 'status' => $status]);
        }
        $this->makeSession($this->batch); // Pending marking is not an absence.
        $past = $this->makeSession($this->batch);
        $past->update(['session_date' => today()->subDay()]);
        Attendance::create(['class_session_id' => $past->id, 'student_id' => $this->student->id, 'status' => 'present']);

        $this->actingAs($this->teacher)->get('/attendance')->assertOk()
            ->assertDontSee('Attendance date range')->assertDontSee('This week')->assertDontSee('Custom dates')
            ->assertSee('1 class needs attendance marking.')->assertSee('Excused: 1.')
            ->assertViewHas('stats', fn ($stats) => $stats['present'] === 1 && $stats['late'] === 1
                && $stats['absent'] === 1 && $stats['excused'] === 1 && $stats['unmarked_sessions'] === 1
                && $stats['average_attendance'] === 66.7);
        $this->get('/attendance?from='.today()->subDay()->toDateString().'&to='.today()->toDateString())->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['present'] === 2 && $stats['average_attendance'] === 75.0);
        $this->get('/attendance?from='.today()->toDateString().'&to='.today()->subDay()->toDateString())
            ->assertSessionHasErrors('to');
    }

    public function test_device_operations_reject_unassigned_session_before_any_network_call(): void
    {
        $this->actingAs($this->teacher);
        foreach (['start', 'end', 'sync'] as $action) {
            $this->post('/attendance/biometric/'.$action, ['session_id' => $this->otherSession->id,
                'start_time' => now()->subHour()->toDateTimeString(), 'end_time' => now()->toDateTimeString()])->assertNotFound();
        }
        $this->post('/attendance/auto-absent', ['session_id' => $this->otherSession->id])->assertNotFound();
        $this->get('/attendance/biometric/test')->assertNotFound();
        Http::assertNothingSent();
    }
}
