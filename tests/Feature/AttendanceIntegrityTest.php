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
use Tests\TestCase;

class AttendanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_excused_and_cancelled_classes_are_excluded_and_missing_marks_are_not_absences(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 100, 'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $batch = Batch::create(['name' => 'Morning', 'teacher_id' => $teacher->id, 'course_id' => $course->id,
            'start_date' => today(), 'max_students' => 10, 'status' => 'ongoing']);
        Enrollment::create(['student_id' => $student->id, 'batch_id' => $batch->id, 'enrollment_date' => today(), 'total_fee' => 100]);
        $sessions = [];
        foreach (['present', 'late', 'absent', 'excused', 'cancelled', 'unmarked'] as $status) {
            $session = ClassSession::create(['batch_id' => $batch->id, 'session_date' => today(), 'start_time' => '09:00', 'end_time' => '10:00',
                'topic' => $status, 'status' => $status === 'cancelled' ? 'cancelled' : 'completed']);
            $sessions[] = $session;
            if ($status !== 'unmarked') {
                Attendance::create(['student_id' => $student->id, 'class_session_id' => $session->id, 'status' => $status === 'cancelled' ? 'absent' : $status]);
            }
        }
        $summary = Attendance::getAttendanceStats($student->id);
        $this->assertSame(4, $summary['total']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(66.7, $summary['attendance_rate']);
        $this->actingAs($student)->get('/student/dashboard')->assertOk()->assertViewHas('unmarked', 1);
        $this->get('/student/attendance')->assertOk()->assertDontSee('cancelled');
        $this->actingAs($teacher)->postJson('/attendance/mark', ['session_id' => $sessions[0]->id,
            'attendance' => [['student_id' => $student->id, 'status' => 'absent']]])->assertUnprocessable();
        $this->post('/attendance/mark', ['session_id' => $sessions[0]->id, 'reason' => 'Register corrected after review',
            'attendance' => [['student_id' => $student->id, 'status' => 'absent']]])->assertRedirect();
        $this->assertSame(1, AuditLog::where('action', 'attendance.corrected')->count());
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Enrollment::create(['student_id' => $otherStudent->id, 'batch_id' => $batch->id, 'enrollment_date' => today(), 'total_fee' => 100]);
        $this->post('/class-sessions/'.$sessions[0]->id.'/attendance', ['attendance' => [$student->id => 'absent', $otherStudent->id => '']])->assertRedirect();
        $this->assertDatabaseMissing('attendances', ['student_id' => $otherStudent->id, 'class_session_id' => $sessions[0]->id]);
        Enrollment::where('student_id', $student->id)->update(['status' => 'dropped', 'dropout_date' => today()]);
        $this->get('/class-sessions/'.$sessions[0]->id.'/attendance')->assertOk()->assertSee($student->name);
    }
}
