<?php

declare(strict_types=1);

namespace Sunrice\Xendit;

use Illuminate\Http\Request;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Payments\InvalidWebhook;
use Sunrice\Commerce\Payments\Methods\GatewayMethod;
use Sunrice\Commerce\Payments\Models\Payment;
use Sunrice\Commerce\Payments\PaymentResult;
use Sunrice\Commerce\Payments\WebhookEvent;
use Sunrice\Xendit\Gateways\XenditClient;

/**
 * Xendit Invoice (redirect): virtual accounts, e-wallets, QRIS, cards and
 * retail outlets on Xendit's invoice page. Invoice webhooks carry the
 * account's callback verification token in x-callback-token. Keys come
 * from XENDIT_SECRET_KEY and XENDIT_CALLBACK_TOKEN (or their XENDIT_SANDBOX_
 * versions in sandbox mode).
 */
class Xendit extends GatewayMethod
{
    public function __construct(protected XenditClient $client) {}

    public static function key(): string
    {
        return 'xendit';
    }

    public static function feature(): ?string
    {
        return 'xendit';
    }

    public function label(): string
    {
        return 'Xendit';
    }

    public function settingsSchema(): array
    {
        return $this->gatewayFields([
            'sandbox' => ['xendit.sandbox_secret_key' => 'XENDIT_SANDBOX_SECRET_KEY', 'xendit.sandbox_callback_token' => 'XENDIT_SANDBOX_CALLBACK_TOKEN'],
            'live' => ['xendit.secret_key' => 'XENDIT_SECRET_KEY', 'xendit.callback_token' => 'XENDIT_CALLBACK_TOKEN'],
        ]);
    }

    public function configured(): bool
    {
        return $this->secretKey() !== '' && $this->callbackToken() !== '' && $this->client->installed();
    }

    protected function currencies(): array
    {
        return ['IDR', 'PHP', 'THB', 'VND', 'MYR'];
    }

    protected function secretKey(): string
    {
        return (string) config($this->sandbox() ? 'xendit.sandbox_secret_key' : 'xendit.secret_key');
    }

    protected function callbackToken(): string
    {
        return (string) config($this->sandbox() ? 'xendit.sandbox_callback_token' : 'xendit.callback_token');
    }

    protected function createPayment(Order $order, Payment $payment, string $reference): array
    {
        $return = commerce_url('commerce.order.payment-return', ['key' => $order->order_key]);
        $params = array_filter([
            'external_id' => $reference,
            'amount' => $this->toGateway($payment->amount),
            'currency' => $this->currency(),
            'description' => __('commerce::payments.pay_title', ['number' => $order->displayNumber()], $order->locale),
            'payer_email' => $order->email,
            'success_redirect_url' => $return,
            'failure_redirect_url' => $return,
            'invoice_duration' => $payment->expires_at !== null && $payment->expires_at->isFuture() ? max(60, (int) now()->diffInSeconds($payment->expires_at)) : null,
        ], fn ($v) => $v !== null && $v !== '');
        $invoice = $this->client->createInvoice($params, $this->secretKey());

        return ['url' => (string) $invoice['invoice_url'], 'meta' => ['invoice_id' => (string) $invoice['id']]];
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $token = $this->callbackToken();
        if ($token === '' || ! hash_equals($token, (string) $request->header('x-callback-token'))) {
            throw new InvalidWebhook('Invalid Xendit callback token.');
        }
        $data = (array) $request->json()->all();
        if (! isset($data['id'], $data['external_id'], $data['status'])) {
            throw new InvalidWebhook('Not an invoice callback.');
        }
        $result = $this->result($data, $this->paymentFor((string) $data['external_id']));
        if ($result === null) {
            throw new InvalidWebhook('The paid amount does not match the payment.');
        }

        // PAID and SETTLED are separate events; the second changes nothing.
        return new WebhookEvent($data['id'].':'.$data['status'], (string) $data['external_id'], $result);
    }

    public function check(Payment $payment): PaymentResult
    {
        $id = $payment->meta['invoice_id'] ?? null;
        if (! is_string($id) || $id === '') {
            return PaymentResult::pending($payment->reference);
        }

        return $this->result($this->client->getInvoice($id, $this->secretKey()), $payment) ?? PaymentResult::pending($payment->reference);
    }

    /**
     * Maps an invoice callback or invoice (null: paid, but not the payment's amount).
     *
     * @param  array<string, mixed>  $data
     */
    protected function result(array $data, ?Payment $payment): ?PaymentResult
    {
        $reference = (string) ($data['external_id'] ?? $payment?->reference);
        $at = $this->time($data['paid_at'] ?? $data['updated'] ?? null);

        return match (strtoupper((string) $data['status'])) {
            'PAID', 'SETTLED' => $this->paidResult($payment, (float) ($data['paid_amount'] ?? $data['amount'] ?? 0), $reference, $at, $data),
            'EXPIRED' => new PaymentResult('expired', null, $reference, $at, $data),
            default => PaymentResult::pending($reference),
        };
    }
}
