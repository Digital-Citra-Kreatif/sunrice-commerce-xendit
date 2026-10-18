<?php

declare(strict_types=1);

namespace Sunrice\CommerceXendit\Gateways;

/**
 * The calls the Xendit method makes (bound to XenditSdkClient, which uses
 * xendit/xendit-php's Invoice API). Swap it in the container to test
 * without the network.
 */
interface XenditClient
{
    /** Whether the SDK is installed. */
    public function installed(): bool;

    /**
     * InvoiceApi::createInvoice(): the invoice's fields (id, external_id, status, amount, invoice_url…).
     *
     * @param  array<string, mixed>  $params  CreateInvoiceRequest fields
     * @return array<string, mixed>
     */
    public function createInvoice(array $params, string $secretKey): array;

    /**
     * InvoiceApi::getInvoiceById().
     *
     * @return array<string, mixed>
     */
    public function getInvoice(string $id, string $secretKey): array;
}
