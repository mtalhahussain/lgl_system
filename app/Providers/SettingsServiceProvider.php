<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\View;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share settings with all views
        try {
            if (\Schema::hasTable('settings')) {
                $settings = Setting::public()->get()->pluck('value', 'key');
                View::share('globalSettings', $settings);
            }
        } catch (\Exception $e) {
            // Handle case where database might not be set up yet
        }
    }
}
