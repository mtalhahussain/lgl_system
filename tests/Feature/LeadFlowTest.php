<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LeadFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
    }

    private function completeUrl(Lead $lead): string
    {
        return URL::temporarySignedRoute('apply.complete', now()->addHours(2), ['lead' => $lead->id]);
    }

    // ---- Public intake --------------------------------------------------------

    public function test_public_can_view_apply_form(): void
    {
        $this->get(route('apply.form'))->assertOk()->assertSee('Apply for admission');
    }

    public function test_public_submission_creates_a_new_lead(): void
    {
        $this->post(route('apply.store'), [
            'name' => 'Ali Khan',
            'father_name' => 'Khan Sb',
            'cnic' => '3520112345678',
            'phone' => '03001234567',
        ])->assertRedirect(route('apply.thanks'));

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertSame('Ali Khan', $lead->name);
        $this->assertSame(Lead::STATUS_NEW, $lead->status);
        $this->assertSame(Lead::SOURCE_PUBLIC, $lead->source);
        $this->assertSame('3520112345678', $lead->raw_payload['cnic']);
    }

    public function test_honeypot_submission_is_silently_dropped(): void
    {
        $this->post(route('apply.store'), [
            'name' => 'Spam Bot',
            'phone' => '03000000000',
            'website' => 'http://spam.example',
        ])->assertRedirect(route('apply.thanks'));

        $this->assertSame(0, Lead::count());
    }

    public function test_intake_validates_required_fields(): void
    {
        $this->post(route('apply.store'), ['name' => ''])
            ->assertSessionHasErrors(['name', 'phone']);
    }

    // ---- Admin pipeline access control ---------------------------------------

    public function test_guest_cannot_see_leads(): void
    {
        $this->get(route('leads.index'))->assertRedirect(route('login'));
    }

    public function test_teacher_and_student_cannot_see_leads(): void
    {
        $lead = Lead::create(['name' => 'X', 'phone' => '03001112222', 'status' => Lead::STATUS_NEW]);
        $this->actingAs($this->teacher)->get(route('leads.index'))->assertForbidden();
        $this->actingAs($this->student)->get(route('leads.show', $lead))->assertForbidden();
    }

    // ---- Qualify + prefill link ----------------------------------------------

    public function test_admin_qualifies_a_lead(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'cnic' => '3520112345678', 'status' => Lead::STATUS_NEW]);

        $this->actingAs($this->admin)->post(route('leads.qualify', $lead))
            ->assertRedirect(route('leads.show', $lead));

        $lead->refresh();
        $this->assertSame(Lead::STATUS_QUALIFIED, $lead->status);
        $this->assertSame($this->admin->id, $lead->verified_by);
        $this->assertNotNull($lead->verified_at);
    }

    public function test_prefill_link_requires_a_valid_signature(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_QUALIFIED]);

        // Unsigned direct hit is rejected by the signed middleware.
        $this->get(route('apply.complete', $lead))->assertForbidden();
    }

    public function test_prefill_link_rejects_a_lead_that_is_not_qualified(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_NEW]);

        $this->get($this->completeUrl($lead))->assertStatus(410);
    }

    public function test_applicant_completes_a_qualified_lead(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'cnic' => '3520112345678', 'status' => Lead::STATUS_QUALIFIED]);

        $this->get($this->completeUrl($lead))->assertOk()->assertSee('Complete your registration');

        $this->post($this->completeUrl($lead), [
            'name' => 'Ali Khan',
            'father_name' => 'Khan Sb',
            'phone' => '03009998888',
            'email' => 'ali@example.com',
            'address' => 'House 1, Lahore',
            'date_of_birth' => '2000-01-01',
        ])->assertRedirect(route('apply.thanks'));

        $lead->refresh();
        $this->assertSame(Lead::STATUS_SUBMITTED, $lead->status);
        $this->assertNotNull($lead->submitted_at);
        $this->assertSame('House 1, Lahore', $lead->submitted_payload['address']);
        // CNIC stays the verified value even though the form never submits it.
        $this->assertSame('3520112345678', $lead->submitted_payload['cnic']);
        $this->assertSame('03009998888', $lead->phone);
    }

    public function test_completion_is_one_time(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_QUALIFIED]);
        $url = $this->completeUrl($lead);

        $this->post($url, ['name' => 'Ali Khan', 'phone' => '03001234567', 'address' => 'Addr'])
            ->assertRedirect(route('apply.thanks'));

        // Second submit is blocked (status is no longer qualified).
        $this->post($url, ['name' => 'Ali Khan', 'phone' => '03001234567', 'address' => 'Addr'])
            ->assertStatus(410);
    }

    // ---- Reject ---------------------------------------------------------------

    public function test_admin_rejects_a_lead_with_reason(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_NEW]);

        $this->actingAs($this->admin)->post(route('leads.reject', $lead), ['reason' => 'Not interested'])
            ->assertRedirect(route('leads.show', $lead));

        $lead->refresh();
        $this->assertSame(Lead::STATUS_REJECTED, $lead->status);
        $this->assertSame('Not interested', $lead->rejected_reason);
    }

    public function test_reject_requires_a_reason(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_NEW]);
        $this->actingAs($this->admin)->post(route('leads.reject', $lead), ['reason' => 'no'])
            ->assertSessionHasErrors('reason');
    }

    // ---- Convert to student ---------------------------------------------------

    public function test_admin_converts_a_submitted_lead_into_a_student(): void
    {
        $lead = Lead::create([
            'name' => 'Ali Khan',
            'phone' => '03009998888',
            'cnic' => '3520112345678',
            'father_name' => 'Khan Sb',
            'status' => Lead::STATUS_SUBMITTED,
            'submitted_payload' => ['address' => 'House 1, Lahore'],
        ]);

        $this->actingAs($this->admin)->get(route('leads.convert', $lead))->assertOk();

        $response = $this->actingAs($this->admin)->post(route('leads.convert.store', $lead), [
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'father_name' => 'Khan Sb',
            'cnic' => '3520112345678',
            'phone' => '03009998888',
            'address' => 'House 1, Lahore',
        ]);

        $student = User::where('email', 'ali@example.com')->first();
        $this->assertNotNull($student);
        $this->assertSame('student', $student->role);
        $this->assertSame('3520112345678', $student->cnic);
        $this->assertSame('Khan Sb', $student->father_name);
        $response->assertRedirect(route('students.show', $student));

        $lead->refresh();
        $this->assertSame(Lead::STATUS_CONVERTED, $lead->status);
        $this->assertSame($student->id, $lead->student_id);
        $this->assertNotNull($lead->converted_at);
    }

    public function test_cannot_convert_a_lead_that_has_not_been_submitted(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_QUALIFIED]);
        $this->actingAs($this->admin)->get(route('leads.convert', $lead))->assertStatus(422);
    }

    public function test_show_flags_a_duplicate_existing_student(): void
    {
        User::factory()->create(['role' => 'student', 'name' => 'Existing Dup', 'phone' => '03009998888', 'is_active' => true]);
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03009998888', 'status' => Lead::STATUS_NEW]);

        $this->actingAs($this->admin)->get(route('leads.show', $lead))
            ->assertOk()->assertSee('Existing Dup');
    }
}
