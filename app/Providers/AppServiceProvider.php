<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use App\Support\BrandColor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The platform already owns `stripe/webhook` (Connect payments), so don't
        // let Cashier register a colliding route — subscription webhooks get wired
        // explicitly in a later billing slice.
        Cashier::ignoreRoutes();
    }

    public function boot(): void
    {
        // The Tenant is the Cashier billable (the company owns its subscription).
        Cashier::useCustomerModel(Tenant::class);

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
