<?php

declare(strict_types=1);

namespace Sunrice\CommerceXendit;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sunrice\Commerce\Facades\Commerce;
use Sunrice\Commerce\Features\Feature;
use Sunrice\CommerceXendit\Gateways\XenditClient;
use Sunrice\CommerceXendit\Gateways\XenditSdkClient;

/**
 * The `xendit` module (Payments; needs `payments`, off by default) and the
 * Xendit method, key `xendit`, so its webhook is commerce's
 * /commerce/webhooks/xendit. Keys come from config/commerce-xendit.php
 * (.env).
 */
class XenditServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('commerce-xendit')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->bindIf(XenditClient::class, XenditSdkClient::class);
    }

    public function packageBooted(): void
    {
        Commerce::registerFeature(new Feature('xendit', 'payments', 'Xendit', 'Virtual accounts, e-wallets, QRIS and cards on a Xendit invoice. Needs XENDIT_SECRET_KEY and XENDIT_CALLBACK_TOKEN.', ['payments'], ['shop', 'quote'], default: false));
        Commerce::registerPaymentMethod(Xendit::class);
    }
}
