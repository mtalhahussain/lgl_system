<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OnlineSelfAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Batch $batch;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:55:00', config('app.timezone')));
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->batch = Batch::create(['name' => 'A1 Online', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20, 'meeting_platform' => 'zoom', 'meeting_link' => 'https://zoom.us/j/123']);
        Enrollment::create(['student_id' => $this->student->id, 'batch_id' => $this->batch->id, 'enrollment_date' => '2026-10-01', 'total_fee' => 30000, 'status' => 'active']);
        $this->session = ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '11:00', 'topic' => 'Greetings', 'status' => 'scheduled']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_window_state_present_late_and_closed(): void
    {
        // 08:55 is inside [08:45, 09:10] -> present
        $this->assertSame(['open' => true, 'status' => 'present'], collect($this->session->selfJoinState())->only(['open', 'status'])->all());

        Carbon::setTestNow(Carbon::parse('2026-11-10 09:30:00', config('app.timezone')));
        $this->assertSame('late', $this->session->selfJoinState()['status']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 08:40:00', config('app.timezone')));
        $this->assertFalse($this->session->selfJoinState()['open']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 11:05:00', config('app.timezone')));
        $this->assertFalse($this->session->selfJoinState()['open']);
    }

    public function test_self_mark_present_persists_source_and_audit(): void
    {
        $result = app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);

        $this->assertSame('present', $result['status']);
        $row = Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->sole();
        $this->assertSame('present', $row->status);
        $this->assertSame('self_online', $row->source);
        $this->assertNotNull($row->check_in_time);
        // Session is not completed by a self-mark.
        $this->assertSame('scheduled', $this->session->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.self_marked', 'actor_id' => $this->student->id]);
    }

    public function test_self_mark_late_after_grace(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:30:00', config('app.timezone')));
        $result = app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);
        $this->assertSame('late', $result['status']);
    }

    public function test_self_mark_blocked_before_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:40:00', config('app.timezone')));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);
    }

    public function test_self_mark_blocked_for_non_online_batch(): void
    {
        $this->batch->update(['meeting_platform' => 'in_person']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session->fresh(), $this->student);
    }

    public function test_self_mark_blocked_for_non_enrolled_student(): void
    {
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $other);
    }

    public function test_self_mark_is_idempotent_and_never_overwrites(): void
    {
        // Teacher already marked the student absent.
        Attendance::create(['class_session_id' => $this->session->id, 'student_id' => $this->student->id, 'status' => 'absent']);
        $result = app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student);

        $this->assertTrue($result['already']);
        $this->assertSame(1, Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->count());
        $this->assertSame('absent', Attendance::where('class_session_id', $this->session->id)->where('student_id', $this->student->id)->sole()->status);
    }

    public function test_self_mark_forbidden_for_inactive_student(): void
    {
        $this->student->update(['is_active' => false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\AttendanceService::class)->selfMarkOnline($this->session, $this->student->fresh());
    }

    public function test_join_route_marks_present_and_redirects_to_meeting(): void
    {
        $response = $this->actingAs($this->student)->post(route('student.session.join', $this->session));

        $response->assertRedirect('https://zoom.us/j/123');
        $this->assertDatabaseHas('attendances', ['class_session_id' => $this->session->id, 'student_id' => $this->student->id, 'status' => 'present', 'source' => 'self_online']);
    }

    public function test_join_route_out_of_window_redirects_back_with_error_and_no_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:40:00', config('app.timezone')));
        $response = $this->actingAs($this->student)->from(route('student.dashboard'))->post(route('student.session.join', $this->session));

        $response->assertRedirect(route('student.dashboard'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', ['class_session_id' => $this->session->id, 'student_id' => $this->student->id]);
    }

    public function test_join_route_rejects_teacher_role(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->post(route('student.session.join', $this->session))->assertForbidden();
    }
}
