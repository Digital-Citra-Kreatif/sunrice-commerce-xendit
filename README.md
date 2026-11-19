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
- [The scheduler](#the-scheduler)
- [Deposits](#deposits)
- [Refunds](#refunds)
- [Troubleshooting](#troubleshooting)
- [Testing your own code](#testing-your-own-code)

## Install

```bash
composer require sunrice/commerce-xendit
```

Requirements: PHP 8.4+, Laravel 13, Sunrice Commerce 0.1, 0.2 or 0.3 (`sunrice/commerce`, which Composer
installs with it). The provider is discovered automatically.

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

## The scheduler

Run Laravel's scheduler, which runs commerce's `commerce:tick` every minute:

```cron
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

It is the safety net for webhooks. When an unpaid payment's deadline passes, `commerce:tick` first
asks Xendit for its status, so a payment whose notification was lost is still recorded as paid. Only
a payment that is still unpaid then expires, which cancels the order. Without the scheduler, a lost
notification leaves the order unpaid and unpaid orders never expire.

## Deposits

When an extension asks for a deposit (commerce's
[Deposits](https://github.com/Digital-Citra-Kreatif/sunrice-commerce/blob/main/docs/payments.md#deposits),
Sunrice Commerce 0.3), the deposit and the balance are two separate payments, each with its own
Xendit invoice for its own amount. The deposit is charged at checkout, the balance when the customer
pays it from the order page. A notification for the balance with the deposit's amount, or the
reverse, is refused like any other wrong amount. An expired balance leaves the order open and
Partially paid.

## Refunds

Xendit doesn't report invoice refunds to the store, so a refund made in the Xendit dashboard doesn't
reach the order. Record it yourself: **Refund** on the order page, with the amount (and the items to
restock). The method doesn't send refunds to Xendit.

## Troubleshooting

Commerce stores every call to `/commerce/webhooks/xendit` in the `commerce_webhook_calls` table
(kept 30 days, `commerce.webhooks.keep_days`). The admin has no page for it, so query it:

```sql
SELECT received_at, status, error, event_id, payment_id
FROM commerce_webhook_calls WHERE method = 'xendit' ORDER BY id DESC LIMIT 20;
```

| `status` | Meaning |
| --- | --- |
| `processed` | Verified and recorded: the payment (and order) changed. |
| `ignored` | Verified, but nothing to change: a duplicate, a status the payment already has, or an unknown reference (`error` says which). |
| `failed` | Refused with `400` (`error` says why), or failed while recording with `500` (Xendit retries). |

If a payment doesn't update:

- **No rows at all**: Xendit isn't calling the store. Check that the webhook URL is set in the
  dashboard for the mode you're paying in (test and live are separate), that it is reachable from
  the internet, and that the Payments and Xendit modules are on (a module that's off answers `404`).
- **`failed`, token**: the callback token (`XENDIT_SANDBOX_CALLBACK_TOKEN` or
  `XENDIT_CALLBACK_TOKEN`) in `.env` belongs to the other mode, or the method's **Sandbox** switch
  doesn't match the dashboard you're paying in. After editing `.env`, clear the config cache
  (`php artisan config:clear`).
- **`failed`, amount**: Xendit reported a different amount from the payment's, and the payment stays
  unpaid. Check the transaction in the dashboard, then mark the order paid by hand if the money
  arrived.
- **`ignored`, unknown reference**: the notification is for a transaction this store didn't create
  (another site on the same Xendit account, or a dashboard test).
- **No change after the customer paid**: wait for the notification or the scheduler's check (see
  [The scheduler](#the-scheduler)).

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
