# Changelog

## Unreleased

## 0.1.1 (2026-11-07)

Works with Sunrice Commerce 0.2 (`sunrice/commerce` `^0.1 || ^0.2`) as well as 0.1. Nothing else changes.

## 0.1.0 (2026-11-06)

The first release, moved out of commerce into its own package. Needs Sunrice Commerce 0.1
(`sunrice/commerce`, on `sunrice/admin` and `sunrice/cms` 0.6), PHP 8.4,
Laravel 13 and `xendit/xendit-php` 7.x (Guzzle 7). Its settings page is Settings → Store →
Payments → Xendit, at `/admin/commerce/settings/payments/xendit`.

Renamed before the first release to match the other commerce packages (`sunrice/commerce-biteship`):
the package is `sunrice/commerce-xendit` (was `sunrice/xendit`), the namespace
`Sunrice\CommerceXendit\` (was `Sunrice\Xendit\`), the config `config/commerce-xendit.php` with key
`commerce-xendit` and publish tag `commerce-xendit-config` (were `xendit` and `xendit-config`), and
the repository
[sunrice-commerce-xendit](https://github.com/Digital-Citra-Kreatif/sunrice-commerce-xendit). The
module and method key stay `xendit`, so the webhook URL and the `.env` keys are unchanged.

- The `xendit` module (Payments; needs `payments`, off by default) and the Xendit method, key
  `xendit`: a Xendit invoice with a redirect (virtual accounts, e-wallets, QRIS, cards, retail
  outlets) in IDR, PHP, THB, VND or MYR.
- The invoice is created once per payment, with the payment's amount, the customer's email, the
  return URL and a duration up to the payment deadline; paying again reuses it.
- Invoice webhooks at commerce's `/commerce/webhooks/xendit`, verified by `x-callback-token`:
  `PAID` and `SETTLED` paid (the amount checked against the payment), `EXPIRED` expired, `PENDING`
  pending. The return and the status check read the invoice.
- Live and test keys in `.env` (`config/commerce-xendit.php`, publish tag
  `commerce-xendit-config`); the method's Sandbox switch picks the test ones.
- SDK calls behind `Gateways\XenditClient`, swappable in the container.
