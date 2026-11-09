<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Payment;
use App\Models\TeacherDue;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportMonthFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** Record one fee payment and stamp its payment date to the given moment. */
    private function payInMonth(string $amount, Carbon $when): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create(['name' => 'A1 '.Str::random(4), 'level' => 'A1', 'total_fee' => '300.00',
            'teacher_per_student_amount' => 0, 'duration_weeks' => 12]);
        $batch = Batch::create(['course_id' => $course->id, 'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'name' => 'B '.Str::random(4), 'start_date' => today()->subMonths(2), 'status' => 'ongoing', 'max_students' => 10]);
        $enrollment = app(EnrollmentService::class)->enrollStudent($student->id, $batch->id, 0, 1);
        app(PaymentService::class)->pay($enrollment->id, $amount, 'cash', (string) Str::uuid(), $this->admin);
        $payment = Payment::where('enrollment_id', $enrollment->id)->latest('id')->first();
        DB::table('payments')->where('id', $payment->id)->update(['paid_at' => $when]);
    }

    private function salaried(): User
    {
        return User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => 50000]);
    }

    private function payout(TeacherDue $due, string $amount, string $key): void
    {
        $this->post('/payroll/dues/'.$due->id.'/payouts',
            ['amount' => $amount, 'method' => 'bank', 'reference' => 'R', 'submission_key' => $key]);
    }

    public function test_collections_report_defaults_to_current_month_and_filters_by_selected_month(): void
    {
        $last = now()->subMonthNoOverflow();
        $this->payInMonth('50.00', now());
        $this->payInMonth('30.00', $last->copy()->day(15));
        $this->actingAs($this->admin);

        // Default window is the current month: only the 50.00 payment counts.
        $this->get('/fees/reports')->assertOk()->assertViewHas('net', '50.00');

        // Selecting the previous month narrows to the 30.00 payment.
        $this->get('/fees/reports?year='.$last->year.'&month='.$last->month)
            ->assertOk()->assertViewHas('net', '30.00');
    }

    public function test_collections_report_offers_year_and_month_filters_instead_of_date_pickers(): void
    {
        $this->actingAs($this->admin);
        $this->get('/fees/reports')->assertOk()
            ->assertSee('filter_year', false)
            ->assertSee('filter_month', false)
            ->assertDontSee('filter_start_date', false)
            ->assertDontSee('filter_end_date', false);
    }

    public function test_payroll_filters_dues_by_selected_year_and_month(): void
    {
        $this->salaried();
        $this->actingAs($this->admin);
        $last = now()->subMonthNoOverflow();
        $this->post('/payroll/salaries', ['period' => now()->format('Y-m')])->assertRedirect('/payroll');
        $this->post('/payroll/salaries', ['period' => $last->format('Y-m')])->assertRedirect('/payroll');
        $this->assertDatabaseCount('teacher_dues', 2);

        // Filtered to the previous month: only that period's due.
        $this->get('/payroll?year='.$last->year.'&month='.$last->month)->assertOk()
            ->assertViewHas('dues', fn ($dues) => $dues->total() === 1 && $dues->first()->period === $last->format('Y-m'));

        // No month filter keeps every due visible.
        $this->get('/payroll')->assertOk()->assertViewHas('dues', fn ($dues) => $dues->total() === 2);
    }

    public function test_payroll_paid_kpi_reflects_the_selected_month(): void
    {
        $this->salaried();
        $this->actingAs($this->admin);
        $last = now()->subMonthNoOverflow();

        $this->post('/payroll/salaries', ['period' => now()->format('Y-m')]);
        $this->payout(TeacherDue::where('period', now()->format('Y-m'))->sole(), '10000', 'payout-key-0000000001');

        $this->post('/payroll/salaries', ['period' => $last->format('Y-m')]);
        $dueLast = TeacherDue::where('period', $last->format('Y-m'))->sole();
        $this->payout($dueLast, '20000', 'payout-key-0000000002');
        DB::table('teacher_payouts')->where('teacher_due_id', $dueLast->id)->update(['paid_at' => $last->copy()->day(15)]);

        // Default KPI shows the current month's payouts.
        $this->get('/payroll')->assertOk()->assertViewHas('kpis', fn ($kpis) => collect($kpis)->contains(
            fn ($kpi) => str_contains($kpi['label'], 'Paid') && $kpi['value'] === currency_format(Money::decimal(1000000))));

        // Selecting the previous month shows that month's payouts.
        $this->get('/payroll?year='.$last->year.'&month='.$last->month)->assertOk()->assertViewHas('kpis', fn ($kpis) => collect($kpis)->contains(
            fn ($kpi) => str_contains($kpi['label'], 'Paid') && $kpi['value'] === currency_format(Money::decimal(2000000))));
    }
}
