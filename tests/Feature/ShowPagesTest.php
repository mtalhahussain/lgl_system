<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_pages_render_their_key_information(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'name' => 'Teacher Tariq', 'salary_type' => 'monthly', 'monthly_salary' => 30000]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Student Sara']);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 9000, 'teacher_per_student_amount' => 500,
            'duration_weeks' => 12, 'description' => 'Beginner course']);
        $batch = Batch::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'Morning A1', 'start_date' => today(),
            'status' => 'ongoing', 'max_students' => 10, 'meeting_platform' => 'zoom', 'meeting_link' => 'https://zoom.example/j/1']);
        $enrollment = app(EnrollmentService::class)->enrollStudent($student->id, $batch->id, 0, 3);
        $session = ClassSession::create(['batch_id' => $batch->id, 'session_date' => today()->addDay(), 'start_time' => '09:00',
            'end_time' => '10:00', 'topic' => 'Greetings', 'status' => 'scheduled']);
        Attendance::create(['class_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'late', 'notes' => 'Traffic']);
        $payment = app(PaymentService::class)->pay($enrollment->id, '1000', 'cash', (string) Str::uuid(), $admin);
        app(PaymentService::class)->reverse($payment, $admin, 'Entered twice');
        $payroll = app(PayrollService::class);
        $payroll->generateSalaries(now()->format('Y-m'), $admin);
        $payout = $payroll->pay(\App\Models\TeacherDue::sole()->id, '30000', 'bank', (string) Str::uuid(), $admin, 'TX-77');

        $this->actingAs($admin);
        $this->get('/batches/'.$batch->id)->assertOk()->assertSee('Student Sara')->assertSee('Teacher Tariq')
            ->assertSee('Join class')->assertSee('1 / 10')->assertSee('Greetings');
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('German A1')->assertSee('Morning A1')
            ->assertSee('Teacher amount per student')->assertSee('Beginner course');
        $this->get('/class-sessions/'.$session->id)->assertOk()->assertSee('Greetings')->assertSee('Student Sara')
            ->assertSee('Traffic')->assertSee('Cancel this session');
        $this->get('/enrollments/'.$enrollment->id)->assertOk()->assertSee('Fee agreement')->assertSee($payment->receipt_number)
            ->assertSee('reversed')->assertSee('Withdraw enrollment');
        $this->get('/payments/'.$payment->id)->assertOk()->assertSee('Fee Payment Receipt')->assertSee('Entered twice')
            ->assertSee('German A1')->assertDontSee('Reverse this payment');
        $this->get('/payroll/payouts/'.$payout->id)->assertOk()->assertSee('Salary Payment Slip')->assertSee('Teacher Tariq')
            ->assertSee('TX-77')->assertSee('Reverse this payout');

        $this->get('/batches/'.$batch->id)->assertSee('Back to Batches')->assertSee('href="'.route('batches.index').'"', false);
        $this->get('/batches/'.$batch->id.'/edit')->assertOk()->assertSee('Back to Batch')->assertSee('href="'.route('batches.show', $batch).'"', false);
        $this->get('/class-sessions/'.$session->id.'/attendance')->assertOk()->assertSee('Back to Session');
        $this->get('/fees/batch/'.$batch->id)->assertOk()->assertSee('Back to Batch');
        $this->get('/fees/student/'.$student->id)->assertOk()->assertSee('Back to Fee ledger');
        $this->get('/students')->assertOk()->assertDontSee('back-link', false);

        $this->actingAs($teacher);
        $this->get('/batches/'.$batch->id)->assertOk()->assertSee('Student Sara')->assertDontSee('Fees</a>', false)->assertSee('Back to My batches');
        $this->get('/attendance/reports')->assertOk()->assertSee('Back to Attendance');
        $this->get('/students/'.$student->id)->assertOk()->assertSee('Enrollments in your batches')->assertSee('Morning A1');
        $this->get('/payroll/payouts/'.$payout->id)->assertOk()->assertDontSee('Reverse this payout');

        $this->actingAs($student);
        $this->get('/fees/student/'.$student->id)->assertOk()->assertDontSee('back-link', false);
    }
}
