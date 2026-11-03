<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TeacherDue;
use App\Models\TeacherPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $salaried;

    private User $perStudent;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->salaried = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => 50000]);
        $this->perStudent = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'per_student']);
        $this->course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 10000,
            'teacher_per_student_amount' => 1500, 'duration_weeks' => 12]);
    }

    private function batch(User $teacher, string $status = 'ongoing'): Batch
    {
        return Batch::create(['name' => 'Batch '.$teacher->id.$status, 'course_id' => $this->course->id, 'teacher_id' => $teacher->id,
            'start_date' => today()->subMonth(), 'max_students' => 20, 'status' => $status]);
    }

    private function enroll(Batch $batch, string $status): void
    {
        Enrollment::create(['student_id' => User::factory()->create(['role' => 'student'])->id, 'batch_id' => $batch->id,
            'enrollment_date' => today(), 'total_fee' => 10000, 'status' => $status]);
    }

    private function payload(array $data = []): array
    {
        return array_merge(['amount' => '20000', 'method' => 'bank', 'reference' => 'TX-1', 'submission_key' => 'payout-key-0000000001'], $data);
    }

    public function test_salary_generation_targets_active_fixed_salary_teachers_once(): void
    {
        User::factory()->create(['role' => 'teacher', 'is_active' => false, 'salary_type' => 'monthly', 'monthly_salary' => 40000]);
        User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => null]);
        $this->actingAs($this->admin);
        $period = now()->format('Y-m');

        $this->post('/payroll/salaries', ['period' => $period])->assertRedirect('/payroll');
        $due = TeacherDue::sole();
        $this->assertSame($this->salaried->id, $due->teacher_id);
        $this->assertSame('50000.00', $due->amount);
        $this->assertSame($period, $due->period);

        $this->post('/payroll/salaries', ['period' => $period])->assertSessionHas('success', 'Salaries: 0 created, 1 already existed. Per-student batch earnings: 0 created, 0 skipped (already generated, no students or no rate).');
        $this->assertDatabaseCount('teacher_dues', 1);

        $this->salaried->update(['monthly_salary' => 60000]);
        $this->assertSame('50000.00', $due->fresh()->amount);

        $this->postJson('/payroll/salaries', ['period' => now()->addMonth()->format('Y-m')])->assertUnprocessable();
    }

    public function test_batch_earning_is_monthly_for_students_enrolled_that_month(): void
    {
        $batch = $this->batch($this->perStudent);
        foreach (['active', 'active', 'completed', 'dropped', 'transferred'] as $status) {
            $this->enroll($batch, $status);
        }
        // Enrolled last month and left this month: counts in both months.
        Enrollment::create(['student_id' => User::factory()->create(['role' => 'student'])->id, 'batch_id' => $batch->id,
            'enrollment_date' => today()->startOfMonth()->subMonth(), 'total_fee' => 10000, 'status' => 'dropped', 'dropout_date' => today()]);
        $this->actingAs($this->admin);
        $month = now()->format('Y-m');
        $previous = now()->startOfMonth()->subMonth()->format('Y-m');

        $this->post('/payroll/batches', ['batch_id' => $batch->id, 'period' => $month])->assertRedirect();
        $due = TeacherDue::sole();
        $this->assertSame([4, '1500.00', '6000.00', $this->perStudent->id, $month],
            [$due->student_count, $due->rate, $due->amount, $due->teacher_id, $due->period]);
        $this->post('/payroll/batches', ['batch_id' => $batch->id, 'period' => $previous])->assertRedirect();
        $this->assertSame([1, '1500.00'], [TeacherDue::where('period', $previous)->sole()->student_count, TeacherDue::where('period', $previous)->sole()->amount]);

        $this->course->update(['teacher_per_student_amount' => 9999]);
        $this->assertSame('6000.00', $due->fresh()->amount);
        $this->postJson('/payroll/batches', ['batch_id' => $batch->id, 'period' => $month])->assertUnprocessable();
        $this->postJson('/payroll/batches', ['batch_id' => $batch->id, 'period' => now()->addMonth()->format('Y-m')])->assertUnprocessable();
        $this->postJson('/payroll/batches', ['batch_id' => $batch->id, 'period' => now()->subMonths(3)->format('Y-m')])->assertUnprocessable();

        $upcoming = $this->batch($this->perStudent, 'upcoming');
        $this->enroll($upcoming, 'active');
        $salaryBatch = $this->batch($this->salaried);
        $this->enroll($salaryBatch, 'active');
        $this->postJson('/payroll/batches', ['batch_id' => $upcoming->id, 'period' => $month])->assertUnprocessable();
        $this->postJson('/payroll/batches', ['batch_id' => $salaryBatch->id, 'period' => $month])->assertUnprocessable();
        $this->assertDatabaseCount('teacher_dues', 2);
    }

    public function test_month_generation_creates_salaries_and_batch_earnings_and_respects_old_whole_batch_dues(): void
    {
        $batch = $this->batch($this->perStudent);
        $this->enroll($batch, 'active');
        $this->enroll($batch, 'active');
        $legacy = Batch::create(['name' => 'Legacy batch', 'course_id' => $this->course->id, 'teacher_id' => $this->perStudent->id,
            'start_date' => today()->subMonth(), 'max_students' => 20, 'status' => 'ongoing']);
        $this->enroll($legacy, 'active');
        TeacherDue::create(['teacher_id' => $this->perStudent->id, 'kind' => 'batch', 'batch_id' => $legacy->id, 'student_count' => 1,
            'rate' => 1500, 'amount' => 1500, 'active_key' => 'batch:'.$legacy->id, 'created_by' => $this->admin->id, 'created_at' => now()]);
        $this->actingAs($this->admin);
        $month = now()->format('Y-m');

        $this->post('/payroll/salaries', ['period' => $month])->assertRedirect('/payroll')->assertSessionHas('success',
            'Salaries: 1 created, 0 already existed. Per-student batch earnings: 1 created, 1 skipped (already generated, no students or no rate).');
        $this->assertSame(['3000.00'], TeacherDue::where('batch_id', $batch->id)->pluck('amount')->all());
        $this->assertSame(1, TeacherDue::where('kind', 'salary')->count());
        $this->assertSame('Batch '.$batch->name.' · '.now()->format('M Y'), TeacherDue::where('batch_id', $batch->id)->sole()->label());
        $this->postJson('/payroll/batches', ['batch_id' => $legacy->id, 'period' => $month])->assertUnprocessable()->assertJsonValidationErrors('batch_id');

        $this->post('/payroll/salaries', ['period' => $month]);
        $this->assertDatabaseCount('teacher_dues', 3);
        $this->get('/payroll')->assertOk()->assertDontSee('>Legacy batch</option>', false);
    }

    public function test_payouts_are_capped_idempotent_and_reversible(): void
    {
        $this->actingAs($this->admin)->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $due = TeacherDue::sole();

        $this->post("/payroll/dues/{$due->id}/payouts", $this->payload())->assertRedirect();
        $this->post("/payroll/dues/{$due->id}/payouts", $this->payload())->assertRedirect();
        $this->assertDatabaseCount('teacher_payouts', 1);
        $this->postJson("/payroll/dues/{$due->id}/payouts", $this->payload(['amount' => '1']))->assertUnprocessable();
        $this->postJson("/payroll/dues/{$due->id}/payouts", $this->payload(['amount' => '30000.01', 'submission_key' => 'payout-key-0000000002']))->assertUnprocessable();

        $this->post("/payroll/dues/{$due->id}/payouts", $this->payload(['amount' => '30000', 'submission_key' => 'payout-key-0000000003']));
        $due->load('payouts.reversal');
        $this->assertSame(['paid', 0], [$due->state(), $due->outstandingMinor()]);

        $payout = TeacherPayout::orderBy('id')->first();
        $this->assertSame($this->salaried->name, $payout->slip_details['teacher_name']);
        $this->post("/payroll/payouts/{$payout->id}/reverse", ['reason' => 'Wrong bank account'])->assertRedirect("/payroll/payouts/{$payout->id}");
        $this->postJson("/payroll/payouts/{$payout->id}/reverse", ['reason' => 'Second attempt'])->assertUnprocessable();
        $due = $due->fresh()->load('payouts.reversal');
        $this->assertSame(['partial', 2000000], [$due->state(), $due->outstandingMinor()]);
        $this->assertSame(1, AuditLog::where('action', 'teacher_payout.reversed')->count());

        $this->expectException(\LogicException::class);
        $payout->fresh()->update(['amount' => '1']);
    }

    public function test_void_requires_no_posted_payouts_and_allows_regeneration(): void
    {
        $this->actingAs($this->admin)->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $due = TeacherDue::sole();
        $this->post("/payroll/dues/{$due->id}/payouts", $this->payload(['amount' => '100']));

        $this->postJson("/payroll/dues/{$due->id}/void", ['reason' => 'Wrong salary'])->assertUnprocessable();
        $this->post('/payroll/payouts/'.TeacherPayout::sole()->id.'/reverse', ['reason' => 'Undo payout']);
        $this->post("/payroll/dues/{$due->id}/void", ['reason' => 'Wrong salary'])->assertRedirect();
        $this->assertNotNull($due->fresh()->voided_at);
        $this->postJson("/payroll/dues/{$due->id}/payouts", $this->payload(['submission_key' => 'payout-key-0000000009']))->assertUnprocessable();

        $this->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $this->assertSame(2, TeacherDue::count());
        $this->assertSame(1, TeacherDue::open()->count());
    }

    public function test_teacher_sees_only_own_earnings_read_only_and_student_is_blocked(): void
    {
        $this->actingAs($this->admin)->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $batch = $this->batch($this->perStudent);
        $this->enroll($batch, 'active');
        $this->post('/payroll/batches', ['batch_id' => $batch->id, 'period' => now()->format('Y-m')]);
        $salaryDue = TeacherDue::where('kind', 'salary')->sole();
        $batchDue = TeacherDue::where('kind', 'batch')->sole();
        $this->post("/payroll/dues/{$batchDue->id}/payouts", $this->payload(['amount' => '500']));
        $payout = TeacherPayout::sole();

        $this->actingAs($this->perStudent)->get('/payroll')->assertOk()->assertSee('My Earnings')
            ->assertSee($batch->name)->assertDontSee('Salary '.now()->format('M Y'))->assertDontSee('Generate month');
        $this->get("/payroll/dues/{$batchDue->id}")->assertOk()->assertDontSee('Record payout');
        $this->get("/payroll/payouts/{$payout->id}")->assertOk()->assertDontSee('Reverse payout');
        $this->get("/payroll/dues/{$salaryDue->id}")->assertForbidden();
        $this->post("/payroll/dues/{$batchDue->id}/payouts", $this->payload(['submission_key' => 'payout-key-0000000004']))->assertForbidden();
        $this->post('/payroll/salaries', ['period' => now()->format('Y-m')])->assertForbidden();

        $this->actingAs($this->salaried)->get("/payroll/payouts/{$payout->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'student', 'is_active' => true]))->get('/payroll')->assertForbidden();
    }

    public function test_teacher_and_student_profiles_render_with_batches_pay_and_enrollments(): void
    {
        $batch = $this->batch($this->perStudent);
        $this->enroll($batch, 'active');
        $this->actingAs($this->admin)->post('/payroll/batches', ['batch_id' => $batch->id, 'period' => now()->format('Y-m')]);

        $this->get('/teachers/'.$this->perStudent->id)->assertOk()
            ->assertSee('Assigned batches')->assertSee($batch->name)->assertSee('1 / 20')
            ->assertSee('Recent pay')->assertSee('Batch '.$batch->name)->assertSee('Per student per month');
        $this->get('/teachers/'.$this->salaried->id)->assertOk()->assertSee('No batches assigned yet.')->assertSee('No pay generated yet.');

        $student = Enrollment::sole()->student;
        $this->get('/students/'.$student->id)->assertOk()->assertSee('Enrollments')->assertSee($batch->name)->assertSee('Fees and receipts');
        $this->actingAs($this->perStudent)->get('/students/'.$student->id)->assertOk()->assertDontSee('Fees and receipts');
    }

    public function test_admin_pages_render_and_teacher_form_saves_pay_settings(): void
    {
        $this->actingAs($this->admin)->get('/payroll')->assertOk()->assertSee('Payroll');
        $this->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $this->get('/payroll/dues/'.TeacherDue::sole()->id)->assertOk()->assertSee('Record payout');
        $this->get('/payroll?state=outstanding')->assertOk()->assertSee($this->salaried->name);
        $this->get('/payroll?state=paid')->assertOk()->assertDontSee('Salary '.now()->format('M Y'));

        $this->get('/teachers/'.$this->perStudent->id.'/edit')->assertOk()->assertSee('Pay type');
        $this->put('/teachers/'.$this->perStudent->id, ['name' => 'Per Student', 'email' => $this->perStudent->email,
            'phone' => '0300', 'address' => 'Street', 'salary_type' => 'monthly', 'monthly_salary' => '45000.50'])->assertRedirect();
        $this->assertSame(['monthly', '45000.50'], [$this->perStudent->fresh()->salary_type, (string) $this->perStudent->fresh()->monthly_salary]);
    }
}
