<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\EnrollmentPackage;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\OverviewService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentPackageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private User $teacher;

    private Batch $a1;

    private Batch $a2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Package Student']);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        foreach (['A1' => '35000.00', 'A2' => '35000.01'] as $level => $fee) {
            $course = Course::create(['name' => 'German '.$level, 'level' => $level, 'total_fee' => $fee,
                'teacher_per_student_amount' => 500, 'duration_weeks' => 8]);
            $batch = Batch::create(['name' => $level.' batch', 'course_id' => $course->id, 'teacher_id' => $this->teacher->id,
                'start_date' => $level === 'A1' ? today() : today()->addMonths(2),
                'end_date' => $level === 'A1' ? today()->addMonths(2)->subDay() : today()->addMonths(4)->subDay(),
                'status' => $level === 'A1' ? 'ongoing' : 'upcoming', 'max_students' => 10]);
            if ($level === 'A1') {
                $this->a1 = $batch;
            } else {
                $this->a2 = $batch;
            }
        }
        $this->actingAs($this->admin);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['student_id' => $this->student->id, 'a1_batch_id' => $this->a1->id,
            'a2_batch_id' => $this->a2->id, 'amount' => '50000.00', 'method' => 'cash',
            'submission_key' => (string) Str::uuid()], $overrides);
    }

    private function purchase(?array $data = null): EnrollmentPackage
    {
        $response = $this->post('/enrollment-packages', $data ?? $this->data());
        $response->assertSessionHasNoErrors();
        $package = EnrollmentPackage::latest('id')->firstOrFail();
        $response->assertRedirect(route('enrollment-packages.show', $package));

        return $package;
    }

    public function test_full_package_posts_exact_module_ledgers_and_a_single_summary_without_double_counting(): void
    {
        $this->get('/enrollments')->assertOk()->assertSee('A1 + A2 package');
        $this->get('/enrollment-packages/create')->assertOk()->assertSee('Full payment');
        $package = $this->purchase();
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('payment_allocations', 2);
        $this->assertSame('50000.00', $package->amount);
        $this->assertSame('50000.00', Money::decimal($package->payments()->get()->sum(fn ($p) => Money::minor($p->amount))));
        $modules = $package->receipt_details['modules'];
        $this->assertSame('25000.00', $modules[0]['amount']);
        $this->assertSame('25000.00', $modules[1]['amount']);
        foreach ($package->enrollments as $enrollment) {
            $this->assertSame('0.00', $enrollment->remaining_fee);
            $this->assertSame(1, $enrollment->feeInstallments()->count());
            $this->assertSame('paid', $enrollment->feeInstallments()->sole()->status);
            app(PaymentService::class)->assertReconciled($enrollment);
            $this->get('/enrollments/'.$enrollment->id)->assertOk()->assertSee('View combined receipt')->assertDontSee('Edit discount');
        }
        $this->assertSame('50000.00', app(OverviewService::class)->financialTotals()['Net collected']);
        $this->assertSame('0.00', app(OverviewService::class)->financialTotals()['Outstanding']);
        $this->get('/fees/reports')->assertOk();
        $this->get('/enrollment-packages/'.$package->id)->assertOk()->assertSee($package->receipt_number)->assertSee('25,000.00', false);
        $this->a1->course->update(['total_fee' => '99000.00', 'name' => 'Changed course name']);
        $this->get('/enrollment-packages/'.$package->id)->assertOk()->assertSee('German A1')->assertDontSee('Changed course name');
    }

    public function test_identical_replay_returns_same_receipt_and_conflicting_replay_is_rejected(): void
    {
        $data = $this->data();
        $package = $this->purchase($data);
        $this->post('/enrollment-packages', $data)->assertRedirect(route('enrollment-packages.show', $package));
        $this->postJson('/enrollment-packages', array_replace($data, ['method' => 'bank']))->assertUnprocessable()->assertJsonValidationErrors('submission_key');
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->postJson('/enrollment-packages', array_replace($data, ['student_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('submission_key');
        $this->assertDatabaseCount('enrollment_packages', 1);
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_unequal_fees_use_exact_minor_units_and_no_cent_is_lost(): void
    {
        $this->a1->course->update(['total_fee' => '30000.00']);
        $this->a2->course->update(['total_fee' => '40000.00']);
        $package = $this->purchase();
        $modules = $package->receipt_details['modules'];
        $this->assertSame('21428.57', $modules[0]['amount']);
        $this->assertSame('28571.43', $modules[1]['amount']);
        $this->assertSame('8571.43', $modules[0]['discount']);
        $this->assertSame('11428.57', $modules[1]['discount']);
        foreach ($package->enrollments as $enrollment) {
            app(PaymentService::class)->assertReconciled($enrollment);
        }
    }

    public function test_closed_courses_unsupported_currency_and_below_price_fees_cannot_sell_package(): void
    {
        $this->a2->course->update(['is_active' => false]);
        $this->postJson('/enrollment-packages', $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->a2->course->update(['is_active' => true, 'total_fee' => '1000.00']);
        $this->postJson('/enrollment-packages', $this->data())->assertUnprocessable()->assertJsonValidationErrors('a1_batch_id');
        \App\Models\Setting::create(['key' => 'currency_code', 'value' => 'USD', 'label' => 'Currency']);
        $this->postJson('/enrollment-packages', $this->data())->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->assertDatabaseCount('enrollment_packages', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_partial_payments_installments_wrong_levels_and_overlapping_batches_are_rejected(): void
    {
        foreach (['25000.00', '50000.01', '0.00'] as $amount) {
            $this->postJson('/enrollment-packages', $this->data(['amount' => $amount]))->assertUnprocessable();
        }
        $this->postJson('/enrollment-packages', $this->data(['installments' => 2]))->assertUnprocessable();
        $this->postJson('/enrollment-packages', $this->data(['a2_batch_id' => $this->a1->id]))->assertUnprocessable();
        $this->postJson('/enrollment-packages', $this->data(['a1_batch_id' => $this->a2->id, 'a2_batch_id' => $this->a1->id]))->assertUnprocessable();
        $this->a2->update(['start_date' => $this->a1->end_date]);
        $this->postJson('/enrollment-packages', $this->data())->assertUnprocessable()->assertJsonValidationErrors('a2_batch_id');
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_second_module_failure_rolls_back_first_admission_payment_and_audits(): void
    {
        $this->a2->update(['max_students' => 0]);
        $auditCount = \App\Models\AuditLog::count();
        $this->postJson('/enrollment-packages', $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('fee_installments', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertDatabaseCount('enrollment_packages', 0);
        $this->a2->update(['max_students' => 10]);
        $this->purchase();
        $this->postJson('/enrollment-packages', $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_role_scope_student_receipts_and_package_price_protection(): void
    {
        $package = $this->purchase();
        $enrollment = $package->enrollments()->firstOrFail();
        $this->patch('/enrollments/'.$enrollment->id.'/discount', ['discount_percentage' => 50, 'reason' => 'Change package price'])
            ->assertSessionHasErrors('discount_percentage');
        $this->actingAs($this->student)->get('/enrollment-packages/'.$package->id)->assertOk()->assertSee($package->receipt_number);
        $this->get('/fees/student/'.$this->student->id)->assertOk();
        $this->get('/payments/'.$package->payments()->first()->id)->assertOk()->assertSee('Combined package receipt');
        $this->get('/enrollment-packages/create')->assertForbidden();
        $this->post('/enrollment-packages', $this->data())->assertForbidden();
        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($other)->get('/enrollment-packages/'.$package->id)->assertForbidden();
        $this->actingAs($this->teacher)->get('/enrollment-packages/'.$package->id)->assertForbidden();
        $this->get('/enrollment-packages/create')->assertForbidden();
        $this->post('/enrollment-packages', $this->data())->assertForbidden();
        $this->actingAs($this->admin);
        $this->admin->update(['is_active' => false]);
        $this->post('/enrollment-packages', $this->data())->assertRedirect('/');
    }

    public function test_reversing_either_original_allocation_reverses_both_and_preserves_receipt(): void
    {
        $package = $this->purchase();
        $payments = $package->payments()->orderBy('id')->get();
        $this->post('/payments/'.$payments->last()->id.'/reverse', ['reason' => 'Wrong advance payment recorded'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_reversals', 2);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('enrollment_packages', 1);
        $this->assertSame('0.00', app(OverviewService::class)->financialTotals()['Net collected']);
        $this->assertSame('50000.00', app(OverviewService::class)->financialTotals()['Outstanding']);
        foreach ($package->enrollments as $enrollment) {
            $this->assertSame('0.00', $enrollment->paid_amount);
            $this->assertSame('25000.00', $enrollment->remaining_fee);
            app(PaymentService::class)->assertReconciled($enrollment);
        }
        $this->get('/enrollment-packages/'.$package->id)->assertOk()->assertSee('Reversed')->assertSee('Wrong advance payment recorded');
        $this->post('/payments/'.$payments->first()->id.'/reverse', ['reason' => 'Duplicate correction'])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payment_reversals', 2);
        $this->expectException(\LogicException::class);
        $package->update(['amount' => '1.00']);
    }

    public function test_group_reversal_rolls_back_if_one_module_does_not_reconcile(): void
    {
        $package = $this->purchase();
        $enrollments = $package->enrollments()->orderBy('id')->get();
        $enrollments->last()->update(['paid_amount' => '1.00']);
        $this->post('/payments/'.$package->payments()->first()->id.'/reverse', ['reason' => 'Correct payment entry'])->assertSessionHasErrors('enrollment');
        $this->assertDatabaseCount('payment_reversals', 0);
        $this->assertSame('25000.00', $enrollments->first()->fresh()->paid_amount);
    }

    public function test_normal_level_wise_enrollment_and_installments_remain_independent(): void
    {
        $service = app(EnrollmentService::class);
        $first = $service->enrollStudent($this->student->id, $this->a1->id, 0, 2);
        app(PaymentService::class)->pay($first->id, '10000.00', 'cash', (string) Str::uuid(), $this->admin);
        $this->a1->update(['status' => 'completed']);
        $this->a1->syncEnrollmentStatuses();
        $second = $service->enrollStudent($this->student->id, $this->a2->id, 0, 2);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('25000.00', $first->fresh()->remaining_fee);
        $this->assertSame('35000.01', $second->remaining_fee);
        $this->assertNull($second->enrollment_package_id);
        $this->assertSame(2, $second->feeInstallments()->count());
        $this->assertDatabaseCount('enrollment_packages', 0);
    }
}
