<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Sunrice\Commerce\Commerce;
use Sunrice\Commerce\Features\Actions\ToggleFeature;
use Sunrice\Commerce\Features\FeatureRegistry;
use Sunrice\Commerce\Payments\PaymentRegistry;
use Sunrice\CommerceXendit\Gateways\XenditClient;
use Sunrice\CommerceXendit\Gateways\XenditSdkClient;
use Sunrice\CommerceXendit\Xendit;
use Sunrice\CommerceXendit\XenditServiceProvider;

/*
 * What the package adds to a store: the `xendit` module, the method and
 * its config.
 */

it('registers the xendit module, off by default and needing payments', function () {
    $feature = app(FeatureRegistry::class)->get('xendit');

    expect($feature)->not->toBeNull()
        ->and([$feature->group, $feature->requires, $feature->default])->toBe(['payments', ['payments'], false])
        ->and(app(Commerce::class)->active('xendit'))->toBeFalse();

    app(ToggleFeature::class)->handle('xendit', true);
    expect(app(Commerce::class)->active('xendit'))->toBeTrue();
});

it('registers the method under the key of its webhook', function () {
    expect(app(PaymentRegistry::class)->classes())->toHaveKey('xendit')
        ->and(app(PaymentRegistry::class)->classes()['xendit'])->toBe(Xendit::class)
        ->and(route('commerce.webhooks', ['method' => 'xendit']))->toEndWith('/commerce/webhooks/xendit');
});

it('reads its keys from commerce-xendit.* and calls the SDK unless swapped', function () {
    expect(array_keys(config('commerce-xendit')))->toBe(['secret_key', 'callback_token', 'sandbox_secret_key', 'sandbox_callback_token'])
        ->and(app(XenditClient::class))->toBeInstanceOf(XenditSdkClient::class)
        ->and(app(XenditClient::class)->installed())->toBeTrue();
});

it('publishes config/commerce-xendit.php with the commerce-xendit-config tag', function () {
    expect(array_values(ServiceProvider::pathsToPublish(XenditServiceProvider::class, 'commerce-xendit-config')))->toBe([config_path('commerce-xendit.php')]);
});
