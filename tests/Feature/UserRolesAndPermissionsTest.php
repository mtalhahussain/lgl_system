<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_creates_users_of_every_role_from_one_form(): void
    {
        $this->actingAs($this->admin)->get('/accounts/create')->assertOk()->assertSee('Role')->assertSee('Optional for staff');

        $this->post('/accounts', ['role' => 'admin', 'name' => 'Second Admin', 'email' => 'admin2@example.test',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'salary_type' => 'monthly', 'designation' => 'Ignored'])
            ->assertSessionHasNoErrors()->assertRedirect('/accounts');
        $admin = User::where('email', 'admin2@example.test')->sole();
        $this->assertSame(['admin', true, null, null], [$admin->role, $admin->is_active, $admin->designation, $admin->employee_id]);
        $this->assertTrue(Hash::check('secret123', $admin->password));

        $this->post('/accounts', ['role' => 'teacher', 'name' => 'Per Student', 'email' => 't@example.test', 'phone' => '03001234567',
            'salary_type' => 'per_student', 'per_student_amount' => '1500', 'monthly_salary' => '9000'])
            ->assertSessionHasNoErrors()->assertRedirect('/accounts?role=teacher');
        $teacher = User::where('email', 't@example.test')->sole();
        $this->assertSame(['teacher', 'per_student', '1500.00', 'TCH'.str_pad((string) $teacher->id, 4, '0', STR_PAD_LEFT)],
            [$teacher->role, $teacher->salary_type, (string) $teacher->per_student_amount, $teacher->employee_id]);
        $this->assertFalse(Hash::check('password', $teacher->password));

        $this->post('/accounts', ['role' => 'student', 'name' => 'New Student', 'email' => 's@example.test', 'monthly_salary' => '5000'])
            ->assertSessionHasNoErrors();
        $student = User::where('email', 's@example.test')->sole();
        $this->get('/accounts')->assertSee(route('students.show', $student), false)->assertSee(route('teachers.show', $teacher), false)
            ->assertDontSee('href="'.url('/students/'.$this->admin->id).'"', false);
        $this->get(route('staff.show', $student))->assertNotFound();
        $this->assertSame(['student', null], [$student->role, $student->monthly_salary]);

        // Staff without an email never signs in (unusable placeholder email).
        $this->post('/accounts', ['role' => 'staff', 'name' => 'Guard', 'phone' => '03009999999', 'designation' => 'Security guard',
            'monthly_salary' => '25000'])
            ->assertSessionHasNoErrors()->assertRedirect('/accounts?role=staff');
        $staff = User::where('role', 'staff')->sole();
        $this->assertStringEndsWith('@staff.invalid', $staff->email);
        $this->assertSame(['Security guard', 'monthly', '25000.00'], [$staff->designation, $staff->salary_type, (string) $staff->monthly_salary]);

        // Staff with an email and password can sign in.
        $this->post('/accounts', ['role' => 'staff', 'name' => 'Front Desk', 'phone' => '03008888888', 'designation' => 'Receptionist',
            'monthly_salary' => '30000', 'email' => 'desk@example.test', 'password' => 'desk12345', 'password_confirmation' => 'desk12345'])
            ->assertSessionHasNoErrors();
        $desk = User::where('email', 'desk@example.test')->sole();
        $this->assertSame(['staff', 'monthly'], [$desk->role, $desk->salary_type]);
        $this->assertTrue(Hash::check('desk12345', $desk->password));
    }

    public function test_create_validates_role_specific_fields_and_is_admin_only(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/accounts', ['role' => 'owner', 'name' => 'X', 'email' => 'x@example.test'])->assertJsonValidationErrors('role');
        $this->postJson('/accounts', ['role' => 'teacher', 'name' => 'X', 'email' => 'x@example.test', 'salary_type' => 'per_student'])
            ->assertJsonValidationErrors(['phone', 'per_student_amount']);
        $this->postJson('/accounts', ['role' => 'staff', 'name' => 'X'])->assertJsonValidationErrors(['designation', 'monthly_salary', 'phone']);
        $this->postJson('/accounts', ['role' => 'student', 'name' => 'X', 'email' => $this->admin->email])->assertJsonValidationErrors('email');
        $this->assertSame(1, User::count());

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->get('/accounts/create')->assertForbidden();
        $this->post('/accounts', ['role' => 'admin', 'name' => 'Hack', 'email' => 'h@example.test'])->assertForbidden();
        $this->put('/accounts/permissions', [])->assertForbidden();
    }

    public function test_seeder_creates_default_permissions_from_current_menus_without_overwriting(): void
    {
        RolePermission::create(['role' => 'teacher', 'module' => 'exams', 'allowed' => false]);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $expected = count(config('modules.teacher')) + count(config('modules.student'));
        $this->assertSame($expected, RolePermission::count());
        $this->assertFalse(RolePermission::where(['role' => 'teacher', 'module' => 'exams'])->sole()->allowed);
        $this->assertSame($expected - 1, RolePermission::where('allowed', true)->count());
    }

    public function test_hidden_modules_leave_the_menu_and_are_blocked_server_side(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($teacher)->get('/exams')->assertOk();
        $this->get('/teacher/dashboard')->assertOk()->assertSee(route('exams.index'), false);

        $this->actingAs($this->admin)->get('/accounts')->assertOk()->assertSee(route('accounts.permissions.edit'), false)->assertDontSee('My Earnings');
        $this->get('/accounts/permissions')->assertOk()->assertSee('Role permissions')->assertSee('My Earnings')->assertSee('Back to Users');
        $allTeacher = array_fill_keys(array_keys(config('modules.teacher')), ['all' => '1']);
        $allStudent = array_fill_keys(array_keys(config('modules.student')), ['all' => '1']);
        unset($allTeacher['exams'], $allStudent['fees']);
        $this->put('/accounts/permissions', ['permissions' => ['teacher' => $allTeacher, 'student' => $allStudent]])
            ->assertRedirect(route('accounts.permissions.edit'));
        $this->assertSame(1, AuditLog::where('action', 'role_permissions.updated')->count());
        $this->assertFalse(RolePermission::where(['role' => 'teacher', 'module' => 'exams'])->sole()->allowed);

        $this->actingAs($teacher)->get('/exams')->assertForbidden();
        $this->get('/teacher/dashboard')->assertOk()->assertDontSee('<i class="fas fa-clipboard-list me-2" aria-hidden="true"></i>Exams', false);
        $this->get('/class-sessions')->assertOk();
        $this->actingAs($student)->get('/fees/student/'.$student->id)->assertForbidden();
        $this->get('/student/attendance')->assertOk();
        // Admin is never restricted.
        $this->actingAs($this->admin)->get('/exams')->assertOk();

        // Saving again with everything ticked restores access.
        $this->put('/accounts/permissions', ['permissions' => ['teacher' => $allTeacher + ['exams' => ['view' => '1']], 'student' => $allStudent + ['fees' => ['view' => '1']]]]);
        $this->actingAs($teacher)->get('/exams')->assertOk();
        $this->actingAs($student)->get('/fees/student/'.$student->id)->assertOk();
    }
}
