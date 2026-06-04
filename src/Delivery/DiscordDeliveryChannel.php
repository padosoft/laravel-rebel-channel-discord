<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord\Delivery;

use Padosoft\Rebel\Channel\Discord\Contracts\DiscordGateway;
use Padosoft\Rebel\Channels\Contracts\MessageDeliveryChannel;
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Channels\Results\DeliveryResult;
use Padosoft\Rebel\Core\Audit\AuditEvent;
use Padosoft\Rebel\Core\Context\SecurityContext;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Padosoft\Rebel\Core\Contracts\KeyedHasher;
use Padosoft\Rebel\Core\Identifiers\PhoneIdentifier;

/**
 * Rebel Channels {@see MessageDeliveryChannel} that delivers a message to a Discord
 * channel via an incoming webhook — primarily for SOC/security alerts (anomaly cases,
 * lockouts, high-risk events), and optionally OTP/notifications.
 *
 * The {@see PhoneIdentifier} recipient's normalized value is treated as the Discord
 * channel/webhook id and is only ever stored as a keyed HMAC in the audit trail; the
 * actual webhook URL to POST to comes from configuration (a secret that is never
 * logged). Every send records a `channel.delivery.sent` / `channel.delivery.failed`
 * audit event so the panel's Channel Performance shows Discord traffic.
 *
 * It never throws out: any transport/SDK error becomes a clean `provider_error`
 * failure so the caller can fall back or surface a tidy error.
 */
final class DiscordDeliveryChannel implements MessageDeliveryChannel
{
    public function __construct(
        private readonly DiscordGateway $gateway,
        private readonly AuditLogger $audit,
        private readonly KeyedHasher $hasher,
        private readonly string $webhookUrl,
        private readonly ?string $username = null,
        private readonly ?string $avatarUrl = null,
    ) {}

    public function key(): string
    {
        return 'discord';
    }

    public function supports(Channel $channel): bool
    {
        return $channel === Channel::Discord;
    }

    public function send(PhoneIdentifier $phone, string $message, Channel $channel, SecurityContext $context): DeliveryResult
    {
        // The recipient's normalized value identifies the Discord channel; we only ever
        // persist it as a keyed HMAC, never in clear.
        $hash = $this->hasher->hash($phone->normalized());

        try {
            $this->gateway->send($this->webhookUrl, $message, $this->username, $this->avatarUrl);
        } catch (\Throwable) {
            $this->audit->record(new AuditEvent(
                type: 'channel.delivery.failed',
                identifierHmac: $hash->hash,
                keyVersion: $hash->keyVersion,
                channel: 'discord',
                provider: 'discord',
                metadata: [
                    'message_status' => 'failed',
                    'error_code' => 'provider_error',
                ],
            ));

            return DeliveryResult::fail($channel, 'provider_error', 'discord');
        }

        $this->audit->record(new AuditEvent(
            type: 'channel.delivery.sent',
            identifierHmac: $hash->hash,
            keyVersion: $hash->keyVersion,
            channel: 'discord',
            provider: 'discord',
            metadata: [
                'message_status' => 'sent',
                'error_code' => null,
            ],
        ));

        return DeliveryResult::sent($channel, 'discord');
    }
}
