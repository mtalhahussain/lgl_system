<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_admin_cannot_be_deactivated_or_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->patchJson('/accounts/'.$admin->id, ['role' => 'student', 'is_active' => 1])->assertUnprocessable();
        $this->patchJson('/accounts/'.$admin->id, ['role' => 'admin', 'is_active' => 0])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_can_assign_supported_roles_and_audit_excludes_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($admin)->get('/accounts')->assertOk();
        $this->patch('/accounts/'.$user->id, ['role' => 'teacher', 'is_active' => 1])->assertRedirect('/accounts');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'teacher']);
        $this->assertSame(1, AuditLog::where('action', 'user.updated')->where('subject_id', $user->id)->count());
        $audit = AuditLog::where('action', 'user.updated')->where('subject_id', $user->id)->sole();
        $this->assertSame('student', $audit->changes['before']['role']);
        $this->assertSame('teacher', $audit->changes['after']['role']);
        $this->assertStringNotContainsString('password', json_encode($audit->changes));
        $this->patchJson('/accounts/'.$user->id, ['role' => 'accountant', 'is_active' => 1])->assertUnprocessable();
    }

    public function test_users_page_saves_changes_in_the_background_without_a_save_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Zara']);
        $this->actingAs($admin)->get('/accounts')->assertOk()->assertSee('Users')->assertDontSee('Account Access')
            ->assertSee('data-autosave', false)->assertDontSee('Choose a supported role')
            ->assertSee('>Students</option>', false)->assertSee('>Admins</option>', false)->assertSee('>Student</option>', false)
            ->assertDontSee('>Admin</option>', false)->assertSee('name="role" value="admin" form="account_'.$admin->id.'"', false);

        $this->patchJson('/accounts/'.$user->id, ['role' => 'student', 'is_active' => 0])->assertOk()
            ->assertJson(['message' => 'Zara: Student, inactive.', 'is_active' => false, 'redirect' => null]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);

        $this->patchJson('/accounts/'.$admin->id, ['role' => 'admin', 'is_active' => 0])->assertOk()
            ->assertJson(['redirect' => route('dashboard')]);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_self_service_cannot_change_roles_or_active_status_and_password_requires_current_password(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'password' => Hash::make('existing-password')]);
        $this->actingAs($student)->get('/accounts')->assertForbidden();
        $this->get('/profile')->assertOk();
        $this->put('/profile', ['name' => 'Changed', 'role' => 'admin', 'is_active' => 0])->assertRedirect('/profile');
        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'Changed', 'role' => 'student', 'is_active' => true]);
        $this->putJson('/profile', ['name' => 'Changed', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertUnprocessable();
        $this->put('/profile', ['name' => 'Changed', 'current_password' => 'existing-password',
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertRedirect('/profile');
        $this->assertTrue(Hash::check('replacement-password', $student->fresh()->password));
    }
}
