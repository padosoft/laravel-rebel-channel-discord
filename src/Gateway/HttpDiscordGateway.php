<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord\Gateway;

use Illuminate\Http\Client\Factory as HttpFactory;
use Padosoft\Rebel\Channel\Discord\Contracts\DiscordGateway;
use RuntimeException;

/**
 * Real {@see DiscordGateway} backed by Laravel's HTTP client. POSTs the message as
 * JSON ({@code {"content": "..."}}, with optional username/avatar overrides) to a
 * Discord incoming-webhook URL.
 *
 * Discord answers a successful webhook post with 204 No Content (or 200 when
 * {@code ?wait=true}); anything else is treated as a failure. The exception message
 * deliberately never includes the webhook URL (a secret).
 */
final class HttpDiscordGateway implements DiscordGateway
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly float $timeout = 5.0,
    ) {}

    public function send(string $webhookUrl, string $message, ?string $username = null, ?string $avatarUrl = null): void
    {
        $payload = ['content' => $message];

        if ($username !== null && $username !== '') {
            $payload['username'] = $username;
        }

        if ($avatarUrl !== null && $avatarUrl !== '') {
            $payload['avatar_url'] = $avatarUrl;
        }

        $response = $this->http
            ->timeout($this->timeout)
            ->acceptJson()
            ->post($webhookUrl, $payload);

        if (! $response->successful()) {
            // Never echo the webhook URL (a secret) — only the status code, which is
            // safe and enough to diagnose. The caller turns this into 'provider_error'.
            throw new RuntimeException("Discord webhook responded with HTTP {$response->status()}.");
        }
    }
}
