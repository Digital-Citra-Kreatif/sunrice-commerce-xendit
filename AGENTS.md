# Working on Sunrice Xendit

A payment gateway package for [Sunrice Commerce](https://github.com/Digital-Citra-Kreatif/sunrice-commerce).
Commerce's `AGENTS.md`, `SPEC.md` and `docs/extending.md` (Payments) set the rules this package
follows; `docs/extension-package.md` there describes its shape.

## Rules

- Extend `Sunrice\Commerce\Payments\Methods\GatewayMethod` and use only commerce's public API.
  Missing something? Propose the change in commerce.
- Report results (`PaymentResult`); never change orders or payments yourself.
- Keys stay in `.env` and `config/xendit.php`, never in store settings.
- No network in tests: swap `Gateways\XenditClient` for a fake.
- Every change ships Pest tests (Orchestra Testbench). Before opening a PR run `composer test`,
  `composer lint` and `composer analyse`.
- Update `README.md` and `CHANGELOG.md` when behaviour or setup changes.
- Do not commit `vendor/`, `composer.lock` or `.env`.

## Conventions

- PHP 8.4+, Laravel 13. Namespace `Sunrice\Xendit\` → `src/`; tests `Sunrice\Xendit\Tests\` →
  `tests/`. Every PHP file starts `declare(strict_types=1);`.
- Config `config/xendit.php` (key `xendit`); module and method key `xendit`.

## Commands

```bash
composer test      # Pest
composer lint      # Pint (test mode)
composer format    # Pint (fix)
composer analyse   # Larastan
```
