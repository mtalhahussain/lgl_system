<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_is_throttled_after_five_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'missing@lgl.com', 'password' => 'incorrect'])->assertUnprocessable();
        }
        $response = $this->postJson('/login', ['email' => 'MISSING@lgl.com', 'password' => 'incorrect'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertStringStartsWith('Too many login attempts.', $response->json('errors.email.0'));
    }

    public function test_successful_login_clears_failed_attempts_and_redirects_by_role(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'password' => Hash::make('valid-password')]);
            $this->post('/login', ['email' => $user->email, 'password' => 'invalid'])->assertSessionHasErrors('email');
            $this->post('/login', ['email' => $user->email, 'password' => 'valid-password'])->assertRedirect('/'.$role.'/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect('/');
        }
    }

    public function test_inactive_user_cannot_log_in_or_continue_an_existing_session(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => false, 'password' => Hash::make('valid-password')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'valid-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->update(['is_active' => true]);
        $this->actingAs($user);
        User::whereKey($user->id)->update(['is_active' => false]);
        $this->get('/dashboard')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_retired_role_cannot_log_in_or_keep_an_existing_session(): void
    {
        // Restore the historical enum on this disposable DB to represent an upgrade.
        $migration = require database_path('migrations/2026_10_31_000001_restrict_user_roles.php');
        $migration->down();
        $user = User::factory()->create(['role' => 'accountant', 'is_active' => true, 'password' => Hash::make('valid-password')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'valid-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/accountant/dashboard')->assertNotFound();
    }

    public function test_three_role_migration_refuses_to_change_a_legacy_account(): void
    {
        $migration = require database_path('migrations/2026_10_31_000001_restrict_user_roles.php');
        $migration->down();
        $user = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        try {
            $migration->up();
            $this->fail('Legacy role must block migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unsupported user roles remain.', $exception->getMessage());
        }
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'accountant', 'is_active' => true]);
    }
}
