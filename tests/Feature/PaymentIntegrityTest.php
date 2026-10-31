<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => '100.01',
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $batch = Batch::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'Morning',
            'start_date' => today(), 'status' => 'ongoing', 'max_students' => 10]);
        $this->enrollment = app(EnrollmentService::class)->enrollStudent($this->student->id, $batch->id, 0, 3);
        $this->actingAs($this->admin);
    }

    private function payment(string $amount, ?string $key = null): Payment
    {
        return app(PaymentService::class)->pay($this->enrollment->id, $amount, 'cash', $key ?? (string) Str::uuid(), $this->admin);
    }

    public function test_partial_and_early_payment_preserve_schedule_and_allocate_exactly(): void
    {
        $original = $this->enrollment->feeInstallments()->orderBy('id')->pluck('amount')->all();
        $first = $this->payment('10.00');
        $this->assertSame('10.00', $first->allocations->first()->amount);
        $second = $this->payment('50.00');
        $this->assertSame(2, $second->allocations()->count());
        $this->assertSame('60.00', $this->enrollment->fresh()->paid_amount);
        $this->assertSame($original, $this->enrollment->feeInstallments()->orderBy('id')->pluck('amount')->all());
        $this->payment('40.01');
        $this->assertSame('100.01', $this->enrollment->fresh()->paid_amount);
        $this->assertSame(3, $this->enrollment->feeInstallments()->where('status', 'paid')->count());
        $this->assertSame(3, \App\Models\AuditLog::where('action', 'payment.posted')->count());
    }

    public function test_duplicate_submission_returns_same_receipt_without_changing_totals(): void
    {
        $key = (string) Str::uuid();
        $first = $this->payment('20.00', $key);
        $second = $this->payment('20.00', $key);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('20.00', $this->enrollment->fresh()->paid_amount);
        try {
            $this->payment('21.00', $key);
            $this->fail('Conflicting replay must fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('submission_key', $exception->errors());
        }
    }

    public function test_overpayment_rejected_without_any_financial_side_effect(): void
    {
        try {
            $this->payment('100.02');
            $this->fail('Overpayment must fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame('0.00', $this->enrollment->fresh()->paid_amount);
    }

    public function test_full_reversal_restores_debt_and_does_not_edit_original_receipt(): void
    {
        $payment = $this->payment('100.01');
        $original = $payment->fresh()->getAttributes();
        app(PaymentService::class)->reverse($payment, $this->admin, 'Recorded against wrong agreement');
        $this->assertSame($original, $payment->fresh()->getAttributes());
        $this->assertSame('0.00', $this->enrollment->fresh()->paid_amount);
        $this->assertSame(3, $this->enrollment->feeInstallments()->where('status', 'pending')->count());
        $this->assertDatabaseCount('payment_allocations', 3);
        try {
            app(PaymentService::class)->reverse($payment, $this->admin, 'Duplicate correction');
            $this->fail('Duplicate reversal must fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertDatabaseCount('payment_reversals', 1);
        $this->payment('100.01');
        $this->assertSame('100.01', $this->enrollment->fresh()->paid_amount);
    }

    public function test_receipt_is_self_only_for_students_and_not_available_to_teachers(): void
    {
        $payment = $this->payment('10.00');
        $this->actingAs($this->student)->get('/payments/'.$payment->id)->assertOk()->assertSee($payment->receipt_number);
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($other)->get('/payments/'.$payment->id)->assertForbidden();
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->get('/payments/'.$payment->id)->assertForbidden();
        $this->actingAs($this->student)->post('/payments/'.$payment->id.'/reverse', ['reason' => 'Not allowed'])->assertForbidden();
    }

    public function test_old_installment_endpoint_uses_ledger_and_requires_submission_key(): void
    {
        $installment = $this->enrollment->feeInstallments()->first();
        $this->postJson('/fees/'.$installment->id.'/pay', ['payment_method' => 'cash'])->assertUnprocessable();
        $this->post('/fees/'.$installment->id.'/pay', ['payment_method' => 'cash', 'submission_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($installment->amount, $this->enrollment->fresh()->paid_amount);
    }

    public function test_legacy_mismatch_blocks_collection_and_saved_receipts_are_immutable(): void
    {
        $this->enrollment->update(['paid_amount' => '1.00']);
        try {
            $this->payment('10.00');
            $this->fail('Unreconciled account must fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('enrollment', $exception->errors());
        }
        $this->enrollment->update(['paid_amount' => 0]);
        $payment = $this->payment('10.00');
        $this->expectException(\LogicException::class);
        $payment->update(['amount' => '20.00']);
    }

    public function test_collection_report_keeps_original_date_and_subtracts_reversal_on_reversal_date(): void
    {
        $this->travelTo(today()->subMonthNoOverflow()->day(5)->setTime(9, 0));
        $payment = $this->payment('10.00');
        $previousStart = today()->startOfMonth()->toDateString();
        $previousEnd = today()->endOfMonth()->toDateString();
        $this->travelBack();
        app(PaymentService::class)->reverse($payment, $this->admin, 'Wrong payment reference');
        $this->get('/fees/reports?start_date='.$previousStart.'&end_date='.$previousEnd)->assertOk()->assertViewHas('net', '10.00');
        $this->get('/fees/reports?start_date='.today()->startOfMonth()->toDateString().'&end_date='.today()->toDateString())
            ->assertOk()->assertViewHas('net', '-10.00');
    }
}
