<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\FeeCalculatorService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $course = Course::create(['name' => 'German', 'level' => 'A1', 'total_fee' => '100.01',
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $this->batch = Batch::create(['course_id' => $course->id, 'teacher_id' => $teacher->id,
            'name' => 'Morning', 'start_date' => today(), 'max_students' => 1, 'status' => 'ongoing']);
    }

    public function test_admission_uses_exact_rounding_and_applies_discount_once(): void
    {
        $this->post('/enrollments', ['student_id' => $this->student->id, 'batch_id' => $this->batch->id,
            'discount_percentage' => '10', 'installments' => 3])->assertRedirect('/enrollments');
        $enrollment = Enrollment::sole();
        $this->assertSame('100.01', $enrollment->total_fee);
        $this->assertSame('10.00', $enrollment->discount_amount);
        $amounts = $enrollment->feeInstallments()->orderBy('id')->pluck('amount')->all();
        $this->assertSame(['30.01', '30.00', '30.00'], $amounts);
        $this->assertSame(9001, array_sum(array_map([Money::class, 'minor'], $amounts)));
        $this->get('/enrollments/'.$enrollment->id)->assertOk();
    }

    public function test_duplicate_and_over_capacity_admission_do_not_create_partial_records(): void
    {
        $service = app(EnrollmentService::class);
        $service->enrollStudent($this->student->id, $this->batch->id);
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        foreach ([$this->student, $other] as $student) {
            try {
                $service->enrollStudent($student->id, $this->batch->id);
                $this->fail('Admission should fail.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('fee_installments', 1);
    }

    public function test_student_registration_rolls_back_if_selected_batch_is_full(): void
    {
        app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id);
        $this->post('/students', ['name' => 'Rejected', 'email' => 'rejected@lgl.com', 'phone' => '03001234567',
            'address' => 'Karachi', 'password' => 'safe-password', 'password_confirmation' => 'safe-password',
            'batch_id' => $this->batch->id])->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('users', ['email' => 'rejected@lgl.com']);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_invalid_schedule_count_does_not_leave_an_enrollment(): void
    {
        try {
            app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id, 0, 0);
            $this->fail('Invalid installment count must fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Installment count', $exception->getMessage());
        }
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_schedule_cannot_be_created_twice_and_month_end_dates_do_not_overflow(): void
    {
        $enrollment = app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id, 0, 3);
        $service = app(FeeCalculatorService::class);
        $rows = $service->generateInstallmentSchedule($enrollment->id, 3, '2026-01-31');
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($rows, 'due_date'));
        try {
            $service->createInstallments($enrollment->id, 3);
            $this->fail('Duplicate schedule must fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('already exist', $exception->getMessage());
        }
        $this->assertDatabaseCount('fee_installments', 3);
    }

    public function test_custom_schedule_preserves_explicit_amounts_and_due_dates(): void
    {
        $schedule = [['amount' => '20.01', 'due_date' => today()->toDateString()],
            ['amount' => '70.00', 'due_date' => today()->addDays(45)->toDateString()]];
        $this->post('/enrollments', ['student_id' => $this->student->id, 'batch_id' => $this->batch->id,
            'discount_percentage' => 10, 'schedule' => $schedule])->assertRedirect('/enrollments');
        $this->assertSame(['20.01', '70.00'], Enrollment::sole()->feeInstallments()->orderBy('id')->pluck('amount')->all());
        $this->assertSame($schedule[1]['due_date'], Enrollment::sole()->feeInstallments()->orderByDesc('id')->first()->due_date->toDateString());
    }

    public function test_invalid_custom_total_rolls_back_admission(): void
    {
        $this->post('/enrollments', ['student_id' => $this->student->id, 'batch_id' => $this->batch->id,
            'schedule' => [['amount' => '99.00', 'due_date' => today()->toDateString()]]])->assertSessionHasErrors('error');
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('fee_installments', 0);
    }
}
