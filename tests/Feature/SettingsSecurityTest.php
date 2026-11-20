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

    public function test_logo_controls_are_opt_in_and_uploads_remain_independent(): void
    {
        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/settings')->assertOk()->assertDontSee('id="integrations-heading"', false)->assertDontSee('name="institute_logo"', false)
            ->assertDontSee('name="sidebar_logo"', false)->assertDontSee('name="document_logo"', false);
        $this->get('/settings?show=1')->assertOk()->assertDontSee('id="integrations-heading"', false)->assertDontSee('name="institute_logo"', false);
        $this->get('/settings?show=true')->assertOk()->assertSee('id="integrations-heading"', false)->assertSee('name="institute_logo"', false)
            ->assertSee('name="sidebar_logo"', false)->assertSee('name="document_logo"', false);

        $this->put('/settings?show=true', [
            'institute_logo' => UploadedFile::fake()->image('login.png'),
            'sidebar_logo' => UploadedFile::fake()->image('sidebar.png'),
            'document_logo' => UploadedFile::fake()->image('document.png'),
        ])->assertRedirect('/settings?show=true');
        $paths = [];
        foreach (['institute_logo', 'sidebar_logo', 'document_logo'] as $key) {
            $paths[$key] = Setting::get($key);
            Storage::disk('public')->assertExists($paths[$key]);
        }
        $this->assertCount(3, array_unique($paths));
        $login = view('auth.login')->render();
        $this->assertStringContainsString(asset('storage/'.$paths['institute_logo']), $login);
        $this->assertStringNotContainsString($paths['sidebar_logo'], $login);
        $this->assertStringNotContainsString($paths['document_logo'], $login);
        $this->get('/settings')->assertOk()->assertSee(asset('storage/'.$paths['sidebar_logo']))
            ->assertDontSee('name="document_logo"', false);
        $this->assertNotNull(\App\Support\PdfImage::instituteLogo());
        $this->put('/settings', ['institute_name' => 'Updated Institute'])->assertRedirect('/settings');
        foreach ($paths as $key => $path) {
            $this->assertSame($path, Setting::get($key));
        }
        $this->put('/settings?show=true', ['institute_logo' => UploadedFile::fake()->image('replacement.png')])
            ->assertRedirect('/settings?show=true');
        $this->assertNotSame($paths['institute_logo'], Setting::get('institute_logo'));
        $this->assertSame($paths['sidebar_logo'], Setting::get('sidebar_logo'));
        $this->assertSame($paths['document_logo'], Setting::get('document_logo'));
        Storage::disk('public')->assertExists($paths['institute_logo']);
        foreach (['sidebar_logo', 'document_logo'] as $key) {
            $this->put('/settings?show=true', [$key => UploadedFile::fake()->create('bad.php', 1, 'application/x-php')])
                ->assertSessionHasErrors($key);
        }
        $this->actingAs(User::factory()->create(['role' => 'teacher', 'is_active' => true]));
        $this->get('/settings?show=true')->assertForbidden();
        $this->put('/settings?show=true', [])->assertForbidden();
    }

    public function test_portal_brand_has_separate_expanded_and_collapsed_logos_with_fallbacks(): void
    {
        $this->seed(SettingsSeeder::class);
        $fallback = view('partials.portal-brand')->render();
        $this->assertStringContainsString(institute_name(), $fallback);
        $this->assertStringContainsString('brand-mark', $fallback);
        $this->assertStringNotContainsString('<img', $fallback);

        Setting::set('institute_logo', 'settings/login.png');
        Setting::create(['key' => 'sidebar_logo', 'value' => 'settings/sidebar.png', 'label' => 'Sidebar logo']);
        $html = view('partials.portal-brand')->render();
        [$expanded, $collapsed] = explode('<div class="portal-brand-collapsed">', $html);
        $this->assertStringContainsString('storage/settings/login.png', $expanded);
        $this->assertStringNotContainsString('storage/settings/sidebar.png', $expanded);
        $this->assertStringContainsString('storage/settings/sidebar.png', $collapsed);
        $this->assertStringNotContainsString('storage/settings/login.png', $collapsed);
    }
}
