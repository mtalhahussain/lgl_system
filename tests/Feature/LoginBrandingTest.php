<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_requested_name_without_settings_or_logo(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Land of German Language')
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
            ->assertSee(Storage::disk('public')->url('settings/first.png'));

        Setting::set('institute_name', 'Land of German Language <Academy>');
        Setting::set('institute_logo', 'settings/replacement.webp');
        $this->get('/login')->assertOk()
            ->assertSee('Land of German Language &lt;Academy&gt;', false)
            ->assertSee(Storage::disk('public')->url('settings/replacement.webp'))
            ->assertDontSee('settings/first.png');
    }
}
