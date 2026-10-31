<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_validate_contact_and_uploads_and_ignore_unknown_fields(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/settings')->assertOk();
        $this->putJson('/settings', ['institute_email' => 'not-an-email'])->assertUnprocessable();
        Storage::fake('public');
        $this->put('/settings', ['institute_logo' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])->assertSessionHasErrors('institute_logo');
        $this->put('/settings', ['institute_email' => 'office@lgl.com', 'private_api_token' => 'unwanted'])->assertRedirect('/settings');
        $this->assertSame('office@lgl.com', Setting::get('institute_email'));
        $this->assertDatabaseMissing('settings', ['key' => 'private_api_token']);
    }
}
