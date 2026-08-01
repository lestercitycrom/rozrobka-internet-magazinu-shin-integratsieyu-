<?php

namespace App\Providers;

use App\Modules\NdiApi\Contracts\NdiDespatchClient;
use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Contracts\NdiOrderStatusClient;
use App\Modules\NdiApi\HttpNdiDespatchClient;
use App\Modules\NdiApi\HttpNdiInquiryClient;
use App\Modules\NdiApi\HttpNdiOrderClient;
use App\Modules\NdiApi\HttpNdiOrderStatusClient;
use App\Modules\Outbox\Contracts\NotionOrderDestination;
use App\Modules\Outbox\Contracts\TelegramOrderDestination;
use App\Modules\Outbox\DisabledNotionOrderDestination;
use App\Modules\Outbox\DisabledTelegramOrderDestination;
use App\Modules\Outbox\HttpNotionOrderDestination;
use App\Modules\Outbox\HttpTelegramOrderDestination;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\DisabledPaymentGateway;
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
        $this->app->bind(NdiOrderClient::class, HttpNdiOrderClient::class);
        $this->app->bind(NdiOrderStatusClient::class, HttpNdiOrderStatusClient::class);
        $this->app->bind(NdiDespatchClient::class, HttpNdiDespatchClient::class);
        $this->app->bind(PaymentGateway::class, DisabledPaymentGateway::class);
        $this->app->bind(NotionOrderDestination::class, function ($app): NotionOrderDestination {
            if (config('outbox.notion_enabled') && config('outbox.notion.token') && config('outbox.notion.database_id')) {
                return $app->make(HttpNotionOrderDestination::class);
            }

            return $app->make(DisabledNotionOrderDestination::class);
        });
        $this->app->bind(TelegramOrderDestination::class, function ($app): TelegramOrderDestination {
            if (config('outbox.telegram_enabled') && config('outbox.telegram.bot_token') && config('outbox.telegram.chat_id')) {
                return $app->make(HttpTelegramOrderDestination::class);
            }

            return $app->make(DisabledTelegramOrderDestination::class);
        });
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
