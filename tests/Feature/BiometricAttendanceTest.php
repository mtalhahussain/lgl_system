<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BiometricAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private BiometricDevice $device;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', config('app.timezone')));
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'name' => 'Teacher Tina']);
        $this->teacher->forceFill(['device_employee_no' => '900'])->save();
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Student Sara']);
        $this->student->forceFill(['device_employee_no' => '42'])->save();
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $batch = Batch::create(['name' => 'A1 Morning 9-11', 'course_id' => $course->id, 'teacher_id' => $this->teacher->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'ongoing', 'max_students' => 20]);
        Enrollment::create(['student_id' => $this->student->id, 'batch_id' => $batch->id, 'enrollment_date' => '2026-10-01',
            'total_fee' => 30000, 'status' => 'active']);
        $this->session = ClassSession::create(['batch_id' => $batch->id, 'session_date' => '2026-11-03', 'start_time' => '09:00',
            'end_time' => '11:00', 'topic' => 'Greetings', 'status' => 'scheduled']);
        $this->device = BiometricDevice::create(['name' => 'Main gate', 'timezone' => 'Asia/Karachi', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function event(string $employee, string $time, int $sub = 75): array
    {
        return ['ipAddress' => '192.168.1.64', 'dateTime' => $time, 'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => ['majorEventType' => 5, 'subEventType' => $sub, 'employeeNoString' => $employee, 'attendanceStatus' => 'checkIn']];
    }

    private function push(array $event, ?string $token = null)
    {
        return $this->call('POST', '/biometric/hook/'.($token ?? $this->device->webhook_token), [], [], [],
            ['CONTENT_TYPE' => 'application/json'], json_encode($event));
    }

    public function test_student_punch_before_class_marks_present_once_and_never_overwrites(): void
    {
        $this->push($this->event('42', '2026-11-03T08:40:00'))->assertOk()->assertSee('OK');
        $this->push($this->event('42', '2026-11-03T08:40:00'))->assertOk();
        $punch = BiometricPunch::sole();
        $this->assertSame(['present', $this->student->id, $this->session->id], [$punch->result, $punch->user_id, $punch->class_session_id]);
        $record = Attendance::sole();
        $this->assertSame(['present', '08:40'], [$record->status, $record->check_in_time->format('H:i')]);
        $this->assertTrue((bool) $record->auto_marked);
        $this->assertSame(1, AuditLog::where('action', 'attendance.device_marked')->whereNull('actor_id')->count());
        $this->assertNotNull($this->device->fresh()->last_seen_at);

        // A later punch in the same class leaves the record alone, even after a manual correction.
        $record->update(['status' => 'excused']);
        $this->push($this->event('42', '2026-11-03T09:50:00'))->assertOk();
        $this->assertSame('already_marked', BiometricPunch::latest('id')->first()->result);
        $this->assertSame('excused', $record->fresh()->status);
        $this->assertSame(2, BiometricPunch::count());

        $this->actingAs($this->admin)->get('/class-sessions/'.$this->session->id)->assertOk()->assertSee('Machine 08:40 AM');
    }

    public function test_late_after_threshold_from_multipart_push_with_offset_and_picture(): void
    {
        $this->post('/biometric/hook/'.$this->device->webhook_token, [
            'event_log' => json_encode($this->event('42', '2026-11-03T09:20:00+05:00')),
            'Picture' => UploadedFile::fake()->image('face.jpg'),
        ])->assertOk();
        $this->assertSame('late', Attendance::sole()->status);
        $this->assertSame('2026-11-03 09:20:00', BiometricPunch::sole()->punched_at->format('Y-m-d H:i:s'));
    }

    public function test_punches_outside_class_unknown_denied_teacher_and_clock_errors_never_mark_attendance(): void
    {
        $this->push($this->event('42', '2026-11-03T08:20:00'))->assertOk();      // before the 30-minute window
        $this->push($this->event('42', '2026-11-03T08:35:00', 76))->assertOk();  // failed face: not stored
        $this->push($this->event('777', '2026-11-03T09:05:00'))->assertOk();     // no profile has this number
        $this->push($this->event('900', '2026-11-03T08:55:00'))->assertOk();     // teacher
        $this->push($this->event('42', '2026-11-03T11:30:00'))->assertOk();      // 90 minutes in the future
        $this->assertSame(['no_session', 'unknown_code', 'teacher', 'clock_ahead'], BiometricPunch::orderBy('id')->pluck('result')->all());
        $this->assertDatabaseCount('attendances', 0);

        $this->session->update(['status' => 'cancelled']);
        $this->push($this->event('42', '2026-11-03T09:00:00'))->assertOk();
        $this->assertSame('no_session', BiometricPunch::latest('id')->first()->result);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_student_of_another_batch_or_inactive_account_is_not_marked(): void
    {
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $other->forceFill(['device_employee_no' => '43'])->save();
        $this->push($this->event('43', '2026-11-03T09:00:00'))->assertOk();
        $this->student->update(['is_active' => false]);
        $this->push($this->event('42', '2026-11-03T09:01:00'))->assertOk();
        $this->assertSame(['no_session', 'unknown_code'], BiometricPunch::orderBy('id')->pluck('result')->all());
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_unknown_token_is_404_and_inactive_machine_is_seen_but_ignored(): void
    {
        $this->push($this->event('42', '2026-11-03T09:00:00'), str_repeat('x', 48))->assertNotFound();
        $this->device->update(['is_active' => false]);
        $this->push($this->event('42', '2026-11-03T09:00:00'))->assertOk();
        $this->assertDatabaseCount('biometric_punches', 0);
        $this->assertNotNull($this->device->fresh()->last_seen_at);
        $this->get('/biometric/hook/'.$this->device->webhook_token)->assertOk();
    }

    public function test_admin_manages_machines_and_sees_punch_log_and_teacher_days(): void
    {
        $this->push($this->event('900', '2026-11-03T08:55:00'));
        $this->push($this->event('900', '2026-11-03T09:58:00'));
        $this->push($this->event('42', '2026-11-03T08:50:00'));
        $this->actingAs($this->admin);
        $this->get('/biometric')->assertOk()->assertSee('Main gate')->assertSee('/biometric/hook/'.$this->device->webhook_token)
            ->assertSee('Student Sara')->assertSee('Marked present');
        $this->get('/biometric?result=teacher')->assertOk()->assertSee('Teacher Tina')->assertDontSee('Student Sara');
        $this->get('/biometric?export=csv')->assertOk();
        $this->get('/biometric/teachers?month=2026-11')->assertOk()->assertDontSee('Teacher Tina')->assertSee('No staff punches in this month.');

        $this->post('/biometric/devices', ['name' => 'Back door', 'timezone' => 'Asia/Karachi'])->assertRedirect('/biometric');
        $this->post('/biometric/devices', ['name' => 'Bad', 'timezone' => 'Mars/Base'])->assertSessionHasErrors('timezone');
        $old = $this->device->webhook_token;
        $this->post('/biometric/devices/'.$this->device->id.'/token')->assertRedirect('/biometric');
        $this->push($this->event('42', '2026-11-03T09:00:00'), $old)->assertNotFound();
        $this->put('/biometric/devices/'.$this->device->id, ['name' => 'Gate 1', 'timezone' => 'Asia/Karachi'])->assertRedirect('/biometric');
        $this->assertFalse($this->device->fresh()->is_active);
        $this->assertSame(3, AuditLog::where('subject_type', 'biometric_device')->count());

        $this->actingAs($this->teacher)->get('/biometric')->assertForbidden();
        $this->get('/biometric/teachers')->assertForbidden();
        $this->actingAs($this->student)->get('/biometric')->assertForbidden();
        $this->expectException(\LogicException::class);
        BiometricPunch::first()->delete();
    }

    public function test_profiles_ignore_posted_machine_number_and_keep_the_automatic_one(): void
    {
        $this->actingAs($this->admin);
        $studentCode = $this->student->fresh()->device_employee_no;
        $teacherCode = $this->teacher->fresh()->device_employee_no;
        $this->assertNotNull($studentCode);
        $this->assertNotNull($teacherCode);

        // The Machine Employee No. is automatic; a posted value is ignored and never errors.
        $base = ['name' => 'Student Sara', 'email' => $this->student->email, 'phone' => '0300', 'address' => 'Karachi'];
        $this->put('/students/'.$this->student->id, $base + ['device_employee_no' => '900'])->assertSessionHasNoErrors();
        $this->put('/students/'.$this->student->id, $base + ['device_employee_no' => '4 2'])->assertSessionHasNoErrors();
        $this->assertSame($studentCode, $this->student->fresh()->device_employee_no);

        $this->put('/teachers/'.$this->teacher->id, ['name' => 'Teacher Tina', 'email' => $this->teacher->email, 'phone' => '0300',
            'address' => 'Karachi', 'device_employee_no' => '901'])->assertSessionHasNoErrors();
        $this->assertSame($teacherCode, $this->teacher->fresh()->device_employee_no);
        $this->get('/teachers/'.$this->teacher->id.'/edit')->assertOk()->assertSee('Machine Employee No.')->assertSee($teacherCode);
    }
}
