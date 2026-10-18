<?php

declare(strict_types=1);

namespace Sunrice\Xendit;

use Illuminate\Support\ServiceProvider;
use Sunrice\Commerce\Commerce;
use Sunrice\Commerce\Features\Feature;
use Sunrice\Xendit\Gateways\XenditClient;
use Sunrice\Xendit\Gateways\XenditSdkClient;

/**
 * The `xendit` module (Payments; needs `payments`, off by default) and the
 * Xendit method, key `xendit`, so its webhook is commerce's
 * /commerce/webhooks/xendit.
 */
class XenditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xendit.php', 'xendit');
        $this->app->bindIf(XenditClient::class, XenditSdkClient::class);
    }

    public function boot(Commerce $commerce): void
    {
        $this->publishes([__DIR__.'/../config/xendit.php' => config_path('xendit.php')], 'xendit-config');

        $commerce->registerFeature(new Feature('xendit', 'payments', 'Xendit', 'Virtual accounts, e-wallets, QRIS and cards on a Xendit invoice. Needs XENDIT_SECRET_KEY and XENDIT_CALLBACK_TOKEN.', ['payments'], ['shop', 'quote'], default: false));
        $commerce->registerPaymentMethod(Xendit::class);
    }
}
