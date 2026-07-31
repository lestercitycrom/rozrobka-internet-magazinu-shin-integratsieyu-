<?php

namespace App\Providers;

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\HttpNdiInquiryClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NdiInquiryClient::class, HttpNdiInquiryClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by('admin-login|'.$email.'|'.$request->ip());
        });
    }
}
