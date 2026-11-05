<?php

namespace Tests\Feature;

use App\Http\Controllers\ClassSessionController;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(9, 0));   // a Monday
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $course = Course::create(['name' => 'German A1', 'level' => 'A1', 'total_fee' => 30000, 'teacher_per_student_amount' => 0, 'duration_weeks' => 4]);
        $this->batch = Batch::create(['name' => 'A1 Morning 9-11', 'course_id' => $course->id, 'teacher_id' => $this->teacher->id,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-29', 'status' => 'ongoing', 'max_students' => 20,
            'meeting_platform' => 'zoom', 'meeting_link' => 'https://zoom.example/j/1']);
    }

    private function form(array $overrides = []): array
    {
        return array_replace(['days' => ['mon', 'wed'], 'start_time' => '09:00', 'end_time' => '11:00',
            'from' => '2026-11-02', 'to' => '2026-12-31', 'topic' => 'Class'], $overrides);
    }

    public function test_admin_generates_weekly_sessions_inside_batch_dates_and_skips_existing_dates(): void
    {
        ClassSession::create(['batch_id' => $this->batch->id, 'session_date' => '2026-11-09', 'start_time' => '10:00',
            'end_time' => '12:00', 'topic' => 'Already planned', 'status' => 'scheduled']);
        $this->actingAs($this->admin)->get('/batches/'.$this->batch->id)->assertOk()->assertSee('Advanced: generate missing classes');

        // The end date is clamped to the batch end (29 Nov): Mondays and Wednesdays give 8 dates, 9 Nov already exists.
        $this->post('/batches/'.$this->batch->id.'/sessions/generate', $this->form())
            ->assertRedirect('/batches/'.$this->batch->id)->assertSessionHas('success', '7 sessions created, 1 dates already had a session.');
        $sessions = ClassSession::where('batch_id', $this->batch->id)->orderBy('session_date')->get();
        $this->assertSame(['2026-11-02', '2026-11-04', '2026-11-09', '2026-11-11', '2026-11-16', '2026-11-18', '2026-11-23', '2026-11-25'],
            $sessions->map(fn ($session) => $session->session_date->toDateString())->all());
        $generated = $sessions->firstWhere('session_date', $sessions->first()->session_date);
        $this->assertSame(['09:00', '11:00', 'Class', 'scheduled', 'https://zoom.example/j/1'],
            [$generated->start_time->format('H:i'), $generated->end_time->format('H:i'), $generated->topic, $generated->status, $generated->meeting_link]);
        $this->assertSame('Already planned', $sessions->firstWhere(fn ($session) => $session->session_date->toDateString() === '2026-11-09')->topic);
        $this->assertSame(7, AuditLog::where('action', 'class_sessions.generated')->sole()->changes['created']);

        $this->post('/batches/'.$this->batch->id.'/sessions/generate', $this->form())->assertSessionHas('success', '0 sessions created, 8 dates already had a session.');
        $this->assertDatabaseCount('class_sessions', 8);
    }

    public function test_invalid_requests_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $url = '/batches/'.$this->batch->id.'/sessions/generate';
        $this->post($url, $this->form(['days' => []]))->assertSessionHasErrors('days');
        $this->post($url, $this->form(['days' => ['funday']]))->assertSessionHasErrors('days.0');
        $this->post($url, $this->form(['end_time' => '08:00']))->assertSessionHasErrors('end_time');
        $this->post($url, $this->form(['from' => '2026-11-01']))->assertSessionHasErrors('from');
        $this->post($url, $this->form(['from' => '2026-12-01', 'to' => '2026-12-31']))->assertSessionHasErrors('days');

        $this->batch->update(['end_date' => null]);
        $this->post($url, $this->form(['days' => array_keys(ClassSessionController::WEEKDAYS), 'to' => '2027-12-31']))->assertSessionHasErrors('to');
        $this->batch->update(['status' => 'cancelled']);
        $this->post($url, $this->form())->assertSessionHasErrors('days');
        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_only_admin_or_the_batch_teacher_can_generate(): void
    {
        $url = '/batches/'.$this->batch->id.'/sessions/generate';
        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->post($url, $this->form())->assertForbidden();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->post($url, $this->form())->assertForbidden();
        $this->assertDatabaseCount('class_sessions', 0);

        $this->actingAs($this->teacher)->get('/batches/'.$this->batch->id)->assertOk()->assertSee('Extra / makeup class')
            ->assertDontSee('Advanced: generate missing classes');
        $this->post($url, $this->form(['days' => ['fri']]))->assertSessionHas('success', '4 sessions created.');
    }
}
