<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('inquiry-submit', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinutes(10, 5)->by('inquiry:10m:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perHour(20)->by('inquiry:hour:'.$request->ip()),
        ]);
    }
}
