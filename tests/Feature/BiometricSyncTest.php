<?php

namespace Tests\Feature;

use App\Biometric\MachineUserSync;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_resync_user_requeues_one_person_to_all_active_machines(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Re Sync']);
        $this->device();
        $this->device();
        BiometricDeviceUser::where('user_id', $student->id)->update(['status' => 'synced', 'attempts' => 2, 'last_error' => 'old']);

        app(MachineUserSync::class)->resyncUser($student->fresh());

        $rows = BiometricDeviceUser::where('user_id', $student->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(['upsert', 'pending', 0, null], [$row->action, $row->status, $row->attempts, $row->last_error]);
            $this->assertGreaterThan(1, $row->version); // reset + queue both bump it
        }

        // Ineligible user: rows flip to delete and are re-pended; no number work needed.
        $student->update(['is_active' => false]);
        BiometricDeviceUser::where('user_id', $student->id)->update(['status' => 'synced']);
        app(MachineUserSync::class)->resyncUser($student->fresh());
        foreach (BiometricDeviceUser::where('user_id', $student->id)->get() as $row) {
            $this->assertSame(['delete', 'pending'], [$row->action, $row->status]);
        }
    }

    private function device(): BiometricDevice
    {
        $this->actingAs($this->admin)->post('/biometric/devices', ['name' => 'Main gate', 'timezone' => 'Asia/Karachi'])->assertRedirect('/biometric');

        return BiometricDevice::latest('id')->firstOrFail()->makeVisible(['bridge_token']);
    }

    private function rows(BiometricDevice $device): array
    {
        return BiometricDeviceUser::where('biometric_device_id', $device->id)->orderBy('employee_no')->get()
            ->map(fn ($row) => [$row->employee_no, $row->action, $row->status])->all();
    }

    public function test_new_students_and_teachers_get_automatic_numbers_and_are_queued(): void
    {
        $this->assertNull($this->admin->fresh()->device_employee_no);
        $early = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'name' => 'Early Teacher']);
        $this->assertNull($early->fresh()->device_employee_no);
        User::factory()->create(['role' => 'student', 'is_active' => false]);
        $device = $this->device();
        $this->assertSame([[(string) $early->id, 'upsert', 'pending']], $this->rows($device));

        $this->post('/students', ['name' => 'Sara Khan', 'email' => 'sara@example.test', 'phone' => '0300', 'address' => 'Karachi',
            'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasNoErrors();
        $sara = User::where('email', 'sara@example.test')->sole();
        $this->assertSame((string) $sara->id, $sara->device_employee_no);
        $row = BiometricDeviceUser::where('employee_no', $sara->device_employee_no)->sole();
        $this->assertSame(['upsert', 'pending', 'Sara Khan', $sara->id], [$row->action, $row->status, $row->name, $row->user_id]);

        // A number already used by someone else falls back to the highest number + 1.
        $taken = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $taken->forceFill(['device_employee_no' => (string) ($taken->id + 1)])->save();
        $next = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->assertSame((string) ($taken->id + 2), $next->fresh()->device_employee_no);

        // A posted device_employee_no is ignored; the teacher still gets an automatic number.
        $this->post('/teachers', ['name' => 'Manual Number', 'email' => 'manual@example.test', 'phone' => '0300', 'address' => 'Karachi',
            'password' => 'password123', 'password_confirmation' => 'password123', 'device_employee_no' => '5005'])->assertSessionHasNoErrors();
        $manual = User::where('email', 'manual@example.test')->sole();
        // The id is already taken as a number by the earlier fixture, so it falls back to the highest number + 1.
        $this->assertSame((string) ($manual->id + 1), $manual->device_employee_no);
        $this->assertSame(0, BiometricDeviceUser::where('employee_no', '5005')->count());
    }

    public function test_profile_changes_queue_updates_moves_and_removals(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Old Name']);
        $device = $this->device();
        $code = $student->fresh()->device_employee_no;
        BiometricDeviceUser::query()->update(['status' => 'synced']);

        $student->update(['email' => 'other@example.test']);
        $this->assertSame([[$code, 'upsert', 'synced']], $this->rows($device));
        $student->update(['name' => 'New Name']);
        $this->assertSame([[$code, 'upsert', 'pending']], $this->rows($device));
        $this->assertSame(2, BiometricDeviceUser::sole()->version);

        $student->forceFill(['device_employee_no' => '7777'])->save();
        $this->assertEqualsCanonicalizing([[$code, 'delete', 'pending'], ['7777', 'upsert', 'pending']], $this->rows($device));
        $student->update(['is_active' => false]);
        $this->assertSame('delete', BiometricDeviceUser::where('employee_no', '7777')->sole()->action);
        $student->update(['is_active' => true]);
        $this->assertSame('upsert', BiometricDeviceUser::where('employee_no', '7777')->sole()->action);

        // Blank on the edit form keeps the existing number.
        $this->put('/students/'.$student->id, ['name' => 'New Name', 'email' => 'other@example.test', 'phone' => '0300',
            'address' => 'Karachi', 'device_employee_no' => ''])->assertSessionHasNoErrors();
        $this->assertSame('7777', $student->fresh()->device_employee_no);
        $student->update(['role' => 'admin']);
        $this->assertSame('delete', BiometricDeviceUser::where('employee_no', '7777')->sole()->action);
    }

    public function test_bridge_receives_pending_users_and_reports_results(): void
    {
        $one = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Student One']);
        $two = User::factory()->create(['role' => 'teacher', 'is_active' => true, 'name' => 'Teacher Two']);
        $device = $this->device();
        $base = '/biometric/bridge/'.$device->bridge_token;
        $this->getJson('/biometric/bridge/'.str_repeat('x', 48).'/pending')->assertNotFound();
        $this->getJson('/biometric/hook/'.$device->bridge_token)->assertNotFound();

        $users = $this->getJson($base.'/pending')->assertOk()->assertJsonPath('device', 'Main gate')->json('users');
        $this->assertSame(['Student One', 'Teacher Two'], array_column($users, 'name'));
        $this->assertNotNull($device->fresh()->bridge_seen_at);
        $one->update(['name' => 'Student One Renamed']);   // changed while the bridge was working

        $this->postJson($base.'/results', ['results' => [
            ['id' => $users[0]['id'], 'version' => $users[0]['version'], 'ok' => true],
            ['id' => $users[1]['id'], 'version' => $users[1]['version'], 'ok' => false, 'error' => '{"statusCode":4,"subStatusCode":"badParameters"}'],
        ]])->assertOk()->assertJsonPath('applied', 1);
        $first = BiometricDeviceUser::find($users[0]['id']);
        $second = BiometricDeviceUser::find($users[1]['id']);
        $this->assertSame(['pending', 'Student One Renamed'], [$first->status, $first->name]);
        $this->assertSame(['failed', 1], [$second->status, $second->attempts]);
        $this->assertStringContainsString('badParameters', $second->last_error);

        // Failed rows wait 10 minutes before the bridge gets them again.
        $this->assertSame([$first->id], array_column($this->getJson($base.'/pending')->json('users'), 'id'));
        $this->travel(11)->minutes();
        $this->assertCount(2, $this->getJson($base.'/pending')->json('users'));
        $this->postJson($base.'/results', ['results' => [['id' => $first->id, 'version' => $first->version, 'ok' => true]]])->assertJsonPath('applied', 1);
        $this->assertSame('synced', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->synced_at);

        $device->update(['is_active' => false]);
        $this->getJson($base.'/pending')->assertNotFound();
    }

    public function test_admin_page_downloads_resync_and_new_bridge_key(): void
    {
        User::factory()->create(['role' => 'student', 'is_active' => true]);
        $device = $this->device();
        BiometricDeviceUser::query()->update(['status' => 'failed', 'attempts' => 3, 'last_error' => 'Device said no']);
        $this->get('/biometric')->assertOk()->assertSee('User sync')->assertSee('Failed users')->assertSee('Device said no')
            ->assertSee('hikvision-bridge.ps1');
        $this->get('/biometric/bridge-script')->assertOk()->assertDownload('hikvision-bridge.ps1');
        $config = $this->get('/biometric/devices/'.$device->id.'/bridge-config')->assertOk()->streamedContent();
        $this->assertSame($device->bridge_token, json_decode($config, true)['BridgeToken']);

        $this->post('/biometric/devices/'.$device->id.'/resync')->assertRedirect('/biometric');
        $this->assertSame(['pending', 0, null], [BiometricDeviceUser::sole()->status, BiometricDeviceUser::sole()->attempts, BiometricDeviceUser::sole()->last_error]);

        $old = $device->bridge_token;
        $this->post('/biometric/devices/'.$device->id.'/bridge-token')->assertRedirect('/biometric');
        $this->getJson('/biometric/bridge/'.$old.'/pending')->assertNotFound();

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->get('/biometric/bridge-script')->assertForbidden();
        $this->get('/biometric/devices/'.$device->id.'/bridge-config')->assertForbidden();
        $this->post('/biometric/devices/'.$device->id.'/resync')->assertForbidden();
    }

    public function test_admin_can_resync_one_user_from_their_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $device = $this->device();
        BiometricDeviceUser::query()->update(['status' => 'failed', 'attempts' => 3, 'last_error' => 'nope']);

        $this->actingAs($this->admin)->post('/people/'.$teacher->id.'/resync-machine')->assertRedirect();
        $this->assertSame(['pending', 0, null], [BiometricDeviceUser::sole()->status, BiometricDeviceUser::sole()->attempts, BiometricDeviceUser::sole()->last_error]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric_device_user.resync', 'subject_type' => 'user', 'subject_id' => $teacher->id]);

        $other = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($other)->post('/people/'.$teacher->id.'/resync-machine')->assertForbidden();
    }

    public function test_person_form_shows_number_and_status_not_an_input(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $device = $this->device();
        $code = $teacher->fresh()->device_employee_no;

        $html = $this->actingAs($this->admin)->get('/teachers/'.$teacher->id.'/edit')->assertOk();
        $html->assertSee('Machine Employee No.')->assertSee('Not confirmed')->assertSee('Sync now')->assertSee('Waiting');
        $html->assertDontSee('name="device_employee_no"', false);
    }

    public function test_student_without_a_machine_has_no_number_and_manual_sync_reports_configuration_error(): void
    {
        $this->actingAs($this->admin)->post('/students', ['name' => 'Offline Student', 'email' => 'offline@example.test',
            'phone' => '0300', 'address' => 'Karachi'])->assertSessionHasNoErrors();
        $student = User::where('email', 'offline@example.test')->sole();
        $this->assertNull($student->device_employee_no);
        $this->get('/students')->assertOk()->assertSee('Not confirmed')
            ->assertSee('Sync Offline Student to attendance machines')->assertSee(route('biometric.resync-user', $student), false);
        $this->post('/people/'.$student->id.'/resync-machine')->assertSessionHasErrors('machine');
        $this->assertNull($student->fresh()->device_employee_no);
        $this->assertDatabaseCount('biometric_device_users', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'biometric_device_user.resync', 'subject_id' => $student->id]);
    }

    public function test_machine_number_is_shown_only_after_matching_bridge_confirmation_and_is_reused(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $device = $this->device();
        $student->refresh();
        $row = BiometricDeviceUser::where('user_id', $student->id)->sole();
        $code = $student->device_employee_no;
        $this->assertNull($student->confirmed_machine_number);
        $this->get('/students')->assertOk()->assertSee('Waiting for machine')->assertDontSee('ID: '.$code.'<', false);
        $this->postJson('/biometric/bridge/'.$device->bridge_token.'/results', ['results' => [
            ['id' => $row->id, 'version' => $row->version, 'ok' => false, 'error' => 'Device offline'],
        ]])->assertOk()->assertJsonPath('applied', 1);
        $this->assertNull($student->fresh()->confirmed_machine_number);
        $this->get('/students')->assertOk()->assertSee('Sync failed')->assertDontSee('ID: '.$code.'<', false);
        $this->postJson('/biometric/bridge/'.$device->bridge_token.'/results', ['results' => [
            ['id' => $row->id, 'version' => $row->version, 'ok' => true],
        ]])->assertOk()->assertJsonPath('applied', 1);
        $this->assertSame($code, $student->fresh()->confirmed_machine_number);
        $this->get('/students')->assertOk()->assertSee('ID: '.$code)->assertSee('Synced');
        $this->post('/people/'.$student->id.'/resync-machine')->assertSessionHasNoErrors();
        $this->assertSame($code, $student->fresh()->device_employee_no);
        $this->assertSame($code, $row->fresh()->employee_no);
        $this->assertSame($code, $student->fresh()->confirmed_machine_number);
        $student->update(['is_active' => false]);
        $delete = $row->fresh();
        $this->postJson('/biometric/bridge/'.$device->bridge_token.'/results', ['results' => [
            ['id' => $delete->id, 'version' => $delete->version, 'ok' => true],
        ]])->assertOk();
        $student->update(['is_active' => true]);
        $this->assertNull($student->fresh()->confirmed_machine_number);
    }

    public function test_inactive_and_admin_accounts_cannot_be_sent_from_the_manual_endpoint(): void
    {
        $this->device();
        $student = User::factory()->create(['role' => 'student', 'is_active' => false]);
        $this->post('/people/'.$student->id.'/resync-machine')->assertUnprocessable();
        $this->post('/people/'.$this->admin->id.'/resync-machine')->assertUnprocessable();
    }
}
