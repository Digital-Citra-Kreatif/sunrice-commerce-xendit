<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Xendit keys
    |--------------------------------------------------------------------------
    |
    | A secret key (Settings → Developers → API Keys) and the webhook
    | verification token (Settings → Developers → Webhooks), for live mode
    | and for test mode. Secrets stay in .env, never in the store settings.
    | The method's "Sandbox" switch in Settings → Store → Payments picks the
    | sandbox keys.
    |
    */

    'secret_key' => env('XENDIT_SECRET_KEY'),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    'sandbox_secret_key' => env('XENDIT_SANDBOX_SECRET_KEY'),
    'sandbox_callback_token' => env('XENDIT_SANDBOX_CALLBACK_TOKEN'),

];
