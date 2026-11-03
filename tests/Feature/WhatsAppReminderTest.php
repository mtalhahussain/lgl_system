<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\EnrollmentService;
use App\Services\FeeReminderService;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Enrollment $unpaid;

    private Enrollment $paid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->admin);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Sara Khan', 'phone' => '0300-1234567']);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 3000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 8]);
        $batch = Batch::create(['name' => 'A1 Morning', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => today(), 'end_date' => today()->addMonths(2), 'status' => 'ongoing', 'max_students' => 20]);
        $other = Batch::create(['name' => 'A1 Evening', 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'start_date' => today(), 'end_date' => today()->addMonths(2), 'status' => 'ongoing', 'max_students' => 20]);
        $service = app(EnrollmentService::class);
        $this->unpaid = $service->enrollStudent($this->student->id, $batch->id, 0, 1, [
            ['amount' => '1000.00', 'due_date' => today()->toDateString()],
            ['amount' => '1000.00', 'due_date' => today()->addDays(3)->toDateString()],
            ['amount' => '1000.00', 'due_date' => today()->addDays(7)->toDateString()],
        ]);
        $this->paid = $service->enrollStudent($this->student->id, $other->id, 0, 1);
        app(PaymentService::class)->pay($this->paid->id, '3000.00', 'cash', (string) Str::uuid(), $this->admin);
    }

    private function connect(): void
    {
        config(['whatsapp.token' => 'test-token', 'whatsapp.phone_number_id' => '1234567890']);
        Setting::where('key', 'whatsapp_reminders_enabled')->update(['value' => '1']);
    }

    private function fakeOk(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::sequence()->whenEmpty(Http::response(['messages' => [['id' => 'wamid.TEST']]], 200))]);
    }

    public function test_daily_command_sends_before_due_and_due_today_once_each(): void
    {
        $this->fakeOk();
        $this->artisan('fees:whatsapp-reminders')->assertExitCode(1);   // switched off
        Setting::where('key', 'whatsapp_reminders_enabled')->update(['value' => '1']);
        $this->artisan('fees:whatsapp-reminders')->assertExitCode(1);   // no credentials
        Http::assertNothingSent();

        $this->connect();
        $this->artisan('fees:whatsapp-reminders')->assertExitCode(0);
        $this->assertSame(['before_due', 'due_today'], WhatsappMessage::orderBy('kind')->pluck('kind')->all());
        $this->assertSame(['sent'], WhatsappMessage::distinct()->pluck('status')->all());
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '923001234567' && $request['template']['name'] === 'fee_reminder' && $request['template']['language']['code'] === 'en'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'Sara Khan'
            && $request['template']['components'][0]['parameters'][2]['text'] === today()->addDays(3)->format('d M Y')
            && str_contains($request['template']['components'][0]['parameters'][3]['text'], 'A1 Morning'));
        $this->assertSame('wamid.TEST', WhatsappMessage::first()->provider_message_id);

        // Re-running the same day sends nothing new; the fully paid enrollment and the +7 installment never qualify.
        $this->artisan('fees:whatsapp-reminders')->assertExitCode(0);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertSame([$this->unpaid->id], WhatsappMessage::distinct()->pluck('enrollment_id')->all());
    }

    public function test_api_errors_invalid_phones_and_inactive_students_are_logged_not_retried(): void
    {
        $this->connect();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist', 'code' => 132001]], 400)]);
        $this->artisan('fees:whatsapp-reminders')->assertExitCode(1);
        $failed = WhatsappMessage::where('kind', 'due_today')->sole();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('132001 Template name does not exist', $failed->error);
        $this->assertStringNotContainsString('test-token', $failed->error);

        $this->student->update(['phone' => '12345']);
        $result = app(FeeReminderService::class)->sendManual($this->unpaid, $this->admin);
        $this->assertSame(['failed', 'Missing or invalid phone number on the student profile.'], [$result->status, $result->error]);
        Http::assertSentCount(2);

        WhatsappMessage::query()->get()->each(fn ($message) => $this->assertSame('failed', $message->status));
        $this->travel(1)->days();
        $this->student->update(['is_active' => false]);
        $this->artisan('fees:whatsapp-reminders');
        Http::assertSentCount(2);
        $this->expectException(\LogicException::class);
        $failed->update(['phone' => '923000000000']);
    }

    public function test_admin_manual_reminder_targets_earliest_unpaid_installment_once_per_day(): void
    {
        $this->connect();
        $this->fakeOk();
        $this->post('/enrollments/'.$this->unpaid->id.'/whatsapp-reminder')->assertRedirect()->assertSessionHas('success', 'WhatsApp reminder sent to Sara Khan.');
        $message = WhatsappMessage::sole();
        $this->assertSame(['manual', $this->admin->id, today()->format('d M Y')], [$message->kind, $message->sent_by, $message->params[2]]);
        $this->post('/enrollments/'.$this->unpaid->id.'/whatsapp-reminder')->assertSessionHasErrors('whatsapp');
        $this->travel(25)->hours();
        $this->post('/enrollments/'.$this->unpaid->id.'/whatsapp-reminder')->assertSessionHasNoErrors();
        $this->post('/enrollments/'.$this->paid->id.'/whatsapp-reminder')->assertSessionHasErrors('whatsapp');
        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->get('/fees')->assertOk()->assertSee('Send WhatsApp reminder');

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->post('/enrollments/'.$this->unpaid->id.'/whatsapp-reminder')->assertForbidden();
        $this->get('/whatsapp')->assertForbidden();
        $this->actingAs($this->student)->get('/whatsapp')->assertForbidden();
    }

    public function test_settings_page_test_message_and_log(): void
    {
        $this->get('/whatsapp')->assertOk()->assertSee('WhatsApp is not connected yet')->assertSee('Assalam o Alaikum {{1}}', false);
        $this->put('/whatsapp/settings', ['days_before' => 2, 'template' => 'Bad Name', 'language' => 'en'])->assertSessionHasErrors('template');
        $this->put('/whatsapp/settings', ['enabled' => 1, 'days_before' => 2, 'template' => 'lgl_fee_due', 'language' => 'en_US'])->assertRedirect('/whatsapp');
        $settings = app(FeeReminderService::class)->settings();
        $this->assertSame([true, 2, false, 'lgl_fee_due', 'en_US'], array_values($settings));
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp.settings_updated']);

        config(['whatsapp.token' => 'test-token', 'whatsapp.phone_number_id' => '1234567890']);
        $this->fakeOk();
        $this->post('/whatsapp/test', ['phone' => '+92 300 7654321'])->assertSessionHas('success');
        $test = WhatsappMessage::sole();
        $this->assertSame(['test', null, '923007654321', 'lgl_fee_due'], [$test->kind, $test->student_id, $test->phone, $test->template]);
        $this->get('/whatsapp')->assertOk()->assertSee('Test message')->assertSee('923007654321')->assertDontSee('WhatsApp is not connected yet');
        $this->get('/whatsapp?status=failed')->assertOk()->assertDontSee('923007654321');
        $this->get('/whatsapp?export=csv')->assertOk();
    }

    public function test_integrations_live_under_settings_not_the_sidebar(): void
    {
        $this->get('/settings')->assertOk()->assertSee('Integrations')
            ->assertSee(route('whatsapp.index'), false)->assertSee(route('biometric.index'), false)
            ->assertSee('Automatic reminders off')->assertSee('0 active machines');
        $this->get('/students')->assertOk()->assertDontSee('WhatsApp Reminders</a>', false)->assertDontSee('Attendance Machine</a>', false)
            ->assertSee('Staff Attendance');
        $this->get('/whatsapp')->assertOk()->assertSee('Back to Settings');
        $this->get('/biometric')->assertOk()->assertSee('Back to Settings');
    }

    public function test_phone_numbers_are_normalised_to_international_form(): void
    {
        $service = app(WhatsAppService::class);
        $this->assertSame('923001234567', $service->normalizePhone('0300-1234567'));
        $this->assertSame('923001234567', $service->normalizePhone('+92 300 1234567'));
        $this->assertSame('923001234567', $service->normalizePhone('3001234567'));
        $this->assertSame('971501234567', $service->normalizePhone('00971 50 123 4567'));
        $this->assertNull($service->normalizePhone('12345'));
        $this->assertNull($service->normalizePhone(null));
    }
}
