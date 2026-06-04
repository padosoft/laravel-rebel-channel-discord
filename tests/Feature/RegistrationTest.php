<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Padosoft\Rebel\Channel\Discord\Delivery\DiscordDeliveryChannel;
use Padosoft\Rebel\Channel\Discord\RebelDiscordServiceProvider;
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Channels\Routing\DeliveryChannelRegistry;

it('registers the Discord delivery channel into the shared registry when a webhook URL is configured', function (): void {
    // The base TestCase configures a webhook_url, so the channel registers under 'discord'.
    $registry = app(DeliveryChannelRegistry::class);

    expect($registry->has('discord'))->toBeTrue();

    $channel = $registry->get('discord');
    expect($channel)->toBeInstanceOf(DiscordDeliveryChannel::class)
        ->and($channel->key())->toBe('discord')
        ->and($channel->supports(Channel::Discord))->toBeTrue()
        ->and($registry->supporting(Channel::Discord))->toContain($channel);
});

it('does not register the channel when no webhook URL is configured', function (): void {
    // A fresh, isolated app whose Discord config has no webhook URL: packageBooted()
    // must bail out before binding the delivery channel.
    $app = freshApp(['webhook_url' => null, 'register_provider' => true]);

    (new RebelDiscordServiceProvider($app))->packageBooted();

    expect($app->bound(DiscordDeliveryChannel::class))->toBeFalse();
});

it('does not register the channel when register_provider is disabled', function (): void {
    $app = freshApp([
        'webhook_url' => 'https://discord.com/api/webhooks/123/test-token',
        'register_provider' => false,
    ]);

    (new RebelDiscordServiceProvider($app))->packageBooted();

    expect($app->bound(DiscordDeliveryChannel::class))->toBeFalse();
});

/**
 * @param  array<string, mixed>  $discordConfig
 */
function freshApp(array $discordConfig): Application
{
    $app = new Application(sys_get_temp_dir());
    $config = new Repository(['rebel-channel-discord' => $discordConfig]);

    // The provider resolves the config via the Repository contract; bind both the
    // 'config' key and the contract alias so make(Repository::class) succeeds.
    $app->instance('config', $config);
    $app->instance(Illuminate\Contracts\Config\Repository::class, $config);

    return $app;
}
