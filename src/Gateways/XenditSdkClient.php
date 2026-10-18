<?php

declare(strict_types=1);

namespace Sunrice\Xendit\Gateways;

use RuntimeException;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\Invoice;
use Xendit\Invoice\InvoiceApi;

/** XenditClient through xendit/xendit-php's Invoice API. */
class XenditSdkClient implements XenditClient
{
    public function installed(): bool
    {
        return class_exists(InvoiceApi::class);
    }

    public function createInvoice(array $params, string $secretKey): array
    {
        return $this->toArray($this->api($secretKey)->createInvoice(new CreateInvoiceRequest($params)));
    }

    public function getInvoice(string $id, string $secretKey): array
    {
        return $this->toArray($this->api($secretKey)->getInvoiceById($id));
    }

    /** Its own configuration, so the key isn't left in the SDK's global default. */
    protected function api(string $secretKey): InvoiceApi
    {
        if (! $this->installed()) {
            throw new RuntimeException('The Xendit payment method needs its SDK: composer require xendit/xendit-php');
        }

        return new InvoiceApi(null, (new Configuration)->setApiKey($secretKey));
    }

    /** @return array<string, mixed> the fields the method reads, by their API names */
    protected function toArray(Invoice $invoice): array
    {
        return [
            'id' => (string) $invoice->getId(),
            'external_id' => (string) $invoice->getExternalId(),
            'status' => (string) $invoice->getStatus(),
            'amount' => (float) $invoice->getAmount(),
            'currency' => (string) $invoice->getCurrency(),
            'invoice_url' => (string) $invoice->getInvoiceUrl(),
        ];
    }
}
