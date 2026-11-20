<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => 'staff', 'is_active' => true, 'email' => 'desk@example.test',
            'password' => 'desk12345', 'designation' => 'Receptionist', 'salary_type' => 'monthly']);
    }

    private function grant(array $staff): void
    {
        $this->actingAs($this->admin)->put('/accounts/permissions', ['permissions' => ['staff' => $staff]])
            ->assertRedirect(route('accounts.permissions.edit'));
    }

    public function test_staff_signs_in_and_sees_nothing_until_admin_grants_it(): void
    {
        $this->post('/login', ['email' => 'desk@example.test', 'password' => 'desk12345'])->assertRedirect('/staff/dashboard');
        $this->get('/dashboard')->assertRedirect(route('staff.dashboard'));
        $this->get('/staff/dashboard')->assertOk()->assertSee('No sections have been given to Staff yet')
            ->assertDontSee('href="'.url('/students').'"', false);
        foreach (['/students', '/students/create', '/enrollments', '/batches', '/fees', '/admin/dashboard'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_granted_actions_open_only_their_routes_and_menu_items(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->grant(['students' => ['view' => '1', 'create' => '1'], 'batches' => ['view' => '1']]);
        $this->assertSame(3, RolePermission::where(['role' => 'staff', 'allowed' => true])->count());

        $this->actingAs($this->staff)->get('/staff/dashboard')->assertOk()->assertSee('Students')->assertSee('View · Create');
        $this->get('/students')->assertOk();
        $this->get('/students/create')->assertOk();
        $this->get('/students/'.$student->id)->assertOk()->assertDontSee(route('students.edit', $student), false);
        $this->get('/students/'.$student->id.'/edit')->assertForbidden();
        $this->delete('/students/'.$student->id)->assertForbidden();
        $this->get('/batches')->assertOk()->assertDontSee(route('batches.create'), false);
        $this->get('/batches/create')->assertForbidden();
        $this->get('/leads')->assertForbidden();

        // Revoking takes effect on the next request.
        $this->grant(['batches' => ['view' => '1']]);
        $this->actingAs($this->staff)->get('/students')->assertForbidden();
        $this->get('/batches')->assertOk();
    }

    public function test_permission_page_saves_each_tick_without_a_save_button(): void
    {
        $this->actingAs($this->admin)->get('/accounts/permissions')->assertOk()
            ->assertSee('data-autosave', false)->assertSee('Each switch saves immediately')
            ->assertSee('name="permissions[staff][students][all]"', false)->assertDontSee('name="permissions[staff][students][view]"', false)
            ->assertSee('<noscript><div class="form-actions px-0 pb-0"><button class="btn btn-primary">Save permissions</button></div></noscript>', false);

        // A tick (the autosave sends the whole form as JSON request) grants; the next change without it removes.
        $this->putJson('/accounts/permissions', ['permissions' => ['staff' => ['students' => ['view' => '1']]]])
            ->assertOk()->assertJson(['message' => 'Permission saved.']);
        $this->assertTrue(RolePermission::allows($this->staff, 'students'));
        $this->putJson('/accounts/permissions', ['permissions' => ['staff' => []]])->assertOk();
        RolePermission::flushCache();
        $this->assertFalse(RolePermission::allows($this->staff, 'students'));

        // The module switch grants every action of the module and shows as on.
        $this->putJson('/accounts/permissions', ['permissions' => ['staff' => ['students' => ['all' => '1']]]])->assertOk();
        RolePermission::flushCache();
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $this->assertTrue(RolePermission::allows($this->staff, 'students', $action));
        }
        $this->assertFalse(RolePermission::allows($this->staff, 'leads'));
        $this->get('/accounts/permissions')->assertSee('name="permissions[staff][students][all]" value="1" checked', false);
        $this->actingAs($this->staff)->get('/students/create')->assertOk();
    }

    public function test_admin_only_areas_can_never_be_granted_to_staff(): void
    {
        $this->grant(['payroll' => ['view' => '1'], 'accounts' => ['view' => '1', 'edit' => '1'], 'settings' => ['view' => '1'],
            'students' => ['view' => '1', 'publish' => '1']]);
        $this->assertSame(['students.view'], RolePermission::where(['role' => 'staff', 'allowed' => true])
            ->get()->map(fn ($row) => $row->module.'.'.$row->action)->all());

        $this->actingAs($this->staff);
        foreach (['/payroll', '/accounts', '/accounts/create', '/accounts/permissions', '/settings', '/whatsapp', '/biometric', '/staff/create', '/admin/dashboard'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put('/accounts/permissions', ['permissions' => ['staff' => ['leads' => ['view' => '1']]]])->assertForbidden();
        $this->patch('/accounts/'.$this->staff->id, ['role' => 'admin', 'is_active' => '1'])->assertForbidden();
        $this->assertSame('staff', $this->staff->fresh()->role);
    }

    public function test_staff_cannot_open_admin_or_staff_records_through_people_screens(): void
    {
        $this->grant(['students' => ['view' => '1', 'edit' => '1'], 'teachers' => ['view' => '1', 'edit' => '1']]);
        $this->actingAs($this->staff);
        $this->get('/students/'.$this->admin->id.'/edit')->assertNotFound();
        $this->get('/teachers/'.$this->admin->id.'/edit')->assertNotFound();
        $this->assertFalse($this->staff->can('update', $this->admin));
        $this->assertFalse($this->staff->can('view', $this->staff));
    }

    public function test_staff_without_an_email_cannot_sign_in_and_seeder_grants_staff_nothing(): void
    {
        $guard = User::factory()->create(['role' => 'staff', 'is_active' => true, 'email' => 'staff-x@staff.invalid']);
        $this->post('/login', ['email' => $guard->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->seed(RolePermissionSeeder::class);
        $this->assertSame(0, RolePermission::where('role', 'staff')->count());
        $this->assertTrue(RolePermission::where(['role' => 'teacher', 'module' => 'exams', 'action' => 'view'])->sole()->allowed);
    }
}
