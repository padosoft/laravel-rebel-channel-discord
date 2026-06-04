<?php

declare(strict_types=1);

use Padosoft\Rebel\Channel\Discord\Delivery\DiscordDeliveryChannel;
use Padosoft\Rebel\Channel\Discord\Testing\FakeDiscordGateway;
use Padosoft\Rebel\Channel\Discord\Tests\Support\RecordingAuditLogger;
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Core\Context\SecurityContext;
use Padosoft\Rebel\Core\Contracts\KeyedHasher;
use Padosoft\Rebel\Core\Identifiers\PhoneIdentifier;

function makeChannel(FakeDiscordGateway $gateway, RecordingAuditLogger $audit): DiscordDeliveryChannel
{
    return new DiscordDeliveryChannel(
        $gateway,
        $audit,
        app(KeyedHasher::class),
        'https://discord.com/api/webhooks/123/test-token',
        'Rebel SOC',
        null,
    );
}

it('supports the Discord channel only', function (): void {
    $channel = makeChannel(new FakeDiscordGateway, new RecordingAuditLogger);

    expect($channel->key())->toBe('discord')
        ->and($channel->supports(Channel::Discord))->toBeTrue()
        ->and($channel->supports(Channel::Sms))->toBeFalse()
        ->and($channel->supports(Channel::Telegram))->toBeFalse();
});

it('sends a message and records channel.delivery.sent', function (): void {
    $gateway = new FakeDiscordGateway;
    $audit = new RecordingAuditLogger;
    $channel = makeChannel($gateway, $audit);

    $result = $channel->send(
        PhoneIdentifier::from('+393331234567'),
        'High-risk login blocked for account #42',
        Channel::Discord,
        new SecurityContext('r'),
    );

    expect($result->accepted())->toBeTrue()
        ->and($result->provider)->toBe('discord')
        ->and($gateway->sent)->toHaveCount(1)
        ->and($gateway->sent[0]['message'])->toBe('High-risk login blocked for account #42')
        ->and($gateway->sent[0]['username'])->toBe('Rebel SOC');

    $sent = $audit->ofType('channel.delivery.sent');
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->channel)->toBe('discord')
        ->and($sent[0]->provider)->toBe('discord')
        ->and($sent[0]->metadata['message_status'])->toBe('sent')
        ->and($sent[0]->metadata['error_code'])->toBeNull()
        // The recipient (Discord channel/webhook id) is stored only as a keyed HMAC.
        ->and($sent[0]->identifierHmac)->not->toBeNull()
        ->and($sent[0]->identifierHmac)->not->toBe('+393331234567')
        ->and($sent[0]->keyVersion)->toBe(1);
});

it('fails gracefully and records channel.delivery.failed when the gateway errors', function (): void {
    $gateway = new FakeDiscordGateway(healthy: false);
    $audit = new RecordingAuditLogger;
    $channel = makeChannel($gateway, $audit);

    $result = $channel->send(
        PhoneIdentifier::from('+393331234567'),
        'should not arrive',
        Channel::Discord,
        new SecurityContext('r'),
    );

    expect($result->failed())->toBeTrue()
        ->and($result->reason)->toBe('provider_error')
        ->and($result->provider)->toBe('discord')
        ->and($gateway->sent)->toHaveCount(0);

    $failed = $audit->ofType('channel.delivery.failed');
    expect($audit->ofType('channel.delivery.sent'))->toHaveCount(0)
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->channel)->toBe('discord')
        ->and($failed[0]->provider)->toBe('discord')
        ->and($failed[0]->metadata['message_status'])->toBe('failed')
        ->and($failed[0]->metadata['error_code'])->toBe('provider_error')
        ->and($failed[0]->identifierHmac)->not->toBeNull();
});
