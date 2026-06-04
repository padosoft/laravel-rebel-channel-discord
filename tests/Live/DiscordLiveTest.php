<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Padosoft\Rebel\Channel\Discord\Gateway\HttpDiscordGateway;

/**
 * LIVE test: it POSTs a REAL message to a real Discord channel via its webhook.
 *
 * It runs ONLY when you explicitly opt in with REBEL_DISCORD_LIVE=1 AND a real
 * DISCORD_WEBHOOK_URL is present — otherwise it self-skips, so the offline suite and
 * external PRs never trigger a post. In CI, provide the values as secrets and set
 * REBEL_DISCORD_LIVE=1.
 */
function liveEnv(string $key): string
{
    $value = getenv($key);

    return is_string($value) ? $value : '';
}

beforeEach(function (): void {
    if (liveEnv('REBEL_DISCORD_LIVE') !== '1') {
        test()->markTestSkipped('Live Discord tests are opt-in (set REBEL_DISCORD_LIVE=1).');
    }

    if (liveEnv('DISCORD_WEBHOOK_URL') === '') {
        test()->markTestSkipped('Live Discord webhook URL absent (DISCORD_WEBHOOK_URL).');
    }
});

it('posts a real message to the Discord webhook', function (): void {
    $gateway = new HttpDiscordGateway(new HttpFactory);

    $gateway->send(
        liveEnv('DISCORD_WEBHOOK_URL'),
        '[Rebel] Live test message — '.date('c'),
        'Rebel SOC (live test)',
    );

    // No exception thrown == Discord accepted the webhook post (204/200).
    expect(true)->toBeTrue();
})->group('live');
