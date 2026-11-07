<?php

namespace App\Providers;

use App\Adapters\Payment\PaymentGatewayInterface;
use App\Adapters\Payment\XenditAdapter;
use App\Adapters\Shipping\ManualShippingAdapter;
use App\Adapters\Shipping\ShippingProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ShippingProviderInterface::class, ManualShippingAdapter::class);
        $this->app->bind(PaymentGatewayInterface::class, XenditAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
