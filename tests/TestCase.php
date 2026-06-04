<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Padosoft\Rebel\Channel\Discord\RebelDiscordServiceProvider;
use Padosoft\Rebel\Channels\RebelChannelsServiceProvider;
use Padosoft\Rebel\Core\RebelCoreServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RebelCoreServiceProvider::class,
            RebelChannelsServiceProvider::class,
            RebelDiscordServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('rebel-core.peppers', [1 => 'test-pepper']);
        $app['config']->set('rebel-core.pepper_current', 1);
        $app['config']->set('cache.default', 'array');

        // A configured webhook URL so the delivery channel registers. The gateway is
        // faked per-test (the offline suite never hits Discord).
        $app['config']->set('rebel-channel-discord.webhook_url', 'https://discord.com/api/webhooks/123/test-token');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-core/database/migrations');
    }
}
