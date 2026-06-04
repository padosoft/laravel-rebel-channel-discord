<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord\Testing;

use Padosoft\Rebel\Channel\Discord\Contracts\DiscordGateway;
use RuntimeException;

/**
 * Deterministic {@see DiscordGateway} for tests: records every posted message and can
 * simulate a webhook outage (so the delivery channel's graceful-failure path is
 * exercised offline, without ever hitting Discord).
 */
final class FakeDiscordGateway implements DiscordGateway
{
    /** @var list<array{webhookUrl: string, message: string, username: ?string, avatarUrl: ?string}> */
    public array $sent = [];

    public function __construct(
        private readonly bool $healthy = true,
    ) {}

    public function send(string $webhookUrl, string $message, ?string $username = null, ?string $avatarUrl = null): void
    {
        if (! $this->healthy) {
            throw new RuntimeException('discord webhook unavailable');
        }

        $this->sent[] = [
            'webhookUrl' => $webhookUrl,
            'message' => $message,
            'username' => $username,
            'avatarUrl' => $avatarUrl,
        ];
    }
}
