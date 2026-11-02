<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeRefund;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\OverviewService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Refund Student']);
        $course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 9000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $batch = Batch::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'Morning',
            'start_date' => today(), 'status' => 'ongoing', 'max_students' => 10]);
        $this->enrollment = app(EnrollmentService::class)->enrollStudent($this->student->id, $batch->id, 0, 3);
        $this->actingAs($this->admin);
    }

    private function pay(string $amount): Payment
    {
        return app(PaymentService::class)->pay($this->enrollment->id, $amount, 'cash', (string) Str::uuid(), $this->admin);
    }

    private function change(string $percentage, array $refund = [])
    {
        return $this->patch('/enrollments/'.$this->enrollment->id.'/discount', array_merge(
            ['discount_percentage' => $percentage, 'reason' => 'Scholarship approved'], $refund));
    }

    private function schedule(): array
    {
        return $this->enrollment->feeInstallments()->orderBy('due_date')->orderBy('id')->pluck('amount')->all();
    }

    public function test_fully_paid_student_gets_a_larger_discount_with_a_recorded_refund(): void
    {
        $payment = $this->pay('9000');

        $this->change('25')->assertSessionHasErrors('refund_method');
        $this->assertStringContainsString('needs a refund of', session('errors')->first('refund_method'));
        $this->assertDatabaseCount('fee_refunds', 0);

        $key = (string) Str::uuid();
        $response = $this->change('25', ['refund_method' => 'bank', 'refund_reference' => 'IBFT-9', 'submission_key' => $key]);
        $refund = FeeRefund::sole();
        $response->assertRedirect('/refunds/'.$refund->id);
        $this->change('25', ['refund_method' => 'bank', 'refund_reference' => 'IBFT-9', 'submission_key' => $key]);
        $this->assertDatabaseCount('fee_refunds', 1);

        $fresh = $this->enrollment->fresh();
        $this->assertSame(['2250.00', '2250.00', '9000.00', '6750.00', '0.00'],
            [$refund->amount, $fresh->refunded_amount, $fresh->paid_amount, $fresh->net_paid, $fresh->remaining_fee]);
        $this->assertSame(['3000.00', '3000.00', '3000.00'], $this->schedule());
        $this->assertSame('2250.00', AuditLog::where('action', 'enrollment.discount_changed')->sole()->changes['refund']['amount']);

        $this->postJson('/payments/'.$payment->id.'/reverse', ['reason' => 'Mistaken entry'])->assertUnprocessable();
        app(PaymentService::class)->assertReconciled($fresh);

        $this->get('/refunds/'.$refund->id)->assertOk()->assertSee('Fee Refund Slip')->assertSee('Refund Student')->assertSee('IBFT-9')->assertSee('25%');
        $this->get('/enrollments/'.$this->enrollment->id)->assertOk()->assertSee('Net paid')->assertSee($refund->voucher_number);
        $this->get('/fees')->assertOk()->assertSee('refund');
        $this->get('/fees/reports')->assertOk()->assertSee($refund->voucher_number)->assertSee('Refunds');
        $this->assertSame('6750.00', app(OverviewService::class)->financialTotals()['Net collected']);
        $this->assertSame('0.00', app(OverviewService::class)->financialTotals()['Outstanding']);
        $this->assertSame('6750.00', app(OverviewService::class)->financialTotals()['This month net collected']);

        $this->actingAs($this->student)->get('/refunds/'.$refund->id)->assertOk();
        $this->get('/fees/student/'.$this->student->id)->assertOk()->assertSee('Refunds')->assertSee($refund->voucher_number)
            ->assertSee('/refunds/'.$refund->id, false);
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($other)->get('/refunds/'.$refund->id)->assertForbidden();
        $this->get('/fees/student/'.$other->id)->assertOk()->assertDontSee($refund->voucher_number)->assertDontSee('Recorded when a later discount');
    }

    public function test_partial_payer_refund_trims_unpaid_first_and_later_decrease_creates_new_debt(): void
    {
        $this->pay('5000');
        $this->change('50', ['refund_method' => 'cash', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $this->assertSame('500.00', FeeRefund::sole()->amount);
        $this->assertSame(['3000.00', '2000.00', '0.00'], $this->schedule());
        $this->assertSame('0.00', $this->enrollment->fresh()->remaining_fee);

        $this->change('40')->assertSessionHasNoErrors();
        $this->assertSame(['3000.00', '2000.00', '900.00'], $this->schedule());
        $this->assertSame('900.00', $this->enrollment->fresh()->remaining_fee);
        $this->assertDatabaseCount('fee_refunds', 1);

        $this->pay('900');
        $this->assertSame('0.00', $this->enrollment->fresh()->remaining_fee);
    }

    public function test_no_refund_is_recorded_when_the_discount_fits_unpaid_amounts(): void
    {
        $this->pay('3000');
        $this->change('25', ['refund_method' => 'cash', 'submission_key' => (string) Str::uuid()])->assertRedirect('/enrollments/'.$this->enrollment->id);
        $this->assertDatabaseCount('fee_refunds', 0);
        $this->assertSame(['3000.00', '3000.00', '750.00'], $this->schedule());
    }
}
