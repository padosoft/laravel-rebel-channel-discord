<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord\Contracts;

/**
 * Thin seam over Discord's incoming-webhook HTTP API so the delivery channel stays
 * fully unit-testable offline. The real implementation POSTs JSON to a Discord
 * webhook URL; a fake ships for tests, and the opt-in live suite uses the real one
 * against an actual webhook.
 */
interface DiscordGateway
{
    /**
     * Post a message to a Discord channel via its incoming webhook.
     *
     * Implementations MUST throw on any transport/HTTP error so the caller can wrap
     * it into a clean `provider_error` failure. The webhook URL is a secret and must
     * never be logged.
     *
     * @param  string  $webhookUrl  The Discord incoming-webhook URL (the recipient).
     * @param  string  $message  The message content.
     * @param  string|null  $username  Optional override for the webhook display name.
     * @param  string|null  $avatarUrl  Optional override for the webhook avatar.
     */
    public function send(string $webhookUrl, string $message, ?string $username = null, ?string $avatarUrl = null): void;
}
