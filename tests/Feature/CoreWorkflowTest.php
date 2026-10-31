<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private Batch $batch;

    private Batch $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['name' => 'A1', 'level' => 'A1', 'total_fee' => 100,
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $data = ['course_id' => $course->id, 'teacher_id' => $this->teacher->id, 'start_date' => today(), 'max_students' => 10, 'status' => 'ongoing'];
        $this->batch = Batch::create($data + ['name' => 'Morning']);
        $this->target = Batch::create($data + ['name' => 'Evening']);
        $this->actingAs($this->admin);
    }

    public function test_unpaid_transfer_moves_one_schedule_without_duplicate_debt(): void
    {
        $source = app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id, 10, 3);
        $this->post('/enrollments/'.$source->id.'/transfer', ['new_batch_id' => $this->target->id])->assertRedirect();
        $new = Enrollment::where('transferred_from_enrollment_id', $source->id)->sole();
        $this->assertSame('transferred', $source->fresh()->status);
        $this->assertSame('0.00', $source->fresh()->remaining_fee);
        $this->assertSame('90.00', $new->remaining_fee);
        $this->assertDatabaseCount('fee_installments', 3);
        $this->assertSame(0, $source->feeInstallments()->count());
        $this->assertSame('90.00', number_format(FeeInstallment::sum('amount'), 2, '.', ''));
        app(PaymentService::class)->pay($new->id, '90.00', 'cash', (string) Str::uuid(), $this->admin);
        $this->assertSame('0.00', $new->fresh()->remaining_fee);
    }

    public function test_paid_transfer_is_blocked_and_withdrawal_preserves_debt_and_receipts(): void
    {
        $source = app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id);
        $payment = app(PaymentService::class)->pay($source->id, '20.00', 'cash', (string) Str::uuid(), $this->admin);
        $this->postJson('/enrollments/'.$source->id.'/transfer', ['new_batch_id' => $this->target->id])->assertUnprocessable();
        $this->patchJson('/enrollments/'.$source->id.'/withdraw', ['dropout_reason' => 'Student withdrew from course', 'refund_amount' => 5])->assertUnprocessable();
        $this->assertSame('active', $source->fresh()->status);
        $this->patch('/enrollments/'.$source->id.'/withdraw', ['dropout_reason' => 'Student withdrew from course'])->assertRedirect();
        $this->assertSame('dropped', $source->fresh()->status);
        $this->assertSame('80.00', $source->fresh()->remaining_fee);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 20]);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertSame('100.00', $source->feeInstallments()->first()->amount);
    }

    public function test_receipt_identity_is_a_snapshot_and_dashboards_reconcile_to_ledger(): void
    {
        $enrollment = app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id);
        $payment = app(PaymentService::class)->pay($enrollment->id, '20.00', 'bank', (string) Str::uuid(), $this->admin);
        $originalName = $this->student->name;
        $this->student->update(['name' => 'Changed after payment']);
        $this->get('/payments/'.$payment->id)->assertOk()->assertSee($originalName)->assertDontSee('Changed after payment');
        $this->get('/admin/dashboard')->assertOk()->assertViewHas('finance', fn ($rows) => $rows['Outstanding'] === '80.00' && $rows['Net collected'] === '20.00');
        $this->get('/fees/reports')->assertOk()->assertViewHas('net', '20.00');
        $this->actingAs($this->teacher)->get('/teacher/dashboard')->assertOk()->assertSee('Morning')->assertDontSee('Net collected');
        $this->actingAs($this->student)->get('/student/dashboard')->assertOk()->assertSee('Morning')->assertDontSee('Net collected');
    }

    public function test_core_report_routes_render_and_dormant_api_is_not_enabled(): void
    {
        app(EnrollmentService::class)->enrollStudent($this->student->id, $this->batch->id);
        foreach (['enrollments', 'collections', 'dues', 'attendance'] as $type) {
            $this->followingRedirects()->get('/reports/'.$type)->assertOk();
        }
        $this->get('/api/dashboard/admin')->assertNotFound();
        $this->get('/reports/teacher')->assertNotFound();
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (str_starts_with($action, 'App\\Http\\Controllers\\')) {
                [$class, $method] = explode('@', $action);
                $this->assertTrue(method_exists($class, $method), $action);
            }
        }
    }

    public function test_deferred_commands_do_not_mark_absences_or_deliver_messages(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->artisan('attendance:auto-absent')->assertExitCode(1);
        $this->artisan('fees:send-reminders')->assertExitCode(1);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
        $this->assertDatabaseCount('attendances', 0);
    }
}
