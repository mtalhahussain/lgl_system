<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscountEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 9000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $batch = Batch::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'name' => 'Morning',
            'start_date' => today(), 'status' => 'ongoing', 'max_students' => 10]);
        $this->enrollment = app(EnrollmentService::class)->enrollStudent($student->id, $batch->id, 0, 3);
        $this->actingAs($this->admin);
    }

    private function schedule(): array
    {
        return $this->enrollment->feeInstallments()->orderBy('due_date')->orderBy('id')->pluck('amount')->all();
    }

    private function change(string $percentage, string $reason = 'Scholarship approved')
    {
        return $this->patch('/enrollments/'.$this->enrollment->id.'/discount', ['discount_percentage' => $percentage, 'reason' => $reason]);
    }

    public function test_more_discount_trims_the_latest_unpaid_installment_before_payments(): void
    {
        $this->assertSame(['3000.00', '3000.00', '3000.00'], $this->schedule());
        $this->change('10')->assertRedirect('/enrollments/'.$this->enrollment->id);

        $fresh = $this->enrollment->fresh();
        $this->assertSame(['900.00', '10'], [$fresh->discount_amount, $fresh->discount_percentage]);
        $this->assertSame(['3000.00', '3000.00', '2100.00'], $this->schedule());
        $audit = AuditLog::where('action', 'enrollment.discount_changed')->sole();
        $this->assertSame('Scholarship approved', $audit->reason);
        $this->assertSame('0.00', $audit->changes['before']['discount_amount']);

        $this->get('/enrollments/'.$this->enrollment->id)->assertOk()->assertSee('10% · ')->assertSee('Edit discount')
            ->assertSee('data-discount-preview', false)->assertSee('data-gross="900000"', false)->assertSee('data-discount="90000"', false)
            ->assertSee('data-kept="0"', false)->assertSee('new <strong>total</strong> discount', false);
        $this->get('/enrollments')->assertOk()->assertSee('10%');
        $this->get('/fees')->assertOk()->assertSee('10% off');
        $student = $this->enrollment->student;
        $this->get('/students/'.$student->id)->assertOk()->assertSee('<strong>10%</strong>', false)->assertSee('8,100', false)
            ->assertSee(route('enrollments.show', $this->enrollment), false);
        $this->actingAs($student)->get('/students/'.$student->id)->assertOk()->assertSee('<strong>10%</strong>', false)
            ->assertDontSee(route('enrollments.show', $this->enrollment), false);
    }

    public function test_paid_amounts_never_change_and_net_cannot_drop_below_paid(): void
    {
        app(PaymentService::class)->pay($this->enrollment->id, '5000', 'cash', (string) Str::uuid(), $this->admin);

        $this->change('50')->assertSessionHasErrors('refund_method');
        $this->assertStringContainsString('keep the discount at 44.44% or less', session('errors')->first('refund_method'));
        $this->assertSame(['3000.00', '3000.00', '3000.00'], $this->schedule());

        $this->change('40')->assertSessionHasNoErrors();
        $this->assertSame(['3000.00', '2400.00', '0.00'], $this->schedule());
        $this->assertSame('3600.00', $this->enrollment->fresh()->discount_amount);
        $this->assertSame('400.00', $this->enrollment->fresh()->remaining_fee);

        $this->change('12.5', 'Scholarship reduced')->assertSessionHasNoErrors();
        $this->assertSame(['3000.00', '4875.00', '0.00'], $this->schedule());
        $this->assertSame(['1125.00', '12.5'], [$this->enrollment->fresh()->discount_amount, $this->enrollment->fresh()->discount_percentage]);

        app(PaymentService::class)->pay($this->enrollment->id, '2875', 'cash', (string) Str::uuid(), $this->admin);
        $this->assertSame('0.00', $this->enrollment->fresh()->remaining_fee);
        $this->assertSame(3, $this->enrollment->feeInstallments()->where('status', 'paid')->count());
    }

    public function test_discount_change_rules_and_access(): void
    {
        $this->change('10', 'no')->assertSessionHasErrors('reason');
        $this->change('100.5')->assertSessionHasErrors('discount_percentage');
        $this->change('0')->assertSessionHasErrors('discount_percentage');
        $this->assertSame('0.00', $this->enrollment->fresh()->discount_amount);

        $this->change('100')->assertSessionHasNoErrors();
        $this->assertSame('0.00', $this->enrollment->fresh()->remaining_fee);

        $this->actingAs(User::factory()->create(['role' => 'teacher', 'is_active' => true]));
        $this->change('5')->assertForbidden();
    }
}
