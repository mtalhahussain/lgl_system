<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $course = Course::create(['name' => 'German', 'level' => 'A1', 'total_fee' => '100.01', 'teacher_per_student_amount' => 0, 'duration_weeks' => 12, 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->batch = Batch::create(['name' => 'Upcoming A1', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => today()->addWeek(), 'end_date' => today()->addMonths(3), 'status' => 'upcoming', 'max_students' => 10]);
    }

    private function data(): array
    {
        return ['name' => 'New Learner', 'email' => 'admission@example.com', 'phone' => '03001234567',
            'address' => 'Karachi', 'father_name' => 'Father', 'cnic' => '3520112345678',
            'batch_id' => $this->batch->id, 'discount_amount' => '10.00', 'installments' => 3];
    }

    public function test_registration_and_admission_are_one_transaction_with_exact_fee_plan(): void
    {
        $this->get('/students/create')->assertOk()->assertSee('Upcoming A1')->assertSee('Fee installments');
        $this->post('/students', $this->data())->assertRedirect('/students')->assertSessionHasNoErrors();
        $student = User::where('email', 'admission@example.com')->sole();
        $enrollment = Enrollment::sole();
        $this->assertSame('student', $student->role);
        $this->assertSame('Father', $student->father_name);
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame('100.01', $enrollment->total_fee);
        $this->assertSame('10.00', $enrollment->discount_amount);
        $this->assertCount(3, $enrollment->feeInstallments);
        $this->assertSame(9001, $enrollment->feeInstallments->sum(fn ($row) => Money::minor($row->amount)));
        $this->post('/students', $this->data())->assertSessionHasErrors('email');
        $this->assertSame(1, Enrollment::count());
    }

    public function test_failed_admission_leaves_no_student_or_fee_plan(): void
    {
        $this->batch->update(['max_students' => 0]);
        $this->from('/students/create')->post('/students', $this->data())->assertRedirect('/students/create')
            ->assertSessionHasErrors('batch_id')->assertSessionHasInput('name', 'New Learner');
        $this->assertDatabaseMissing('users', ['email' => 'admission@example.com']);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('fee_installments', 0);
    }

    public function test_open_leads_use_shared_registration_form_and_cannot_convert_twice(): void
    {
        foreach (['qualified', 'submitted'] as $status) {
            $lead = Lead::create(['name' => 'Latest '.$status, 'phone' => '03001234567', 'email' => $status.'@example.com',
                'status' => $status, 'raw_payload' => ['name' => 'Old name']]);
            $this->get(route('leads.convert', $lead))->assertOk()->assertSee('Latest '.$status)
                ->assertSee('Fee installments')->assertSee('Upcoming A1');
            $this->post(route('leads.convert.store', $lead), array_replace($this->data(), ['email' => $status.'@example.com']))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('converted', $lead->fresh()->status);
            $this->assertNotNull($lead->fresh()->student_id);
            $this->post(route('leads.convert.store', $lead), $this->data())->assertStatus(422);
        }
        $this->assertSame(2, Enrollment::count());
    }

    public function test_failed_lead_admission_preserves_open_lead(): void
    {
        $lead = Lead::create(['name' => 'Interested', 'phone' => '03001234567', 'status' => 'qualified']);
        $this->batch->update(['status' => 'cancelled']);
        $this->post(route('leads.convert.store', $lead), $this->data())->assertSessionHasErrors('batch_id');
        $this->assertSame('qualified', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->student_id);
        $this->assertDatabaseMissing('users', ['email' => 'admission@example.com']);
        $lead->update(['status' => 'rejected']);
        $this->get(route('leads.convert', $lead))->assertStatus(422);
    }

    public function test_profile_only_registration_and_permissions_remain_explicit(): void
    {
        $this->post('/students', array_replace($this->data(), ['batch_id' => null]))->assertRedirect('/students')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('enrollments', 0);
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            $this->get('/students/create')->assertForbidden();
            $this->post('/students', $this->data())->assertForbidden();
        }
    }

    public function test_existing_student_uses_same_form_and_exact_fixed_discount_without_duplicate_account(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'phone' => '03009998888',
            'father_name' => 'Saved father', 'cnic' => '3520112345678', 'address' => 'Saved address',
            'date_of_birth' => '2000-01-02', 'emergency_contact' => '03001112222']);
        $before = User::count();
        foreach (['/students/create', '/enrollments/create'] as $url) {
            $page = $this->get($url.'?student_id='.$student->id)->assertOk()->assertViewIs('admin.people.form')->assertSee('Student admission')
                ->assertSee('name="discount_amount"', false)->assertDontSee('name="discount_percentage"', false)
                ->assertSee('name="email"', false)->assertSee('value="Saved father"', false)
                ->assertSee('value="Saved address"', false)->assertSee('value="2000-01-02"', false)
                ->assertSee('value="03009998888"', false)->assertSee('readonly', false);
            $this->assertSame(1, substr_count($page->getContent(), 'id="batch_id"'));
            $this->assertSame(1, substr_count($page->getContent(), 'id="discount_amount"'));
        }
        $this->post('/students', ['student_id' => $student->id, 'batch_id' => $this->batch->id, 'discount_amount' => '7.13', 'installments' => 3,
            'name' => 'Tampered', 'email' => 'tampered@example.com', 'phone' => 'letters', 'address' => 'Changed',
            'father_name' => 'Changed', 'date_of_birth' => 'invalid', 'cnic' => 'invalid', 'is_active' => 0])
            ->assertRedirect('/students')->assertSessionHasNoErrors();
        $this->assertSame($before, User::count());
        $this->assertSame('Saved father', $student->fresh()->father_name);
        $this->assertSame('Saved address', $student->fresh()->address);
        $this->assertSame('03009998888', $student->fresh()->phone);
        $this->assertTrue($student->fresh()->is_active);
        $this->assertSame('7.13', Enrollment::sole()->discount_amount);
        $this->assertSame(9288, Enrollment::sole()->feeInstallments->sum(fn ($row) => Money::minor($row->amount)));
    }

    public function test_excess_fixed_discount_rolls_back_registration(): void
    {
        $this->post('/students', array_replace($this->data(), ['discount_amount' => '100.02']))->assertSessionHasErrors('discount_amount');
        $this->assertDatabaseMissing('users', ['email' => 'admission@example.com']);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_shared_form_accepts_custom_schedule_with_fixed_discount(): void
    {
        $this->post('/students', array_replace($this->data(), ['discount_amount' => '7.13', 'schedule' => [
            ['amount' => '40.00', 'due_date' => today()->toDateString()],
            ['amount' => '52.88', 'due_date' => today()->addMonth()->toDateString()],
            ['amount' => '', 'due_date' => ''],
        ]]))->assertRedirect('/students')->assertSessionHasNoErrors();
        $this->assertSame(['40.00', '52.88'], Enrollment::sole()->feeInstallments->pluck('amount')->all());
        $this->assertSame('7.13', Enrollment::sole()->discount_amount);
    }

    public function test_students_table_contains_admission_columns_and_package_action_without_second_tab(): void
    {
        $this->post('/students', $this->data())->assertSessionHasNoErrors();
        $this->get('/students')->assertOk()->assertSee('Multi-level package')->assertDontSee('Enrollment status')->assertDontSee('>Admitted<', false)
            ->assertSee('Discount')->assertSee('Outstanding')->assertSee('Upcoming A1')->assertSee('A1')
            ->assertSee(route('enrollments.show', Enrollment::sole()), false)
            ->assertDontSee('Students &amp; Admissions', false)->assertDontSee('aria-label="Students and admissions"', false);
    }

    public function test_missing_profile_fields_are_editable_validated_and_saved_with_admission(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'phone' => null,
            'address' => null, 'father_name' => null, 'cnic' => null, 'date_of_birth' => null, 'emergency_contact' => null]);
        $originalName = $student->name;
        $html = $this->get('/students/create?student_id='.$student->id)->assertOk()->getContent();
        foreach (['phone', 'address', 'father_name', 'cnic', 'date_of_birth', 'emergency_contact'] as $field) {
            preg_match('/<input[^>]*id="'.$field.'"[^>]*>/', $html, $match);
            $this->assertNotEmpty($match);
            $this->assertStringNotContainsString('readonly', $match[0]);
        }
        $data = ['student_id' => $student->id, 'batch_id' => $this->batch->id, 'name' => 'Overwrite attempt',
            'phone' => '03009998888', 'address' => 'Added address', 'father_name' => 'Added father',
            'cnic' => '3520112345678', 'date_of_birth' => '2001-02-03', 'emergency_contact' => '03001112222'];
        $this->post('/students', array_replace($data, ['phone' => 'invalid']))->assertSessionHasErrors('phone');
        $this->assertNull($student->fresh()->father_name);
        $this->post('/students', $data)->assertRedirect('/students')->assertSessionHasNoErrors();
        $this->assertSame($originalName, $student->fresh()->name);
        $this->assertSame('Added father', $student->fresh()->father_name);
        $this->assertSame('Added address', $student->fresh()->address);
        $this->assertSame('03009998888', $student->fresh()->phone);
        $this->assertSame('2001-02-03', $student->fresh()->date_of_birth->format('Y-m-d'));
    }

    public function test_profile_completion_rolls_back_with_failed_admission_and_cannot_overwrite_concurrent_fill(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'father_name' => null]);
        $this->batch->update(['max_students' => 0]);
        $data = ['student_id' => $student->id, 'batch_id' => $this->batch->id, 'father_name' => 'Unsaved father'];
        $this->post('/students', $data)->assertSessionHasErrors('batch_id');
        $this->assertNull($student->fresh()->father_name);
        $this->batch->update(['max_students' => 10]);
        $student->update(['father_name' => 'Filled by another request']);
        app(\App\Services\StudentRegistrationService::class)->register($data);
        $this->assertSame('Filled by another request', $student->fresh()->father_name);
    }
}
