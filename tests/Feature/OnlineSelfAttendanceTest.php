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
}
