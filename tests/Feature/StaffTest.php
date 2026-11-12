<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\BiometricPunch;
use App\Models\TeacherDue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->admin);
    }

    private function addStaff(array $overrides = []): User
    {
        $this->post('/staff', array_replace(['name' => 'Guard Gul', 'designation' => 'Security guard', 'phone' => '03001112233',
            'monthly_salary' => '25000', 'hire_date' => '2026-10-01'], $overrides))->assertSessionHasNoErrors()->assertRedirect('/accounts?role=staff');

        return User::where('role', 'staff')->latest('id')->firstOrFail();
    }

    public function test_admin_adds_staff_who_is_listed_in_users_but_cannot_sign_in(): void
    {
        $this->get('/accounts')->assertOk()->assertSee('Add staff')->assertSee(route('staff.create'), false);
        $this->get('/staff/create')->assertOk()->assertSee('Receptionist')->assertSee('Back to Users');
        $this->post('/staff', ['name' => 'No Job', 'phone' => '0300', 'monthly_salary' => ''])->assertSessionHasErrors(['designation', 'monthly_salary']);

        $guard = $this->addStaff();
        $this->assertSame(['staff', 'Security guard', '25000.00', 'monthly', true], [$guard->role, $guard->designation, (string) $guard->monthly_salary, $guard->salary_type, $guard->is_active]);
        $this->assertSame('STF'.str_pad((string) $guard->id, 4, '0', STR_PAD_LEFT), $guard->employee_id);
        $this->assertNull($guard->device_employee_no);
        $this->assertStringEndsWith('@staff.invalid', $guard->email);

        $this->get('/accounts?role=staff')->assertOk()->assertSee('Guard Gul')->assertSee('Security guard · 03001112233')
            ->assertDontSee('@staff.invalid')->assertSee(route('staff.edit', $guard), false);
        $this->get('/accounts')->assertOk()->assertSee('Staff');

        // Even with a known password, staff cannot sign in, and an existing session is ended.
        $guard->forceFill(['password' => Hash::make('password123')])->save();
        auth()->logout();
        $this->post('/login', ['email' => $guard->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($guard)->get('/profile')->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_staff_edit_activation_and_role_changes_are_controlled(): void
    {
        $guard = $this->addStaff();
        $this->get('/staff/'.$guard->id.'/edit')->assertOk()->assertSee('Guard Gul');
        $this->put('/staff/'.$guard->id, ['name' => 'Guard Gul Khan', 'designation' => 'Night guard', 'phone' => '0300', 'monthly_salary' => '27000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Guard Gul Khan', 'Night guard', '27000.00', null], [$guard->fresh()->name, $guard->fresh()->designation,
            (string) $guard->fresh()->monthly_salary, $guard->fresh()->device_employee_no]);

        $this->patchJson('/accounts/'.$guard->id, ['role' => 'staff', 'is_active' => false])->assertOk();
        $this->assertFalse($guard->fresh()->is_active);
        $this->patchJson('/accounts/'.$guard->id, ['role' => 'teacher', 'is_active' => true])->assertUnprocessable();
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->patchJson('/accounts/'.$teacher->id, ['role' => 'staff', 'is_active' => true])->assertUnprocessable();
        $this->get('/staff/'.$teacher->id.'/edit')->assertNotFound();
        $this->assertSame('teacher', $teacher->fresh()->role);

        $this->get('/batches/create')->assertOk()->assertDontSee('Guard Gul Khan');
        $this->actingAs($teacher)->get('/staff/create')->assertForbidden();
        $this->post('/staff', ['name' => 'X'])->assertForbidden();
    }

    public function test_staff_salary_is_generated_with_teacher_salaries(): void
    {
        $this->addStaff(['name' => 'Rania Reception', 'designation' => 'Receptionist', 'monthly_salary' => '30000']);
        $left = $this->addStaff(['name' => 'Left Cleaner', 'designation' => 'Cleaner', 'monthly_salary' => '18000']);
        $left->update(['is_active' => false]);
        User::factory()->create(['role' => 'teacher', 'is_active' => true, 'salary_type' => 'monthly', 'monthly_salary' => 50000]);

        $this->post('/payroll/salaries', ['period' => now()->format('Y-m')])->assertRedirect('/payroll');
        $this->assertEqualsCanonicalizing(['30000.00', '50000.00'], TeacherDue::where('kind', 'salary')->pluck('amount')->all());
        $this->get('/payroll')->assertOk()->assertSee('Payroll')->assertSee('Rania Reception')->assertSee('Person');
        $due = TeacherDue::whereHas('teacher', fn ($query) => $query->where('role', 'staff'))->sole();
        $this->post('/payroll/dues/'.$due->id.'/payouts', ['amount' => '30000', 'method' => 'cash', 'submission_key' => 'staff-payout-key-0001'])->assertRedirect();
        $this->get('/payroll/payouts/'.$due->payouts()->sole()->id)->assertOk()->assertSee('Salary Payment Slip')->assertSee('Rania Reception');
    }

    public function test_staff_are_synced_to_the_machine_and_punches_show_in_staff_attendance(): void
    {
        $this->post('/biometric/devices', ['name' => 'Gate', 'timezone' => 'Asia/Karachi']);
        $device = BiometricDevice::sole()->makeVisible('webhook_token');
        $guard = $this->addStaff();
        $this->assertSame('upsert', BiometricDeviceUser::where('user_id', $guard->id)->sole()->action);

        $this->travelTo(today()->setTime(21, 0));
        $event = fn (string $time) => ['dateTime' => $time, 'AccessControllerEvent' => ['majorEventType' => 5, 'subEventType' => 75, 'employeeNoString' => $guard->device_employee_no]];
        foreach (['08:01:00', '20:05:00'] as $time) {
            $this->call('POST', '/biometric/hook/'.$device->webhook_token, [], [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode($event(today()->toDateString().'T'.$time)))->assertOk();
        }
        $this->assertSame(['staff', 'staff'], BiometricPunch::pluck('result')->all());
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Included Teacher', 'is_active' => true]);
        $teacherEvent = $event(today()->toDateString().'T20:06:00');
        $teacherEvent['AccessControllerEvent']['employeeNoString'] = $teacher->device_employee_no;
        $this->call('POST', '/biometric/hook/'.$device->webhook_token, [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode($teacherEvent))->assertOk();
        $this->get('/biometric/teachers?month='.today()->format('Y-m'))->assertOk()->assertSee('Staff member')
            ->assertSee('Guard Gul')->assertSee('Security guard')->assertSee('Check-in')->assertSee('Check-out')->assertSee('08:01:00 AM')->assertSee('08:05:00 PM')
            ->assertSee('Included Teacher')
            ->assertViewHas('teachers', fn ($people) => $people->count() === 2 && $people->has($guard->id) && $people->has($teacher->id))
            ->assertViewHas('days', fn ($days) => $days->count() === 2 && $days->firstWhere('user_id', $guard->id)->punches === 2);
        $this->get('/biometric/teachers?month='.today()->format('Y-m').'&teacher_id='.$teacher->id)->assertOk()
            ->assertSee('Included Teacher')->assertViewHas('days', fn ($days) => $days->count() === 1 && $days->first()->user_id === $teacher->id);
        $this->get('/biometric/teachers?month='.today()->format('Y-m').'&teacher_id='.$guard->id)->assertOk()
            ->assertViewHas('days', fn ($days) => $days->count() === 1 && $days->first()->user_id === $guard->id);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Excluded Student']);
        $this->get('/biometric/teachers?month='.today()->format('Y-m').'&teacher_id='.$student->id)->assertOk()
            ->assertDontSee('Excluded Student')->assertViewHas('teachers', fn ($people) => ! $people->has($student->id))
            ->assertViewHas('days', fn ($days) => $days->every(fn ($day) => in_array($day->user_id, [$guard->id, $teacher->id], true)));
        $this->get('/biometric?result=teacher')->assertOk()->assertSee('Included Teacher');
        $this->get('/biometric?result=staff')->assertOk()->assertSee('Staff punch');
    }

    public function test_unified_attendance_tabs_scope_people_dates_summaries_and_punch_details(): void
    {
        $this->post('/biometric/devices', ['name' => 'Test gate', 'timezone' => 'Asia/Karachi']);
        $device = BiometricDevice::sole();
        $this->addStaff();
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Tab Teacher', 'is_active' => true]);
        $this->get('/attendance?tab=teachers')->assertOk()->assertSee('Tab Teacher')->assertDontSee('Guard Gul')
            ->assertSee('No punches recorded')->assertViewHas('kpis', fn ($items) => $items[2]['value'] === 1)
            ->assertViewHas('from', today()->toDateString())->assertViewHas('to', today()->toDateString());
        $this->get('/attendance?tab=staff')->assertOk()->assertSee('Guard Gul')->assertDontSee('Tab Teacher');
        foreach (['2026-11-02 09:00:00', '2026-11-02 17:00:00', '2026-11-03 10:00:00'] as $time) {
            BiometricPunch::create(['biometric_device_id' => $device->id, 'employee_no' => $teacher->device_employee_no,
                'user_id' => $teacher->id, 'punched_at' => $time, 'result' => 'teacher']);
        }
        $this->get('/attendance?tab=teachers&from=2026-11-02&to=2026-11-02&teacher_id='.$teacher->id)->assertOk()
            ->assertSee('Details')->assertSee('Test gate')->assertSee('05:00:00 PM')->assertSee('Punches recorded')
            ->assertViewHas('days', fn ($days) => $days->count() === 1 && $days->first()->punches === 2)
            ->assertViewHas('kpis', fn ($items) => $items[1]['value'] === 1 && $items[2]['value'] === 0 && $items[3]['value'] === 2);
        $this->get('/attendance?tab=staff&from=2026-11-02&to=2026-11-02&teacher_id='.$teacher->id)->assertOk()
            ->assertDontSee('Tab Teacher')->assertViewHas('days', fn ($days) => $days->isEmpty());
        $this->get('/attendance?tab=teachers&from=2026-11-03&to=2026-11-02')->assertSessionHasErrors('to');
        $this->actingAs($teacher)->get('/attendance?tab=staff')->assertForbidden();
        $this->get('/attendance?tab=teachers')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'student', 'is_active' => true]))
            ->get('/attendance?tab=teachers')->assertForbidden();
    }
}
