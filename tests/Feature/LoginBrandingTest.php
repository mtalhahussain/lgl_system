<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_requested_name_without_settings_or_logo(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Land of German Language')
            ->assertSee('INSTITUTE PORTAL')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('<img', false);

        Schema::drop('settings');
        $this->get('/login')->assertOk()->assertSee('Land of German Language');
    }

    public function test_login_reads_current_branding_and_escapes_settings(): void
    {
        Setting::create(['key' => 'institute_name', 'value' => 'German Language Learning Institute', 'label' => 'Institute Name']);
        Setting::create(['key' => 'institute_logo', 'value' => 'settings/first.png', 'label' => 'Institute Logo']);
        $this->get('/login')->assertOk()->assertSee('Land of German Language')
            ->assertSee(asset('storage/settings/first.png'))
            ->assertSee('class="login-uploaded-logo"', false)
            ->assertDontSee('INSTITUTE PORTAL')
            ->assertDontSee('<span>Land of German Language', false);

        Setting::set('institute_name', 'Land of German Language <Academy>');
        Setting::set('institute_logo', 'settings/replacement.webp');
        $this->get('/login')->assertOk()
            ->assertSee('Land of German Language &lt;Academy&gt;', false)
            ->assertSee(asset('storage/settings/replacement.webp'))
            ->assertDontSee('settings/first.png');
    }

    public function test_logo_uses_current_application_address_instead_of_disk_origin(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);
        Setting::create(['key' => 'institute_logo', 'value' => 'settings/logo.png', 'label' => 'Institute Logo']);

        $this->get('http://127.0.0.1:8000/login')->assertOk()
            ->assertSee('http://127.0.0.1:8000/storage/settings/logo.png', false)
            ->assertDontSee('http://localhost/storage/settings/logo.png', false);

        $this->get('http://127.0.0.1:8000/apply')->assertOk()
            ->assertSee('http://127.0.0.1:8000/storage/settings/logo.png', false);
    }

    public function test_public_header_uses_logo_only_or_name_fallback(): void
    {
        $fallback = $this->get('/apply')->assertOk()->getContent();
        preg_match('/<header\b[^>]*>(.*?)<\/header>/s', $fallback, $header);
        $this->assertStringContainsString('Land of German Language', strip_tags($header[1]));
        Setting::create(['key' => 'institute_logo', 'value' => 'settings/logo.png', 'label' => 'Logo']);
        $withLogo = $this->get('/apply')->assertOk()->getContent();
        preg_match('/<header\b[^>]*>(.*?)<\/header>/s', $withLogo, $header);
        $this->assertStringContainsString('class="apply-brand-logo"', $header[1]);
        $this->assertSame('', trim(strip_tags($header[1])));
    }
}
