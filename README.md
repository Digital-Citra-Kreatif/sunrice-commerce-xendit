# Sunrice Commerce Xendit

Xendit invoices for [Sunrice Commerce](https://github.com/Digital-Citra-Kreatif/sunrice-commerce):
the customer pays on Xendit's invoice page by virtual account, e-wallet, QRIS, card or retail
outlet, and comes back to the order. Currencies: IDR, PHP, THB, VND and MYR.

`Sunrice\CommerceXendit\Xendit`, key `xendit`, module `xendit` (Payments; needs `payments`, off by default).

## Contents

- [Install](#install)
- [Keys](#keys)
- [Webhook](#webhook)
- [Switching it on](#switching-it-on)
- [Sandbox (test mode)](#sandbox-test-mode)
- [How it works](#how-it-works)
- [Statuses](#statuses)
- [Testing your own code](#testing-your-own-code)

## Install

```bash
composer require sunrice/commerce-xendit
```

Requirements: PHP 8.4+, Laravel 13, Sunrice Commerce with `Payments\Methods\GatewayMethod`. The
provider is discovered automatically.

The package uses Xendit's SDK, `xendit/xendit-php` 7.x, which needs **Guzzle 7**
(`guzzlehttp/guzzle` ^7.3). Laravel 13 takes Guzzle 7 or 8, so Composer installs Guzzle 7; an
application that requires Guzzle 8 itself can't install this package until the SDK allows it.

To change the config file, `config/commerce-xendit.php` (key `commerce-xendit`; it only reads
`.env`):

```bash
php artisan vendor:publish --tag=commerce-xendit-config
```

## Keys

Xendit has a test mode and a live mode, each with its own keys. In the
[Xendit dashboard](https://dashboard.xendit.co), in test mode and again in live mode:

- Settings → Developers → API Keys: create a **secret key** with write access to Money-in
  (invoices);
- Settings → Developers → Webhooks: copy the **Webhook verification token** (the callback token).

Put them in `.env`:

```dotenv
XENDIT_SANDBOX_SECRET_KEY=xnd_development_…
XENDIT_SANDBOX_CALLBACK_TOKEN=…
XENDIT_SECRET_KEY=xnd_production_…
XENDIT_CALLBACK_TOKEN=…
```

| Key | Default | |
| --- | --- | --- |
| `commerce-xendit.secret_key` | `env('XENDIT_SECRET_KEY')` | Live secret key |
| `commerce-xendit.callback_token` | `env('XENDIT_CALLBACK_TOKEN')` | Live webhook verification token |
| `commerce-xendit.sandbox_secret_key` | `env('XENDIT_SANDBOX_SECRET_KEY')` | Test secret key |
| `commerce-xendit.sandbox_callback_token` | `env('XENDIT_SANDBOX_CALLBACK_TOKEN')` | Test webhook verification token |

Keys stay in `.env`, never in the store settings: the settings form says which are set, never
their values.

The dashboard menu paths above were written from Xendit's documentation at the time and have not
been checked against the live dashboard; Xendit renames menus from time to time. If a path doesn't
match, look for "API Keys" and "Webhooks" under the dashboard's developer settings.

## Webhook

In the same Webhooks page, in test mode and again in live mode, set the **Invoices** webhook URL
(invoice paid) to:

```
https://<your-store>/commerce/webhooks/xendit
```

This is commerce's own webhook route for the method key `xendit`; the method's settings show the
exact URL. The return URL needs no setting: each invoice sends the customer back to
`/order/{key}/payment/return`.

## Switching it on

1. Switch on **Xendit** in Settings → Store → Features (it needs Payment methods).
2. Switch on the method in Settings → Store → Payments.

The method is offered only when both keys of the current mode are set and the store currency is
one Xendit takes. Switching the module off hides the method and its webhook answers `404`; orders
already paid this way keep their payments.

## Sandbox (test mode)

Settings: **Sandbox (test mode)** (`sandbox`, on by default: uses the `XENDIT_SANDBOX_` keys; off:
the live ones) and **Instructions** per language, shown on the order page and in the email, plus
commerce's shared method fields (title, fee, deadline…).
Switch Sandbox off once the live keys and the live webhook are set.

## How it works

- `start()` creates the invoice once per payment: `external_id` `{number}-{payment id}-{random}`,
  the amount and currency, the customer's email, an `invoice_duration` up to the payment deadline,
  and the return URL for success and failure. Its URL and id are stored on the payment; paying
  again reuses them, and the order page and the "order received" email link to it while the
  payment is pending.
- Webhooks must carry the verification token in `x-callback-token`. A wrong or missing token, or a
  paid amount that isn't the payment's, answers `400` (logged `failed`).
- The event id is the invoice id with the status, so a repeated callback is a duplicate.
- On the customer's return, and once before the deadline, the method reads the invoice
  (`getInvoiceById`) rather than trusting the query string.

## Statuses

| Xendit `status` | Result |
| --- | --- |
| `PAID`, `SETTLED` | paid (the second changes nothing) |
| `PENDING` | pending |
| `EXPIRED` | expired (the order is cancelled) |

## Testing your own code

The SDK calls go through `Sunrice\CommerceXendit\Gateways\XenditClient` (bound to `XenditSdkClient` with
`bindIf`). Bind your own to test without the network:

```php
app()->instance(XenditClient::class, new FakeXenditClient);
```

This package's tests (`tests/Feature/XenditTest.php`) do exactly that.

## Development

```bash
composer test      # Pest
composer lint      # Pint (test mode)
composer analyse   # Larastan
```

## License

MIT.
