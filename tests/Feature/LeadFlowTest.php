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
        $this->get(route('apply.form'))->assertOk()->assertSee('Send inquiry')->assertDontSee('name="cnic"', false);
    }

    public function test_public_interest_is_a_validated_level_code_dropdown(): void
    {
        $page = $this->get(route('apply.form'))->assertOk()->assertSee('<select class="form-select" id="program_interest"', false)
            ->assertSee('name="program_interest" required', false)->assertDontSee('novalidate');
        foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $code) {
            $page->assertSee('value="'.$code.'"', false);
        }
        foreach ([null, ''] as $missing) {
            $this->postJson(route('apply.store'), ['name' => 'Interested', 'phone' => '03001234567', 'program_interest' => $missing])
                ->assertUnprocessable()->assertJsonValidationErrors('program_interest');
        }
        $this->postJson(route('apply.store'), ['name' => 'Interested', 'phone' => '03001234567', 'program_interest' => 'Invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('program_interest');
        $this->post(route('apply.store'), ['name' => 'Interested', 'phone' => '03001234567', 'program_interest' => 'B1'])
            ->assertRedirect(route('apply.thanks'));
        $this->assertSame('B1', Lead::sole()->program_interest);
    }

    public function test_duplicate_phone_is_ignored_even_after_24_hours_without_replacing_original_inquiry(): void
    {
        $data = ['name' => 'Original inquiry', 'phone' => '03001234567', 'program_interest' => 'A1'];
        $this->post(route('apply.store'), $data)->assertRedirect(route('apply.thanks'));
        $this->post(route('apply.store'), array_replace($data, ['name' => 'Repeated']))->assertRedirect(route('apply.thanks'));
        $this->assertSame(1, Lead::count());
        $this->assertSame('Original inquiry', Lead::sole()->name);
        $this->travel(25)->hours();
        $this->post(route('apply.store'), $data)->assertRedirect(route('apply.thanks'));
        $this->assertSame(1, Lead::count());
        $this->assertSame('Original inquiry', Lead::sole()->name);
    }

    public function test_temporary_and_regenerated_links_do_not_allow_repeat_phone_submissions(): void
    {
        $data = ['name' => 'Original inquiry', 'phone' => '03001234567', 'program_interest' => 'A1'];
        $dayLink = URL::temporarySignedRoute('inquiry.form', now()->addDay());
        $weekLink = URL::temporarySignedRoute('inquiry.form', now()->addDays(7));
        $this->post($dayLink, $data)->assertRedirect(route('apply.thanks'));
        $this->post($dayLink, array_replace($data, ['name' => 'Repeated']))->assertRedirect(route('apply.thanks'));
        $this->travel(2)->days();
        $this->post($weekLink, $data)->assertRedirect(route('apply.thanks'));
        $this->travel(8)->days();
        $newLink = URL::temporarySignedRoute('inquiry.form', now()->addDays(7));
        $this->post($newLink, $data)->assertRedirect(route('apply.thanks'));
        $this->assertSame(1, Lead::count());
        $this->assertSame('Original inquiry', Lead::sole()->name);
        $this->post($newLink, array_replace($data, ['phone' => '03001234568']))->assertRedirect(route('apply.thanks'));
        $this->assertSame(2, Lead::count());
    }

    public function test_inquiry_post_is_rate_limited_and_does_not_block_other_ips(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('apply.store'), ['name' => 'Interested '.$i, 'phone' => '0300123400'.$i, 'program_interest' => 'A1'])->assertRedirect();
        }
        $limited = $this->post(route('apply.store'), ['name' => 'Too many', 'phone' => '03001234999'])->assertStatus(429);
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $this->assertSame(5, Lead::count());
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
            ->post(route('apply.store'), ['name' => 'Another visitor', 'phone' => '03001234888', 'program_interest' => 'A1'])->assertRedirect();
        $this->assertSame(6, Lead::count());
    }

    public function test_temporary_inquiry_signature_and_expiry_cover_get_and_post(): void
    {
        $url = URL::temporarySignedRoute('inquiry.form', now()->addDay());
        $this->get($url)->assertOk()->assertSee(e($url), false);
        $this->post($url, ['name' => 'Timed inquiry', 'phone' => '03001234567', 'program_interest' => 'A1'])->assertRedirect(route('apply.thanks'));
        $this->get(route('inquiry.form'))->assertForbidden();
        $this->post(route('inquiry.store'), ['name' => 'Unsigned', 'phone' => '03001234568'])->assertForbidden();
        $this->travel(25)->hours();
        $this->get($url)->assertForbidden();
        $this->post($url, ['name' => 'Expired', 'phone' => '03001234569'])->assertForbidden();
        $this->assertSame(1, Lead::count());
    }

    public function test_admin_can_generate_and_copy_temporary_or_permanent_inquiry_links(): void
    {
        $this->actingAs($this->admin)->get(route('leads.index'))->assertOk()->assertSee('Copy link')
            ->assertSee('Public link')->assertSee('data-inquiry-link-form', false)
            ->assertSee('class="accordion-button collapsed"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('id="inquiry-link-panel" class="accordion-collapse collapse"', false)
            ->assertSee('data-copy-target="inquiry_link"', false)->assertSee(route('apply.form'), false);
        $this->get(route('leads.index', ['link_expiry' => '24h']))->assertOk()
            ->assertSee('/inquiry?expires=', false)->assertSee('signature=', false);
    }

    public function test_ajax_link_generation_returns_signed_links_and_preserves_access_control(): void
    {
        $this->freezeTime();
        foreach (['permanent' => null, '24h' => now()->addDay(), '7d' => now()->addDays(7)] as $expiry => $expiresAt) {
            $url = $expiresAt ? URL::temporarySignedRoute('inquiry.form', $expiresAt) : route('apply.form');
            $this->actingAs($this->admin)->getJson(route('leads.index', ['link_expiry' => $expiry]))
                ->assertOk()->assertExactJson(['linkExpiry' => $expiry, 'inquiryUrl' => $url])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        foreach ([$this->teacher, $this->student] as $user) {
            $this->actingAs($user)->getJson(route('leads.index', ['link_expiry' => '24h']))->assertForbidden();
        }
    }

    public function test_public_submission_creates_a_new_lead(): void
    {
        $this->post(route('apply.store'), [
            'name' => 'Ali Khan',
            'father_name' => 'Khan Sb',
            'cnic' => '3520112345678',
            'phone' => '03001234567',
            'program_interest' => 'A1',
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
            ->assertSessionHasErrors(['name', 'phone', 'program_interest']);
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

    public function test_inquiry_link_and_follow_up_create_no_student_until_qualified_conversion(): void
    {
        $studentCount = User::where('role', 'student')->count();
        $this->post(route('apply.store'), ['name' => 'Interested person', 'phone' => '03001234567', 'program_interest' => 'A1'])
            ->assertRedirect(route('apply.thanks'));
        $lead = Lead::sole();
        $this->assertSame('new', $lead->status);
        $this->assertSame($studentCount, User::where('role', 'student')->count());
        $this->assertNull($lead->student_id);
        $this->actingAs($this->admin)->get(route('leads.index'))->assertOk()->assertSee(route('apply.form'), false)
            ->assertDontSee('value="meta"', false);
        $this->get(route('leads.convert', $lead))->assertStatus(422);
        $this->get(route('leads.show', $lead))->assertOk()->assertSee('Mark as contacted')->assertDontSee('generate application link');
        $this->post(route('leads.contact', $lead))->assertRedirect();
        $this->assertSame('contacted', $lead->fresh()->status);
        $this->post(route('leads.qualify', $lead))->assertRedirect();
        $this->get(route('leads.show', $lead))->assertOk()->assertSee('Convert to student')->assertDontSee('id="prefill"', false);
        $this->post(route('leads.reject', $lead), ['reason' => 'Not ready for the level'])->assertRedirect();
        $this->get(route('leads.convert', $lead))->assertStatus(422);
        $this->assertSame($studentCount, User::where('role', 'student')->count());
        $this->actingAs($this->teacher)->post(route('leads.contact', $lead))->assertForbidden();
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

    public function test_admin_can_register_a_qualified_lead_directly(): void
    {
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03001234567', 'status' => Lead::STATUS_QUALIFIED]);
        $this->actingAs($this->admin)->get(route('leads.convert', $lead))->assertOk()->assertSee('Student admission');
    }

    public function test_show_flags_a_duplicate_existing_student(): void
    {
        User::factory()->create(['role' => 'student', 'name' => 'Existing Dup', 'phone' => '03009998888', 'is_active' => true]);
        $lead = Lead::create(['name' => 'Ali', 'phone' => '03009998888', 'status' => Lead::STATUS_NEW]);

        $this->actingAs($this->admin)->get(route('leads.show', $lead))
            ->assertOk()->assertSee('Existing Dup');
    }

    // ---- Manual leads ---------------------------------------------------------

    public function test_admin_adds_edits_and_deletes_a_manual_lead(): void
    {
        $this->actingAs($this->admin)->get(route('leads.index'))->assertOk()->assertSee('Add lead');
        $this->get(route('leads.create'))->assertOk()->assertSee('Add lead');

        $this->post(route('leads.store'), ['name' => 'Walk In', 'phone' => '03001230000', 'cnic' => '3520112345670',
            'program_interest' => 'A1', 'message' => 'Came to office', 'status' => 'contacted'])->assertRedirect();
        $lead = Lead::sole();
        $this->assertSame([Lead::SOURCE_MANUAL, Lead::STATUS_CONTACTED, 'Walk In', $this->admin->id],
            [$lead->source, $lead->status, $lead->name, $lead->assigned_to]);

        $this->get(route('leads.edit', $lead))->assertOk()->assertSee('Walk In');
        $this->put(route('leads.update', $lead), ['name' => 'Walk In Two', 'phone' => '03001230001', 'status' => 'new'])
            ->assertRedirect(route('leads.show', $lead));
        $this->assertSame(['Walk In Two', '03001230001', 'new'], [$lead->fresh()->name, $lead->fresh()->phone, $lead->fresh()->status]);

        // Workflow statuses cannot be set by hand.
        $this->putJson(route('leads.update', $lead), ['name' => 'X', 'phone' => '0300', 'status' => 'converted'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(route('leads.store'), ['name' => 'Y', 'phone' => 'abc', 'status' => 'new'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->delete(route('leads.destroy', $lead))->assertRedirect(route('leads.index'));
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_converted_and_submitted_leads_are_protected_and_non_admins_are_blocked(): void
    {
        $converted = Lead::create(['name' => 'Done', 'phone' => '03001110000', 'status' => Lead::STATUS_CONVERTED]);
        $submitted = Lead::create(['name' => 'Sent', 'phone' => '03001110001', 'status' => Lead::STATUS_SUBMITTED]);
        $qualified = Lead::create(['name' => 'Qual', 'phone' => '03001110002', 'status' => Lead::STATUS_QUALIFIED]);
        $this->actingAs($this->admin);

        $this->delete(route('leads.destroy', $converted))->assertStatus(422);
        $this->get(route('leads.edit', $converted))->assertStatus(422);
        $this->put(route('leads.update', $submitted), ['name' => 'Z', 'phone' => '0300'])->assertStatus(422);
        // Qualified lead keeps its workflow status; details remain editable.
        $this->put(route('leads.update', $qualified), ['name' => 'Qual Two', 'phone' => '03001110002'])->assertRedirect();
        $this->assertSame(['Qual Two', Lead::STATUS_QUALIFIED], [$qualified->fresh()->name, $qualified->fresh()->status]);
        $this->assertDatabaseCount('leads', 3);

        foreach ([$this->teacher, $this->student] as $user) {
            $this->actingAs($user)->get(route('leads.create'))->assertForbidden();
            $this->post(route('leads.store'), ['name' => 'N', 'phone' => '0300', 'status' => 'new'])->assertForbidden();
            $this->delete(route('leads.destroy', $qualified))->assertForbidden();
        }
    }
}
