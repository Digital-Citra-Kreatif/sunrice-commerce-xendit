<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Features\Actions\ToggleFeature;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Payments\Models\WebhookCall;
use Sunrice\Commerce\Payments\PaymentContext;
use Sunrice\Commerce\Payments\PaymentManager;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\Xendit\Gateways\XenditClient;

/*
 * Xendit invoices with a fake client (no network): the redirect, its
 * reuse, invoice callbacks verified by x-callback-token, and the status
 * check.
 */

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config(['honeypot.enabled' => false, 'xendit.secret_key' => 'xnd_production_test', 'xendit.callback_token' => 'cb-live']);
    $this->mug = checkoutStore();
    app(ToggleFeature::class)->handle('xendit', true);
    $stored = (array) app(StoreSettings::class)->get('payments.methods');
    app(StoreSettings::class)->saveSection('payments', ['methods' => [...$stored, 'xendit' => ['active' => true, 'sandbox' => false, 'title' => ['id' => 'Xendit']]]]);
    $this->xendit = new class implements XenditClient
    {
        public array $created = [];

        public array $invoices = [];

        public function installed(): bool
        {
            return true;
        }

        public function createInvoice(array $params, string $secretKey): array
        {
            $this->created[] = [$params, $secretKey];
            $id = 'inv-'.count($this->created);

            return $this->invoices[$id] = ['id' => $id, 'external_id' => $params['external_id'], 'status' => 'PENDING', 'amount' => $params['amount'],
                'invoice_url' => 'https://checkout.xendit.co/web/'.$id];
        }

        public function getInvoice(string $id, string $secretKey): array
        {
            return $this->invoices[$id];
        }
    };
    app()->instance(XenditClient::class, $this->xendit);
    app()->forgetScopedInstances();
});

function placeWithXendit(Variant $mug): Order
{
    $cart = cartWith([[$mug, 1]]);
    $input = checkoutInput(['payment_method' => 'xendit']);
    test()->post('/checkout', [...$input, 'action' => 'place', 'totals_hash' => seenHash($cart, $input)])
        ->assertRedirect('https://checkout.xendit.co/web/inv-1');

    return Order::query()->sole();
}

function xenditCallback(Order $order, array $data, string $token = 'cb-live'): TestResponse
{
    $data = ['id' => 'inv-1', 'external_id' => $order->payments()->sole()->reference, 'amount' => 60000, ...$data];

    return test()->postJson('/commerce/webhooks/xendit', $data, ['x-callback-token' => $token]);
}

it('needs the secret key and the callback token of the current mode', function () {
    $offered = fn () => array_keys(app(PaymentManager::class)->available(new PaymentContext(60000)));
    expect($offered())->toContain('xendit');

    config(['xendit.callback_token' => null]);
    app()->forgetScopedInstances();
    expect($offered())->not->toContain('xendit');

    // Sandbox mode reads the sandbox keys.
    config(['xendit.callback_token' => 'cb-live']);
    $stored = (array) app(StoreSettings::class)->get('payments.methods');
    app(StoreSettings::class)->saveSection('payments', ['methods' => [...$stored, 'xendit' => [...$stored['xendit'], 'sandbox' => true]]]);
    app()->forgetScopedInstances();
    expect($offered())->not->toContain('xendit');
});

it('creates an invoice once per payment and sends the customer to it', function () {
    $order = placeWithXendit($this->mug);
    $payment = $order->payments()->sole();
    [$params, $key] = $this->xendit->created[0];

    expect($key)->toBe('xnd_production_test')
        ->and($params)->toMatchArray(['external_id' => $payment->reference, 'amount' => 60000, 'currency' => 'IDR', 'payer_email' => 'rina@example.com'])
        ->and($params['success_redirect_url'])->toEndWith("/order/{$order->order_key}/payment/return")
        ->and($params['invoice_duration'])->toBeGreaterThan(86000)
        ->and($payment->meta)->toMatchArray(['invoice_id' => 'inv-1', 'sandbox' => false]);

    $this->post("/order/{$order->order_key}/pay", ['payment_method' => 'xendit'])->assertRedirect('https://checkout.xendit.co/web/inv-1');
    expect($this->xendit->created)->toHaveCount(1);
});

it('marks the payment paid on PAID, ignoring the repeat and SETTLED', function () {
    $order = placeWithXendit($this->mug);

    xenditCallback($order, ['status' => 'PAID', 'paid_amount' => 60000, 'paid_at' => '2026-10-18T03:15:00.000Z'])->assertOk();
    $order->refresh();
    expect([$order->status, $order->payment_status, $order->paid_total])->toBe(['processing', 'paid', 60000])
        ->and($order->payments()->sole()->paid_at->toIso8601String())->toBe('2026-10-18T03:15:00+00:00');

    xenditCallback($order, ['status' => 'PAID', 'paid_amount' => 60000])->assertOk()->assertJson(['duplicate' => true]);
    xenditCallback($order, ['status' => 'SETTLED', 'paid_amount' => 60000])->assertOk();
    expect($order->fresh()->paid_total)->toBe(60000)
        ->and(WebhookCall::query()->orderBy('id')->pluck('status')->all())->toBe(['processed', 'ignored', 'ignored']);
});

it('refuses a wrong or missing token and a different amount', function () {
    $order = placeWithXendit($this->mug);

    xenditCallback($order, ['status' => 'PAID'], 'nope')->assertStatus(400);
    test()->postJson('/commerce/webhooks/xendit', ['id' => 'inv-1', 'status' => 'PAID'])->assertStatus(400);
    xenditCallback($order, ['status' => 'PAID', 'paid_amount' => 10000])->assertStatus(400);

    expect($order->fresh()->payment_status)->toBe('unpaid')
        ->and(WebhookCall::query()->pluck('status')->unique()->all())->toBe(['failed']);
});

it('keeps PENDING pending and maps EXPIRED to a cancelled order', function () {
    $order = placeWithXendit($this->mug);

    xenditCallback($order, ['status' => 'PENDING'])->assertOk();
    expect([$order->fresh()->status, $order->fresh()->payment_status])->toBe(['pending', 'unpaid']);

    xenditCallback($order, ['status' => 'EXPIRED'])->assertOk();
    expect([$order->fresh()->status, $order->payments()->sole()->status])->toBe(['cancelled', 'expired']);
});

it('asks Xendit for the invoice when the customer returns', function () {
    $order = placeWithXendit($this->mug);

    $this->get("/order/{$order->order_key}/payment/return")->assertRedirect($order->url());
    expect($order->fresh()->payment_status)->toBe('unpaid');

    $this->xendit->invoices['inv-1']['status'] = 'PAID';
    $this->get("/order/{$order->order_key}/payment/return")->assertRedirect($order->url());
    expect($order->fresh()->payment_status)->toBe('paid');
});
