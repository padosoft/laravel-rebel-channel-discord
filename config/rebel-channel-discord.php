<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Discord incoming-webhook URL
    |--------------------------------------------------------------------------
    | The destination Discord channel's incoming-webhook URL. Create it in Discord:
    | open the target channel → Edit Channel → Integrations → Webhooks → New Webhook,
    | then "Copy Webhook URL". It looks like:
    |
    |     https://discord.com/api/webhooks/<id>/<token>
    |
    | This is a SECRET (anyone with the URL can post to the channel) — keep it in
    | .env, never commit it, and never log it. The delivery channel registers only
    | when this value is present.
    */
    'webhook_url' => env('DISCORD_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Display overrides (optional)
    |--------------------------------------------------------------------------
    | Override the webhook's default display name and avatar per message. Leave null
    | to use whatever you configured on the webhook in Discord.
    */
    'username' => env('DISCORD_WEBHOOK_USERNAME'),
    'avatar_url' => env('DISCORD_WEBHOOK_AVATAR_URL'),

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    | Whether to auto-register the Discord delivery channel into the Rebel Channels
    | container on boot (only takes effect when webhook_url is set). Set to false to
    | keep the package installed but dormant.
    */
    'register_provider' => env('REBEL_DISCORD_REGISTER', true),

];
