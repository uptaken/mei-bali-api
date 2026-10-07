<?php

namespace App\Providers;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppService::class, fn () => new WhatsAppService(
            baseUrl: config('services.fonnte.base_url'),
            token: config('services.fonnte.token'),
            enabled: config('services.fonnte.enabled'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
