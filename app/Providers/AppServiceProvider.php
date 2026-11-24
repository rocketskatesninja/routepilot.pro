<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use App\Support\BrandColor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;
use Laravel\Cashier\Cashier;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Web push to phones, signed with this site's VAPID keys. Notifications
        // older than a day aren't worth delivering.
        $this->app->bind(WebPush::class, fn (): WebPush => new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 86400]));
    }

    public function boot(): void
    {
        // The Tenant is the Cashier billable (the company owns its subscription).
        // Login auditing lives in App\Listeners\RecordLoginActivity, registered
        // via Laravel's app/Listeners event auto-discovery (no explicit binding).
        Cashier::useCustomerModel(Tenant::class);

        // Cashier skips signature checks when its webhook secret is empty, which
        // would let anyone post subscription events. With no secret configured,
        // give it one nobody knows so every call is refused (fail closed, like
        // StripeWebhookController). The real one is STRIPE_SUBSCRIPTION_WEBHOOK_SECRET.
        if (blank(config('cashier.webhook.secret'))) {
            config(['cashier.webhook.secret' => 'unset-'.Str::random(40)]);
        }

        // Inject the tenant's brand color into the root view server-side so the
        // SSR / first paint is on-brand before JS runs (applyBrand can't touch
        // the DOM during SSR). Null when no tenant/brand → the default applies.
        View::composer('app', function (ViewInstance $view): void {
            $tenant = app()->has('tenant') ? app('tenant') : null;
            $brand = $tenant instanceof Tenant ? $tenant->brand_color : null;
            $view->with('brandChannels', is_string($brand) ? BrandColor::toHslChannels($brand) : null);
        });
    }
}
