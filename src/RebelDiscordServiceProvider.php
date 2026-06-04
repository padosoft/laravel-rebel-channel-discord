<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Padosoft\Rebel\Channel\Discord\Contracts\DiscordGateway;
use Padosoft\Rebel\Channel\Discord\Delivery\DiscordDeliveryChannel;
use Padosoft\Rebel\Channel\Discord\Gateway\HttpDiscordGateway;
use Padosoft\Rebel\Channels\Routing\DeliveryChannelRegistry;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Padosoft\Rebel\Core\Contracts\KeyedHasher;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Registers the Discord delivery channel into the Rebel Channels container (when a
 * webhook URL is configured) and binds the Discord gateway.
 *
 * Config is read lazily: the package installs cleanly with no Discord config, and the
 * delivery channel simply does not register until you set DISCORD_WEBHOOK_URL. The
 * channel registers itself into the shared {@see DeliveryChannelRegistry} (provided by
 * laravel-rebel-channels) keyed by `discord`, so it coexists with every other delivery
 * channel and the admin panel can enumerate them all.
 */
final class RebelDiscordServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-rebel-channel-discord')
            ->hasConfigFile('rebel-channel-discord');
    }

    public function packageBooted(): void
    {
        $config = $this->app->make(Repository::class);

        // Opt-out switch: when register_provider is false, nothing Discord-backed is wired.
        if ($config->get('rebel-channel-discord.register_provider', true) !== true) {
            return;
        }

        // No webhook URL → no delivery channel is registered and no gateway is constructed.
        $webhookUrl = $this->stringConfig($config, 'webhook_url');
        if ($webhookUrl === '') {
            return;
        }

        // Bind the real gateway only when not already bound (so a test can bind a fake first).
        if (! $this->app->bound(DiscordGateway::class)) {
            $this->app->singleton(DiscordGateway::class, function (): HttpDiscordGateway {
                return new HttpDiscordGateway($this->app->make(HttpFactory::class));
            });
        }

        $this->app->singleton(DiscordDeliveryChannel::class, function () use ($config, $webhookUrl): DiscordDeliveryChannel {
            return new DiscordDeliveryChannel(
                $this->app->make(DiscordGateway::class),
                $this->app->make(AuditLogger::class),
                $this->app->make(KeyedHasher::class),
                $webhookUrl,
                $this->nullableConfig($config, 'username'),
                $this->nullableConfig($config, 'avatar_url'),
            );
        });

        // Register into the shared delivery registry (keyed 'discord') so it coexists
        // with every other delivery channel instead of fighting over one contract binding.
        if (class_exists(DeliveryChannelRegistry::class) && $this->app->bound(DeliveryChannelRegistry::class)) {
            $this->app->make(DeliveryChannelRegistry::class)
                ->register($this->app->make(DiscordDeliveryChannel::class));
        }
    }

    private function stringConfig(Repository $config, string $key): string
    {
        $value = $config->get("rebel-channel-discord.{$key}");

        return is_string($value) ? $value : '';
    }

    private function nullableConfig(Repository $config, string $key): ?string
    {
        $value = $this->stringConfig($config, $key);

        return $value === '' ? null : $value;
    }
}
