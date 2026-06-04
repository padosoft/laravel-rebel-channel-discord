<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Padosoft\Rebel\Channel\Discord\Gateway\HttpDiscordGateway;

it('POSTs the message as JSON content to the webhook URL', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'discord.com/*' => $http->response('', 204),
    ]);

    $gateway = new HttpDiscordGateway($http);
    $gateway->send(
        'https://discord.com/api/webhooks/123/test-token',
        'SOC alert: account locked',
        'Rebel SOC',
        'https://example.test/avatar.png',
    );

    $http->assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://discord.com/api/webhooks/123/test-token'
            && $request->method() === 'POST'
            && ($body['content'] ?? null) === 'SOC alert: account locked'
            && ($body['username'] ?? null) === 'Rebel SOC'
            && ($body['avatar_url'] ?? null) === 'https://example.test/avatar.png';
    });
});

it('omits username and avatar from the payload when not provided', function (): void {
    $http = new HttpFactory;
    $http->fake(['discord.com/*' => $http->response('', 204)]);

    (new HttpDiscordGateway($http))->send('https://discord.com/api/webhooks/123/test-token', 'plain');

    $http->assertSent(function (Request $request): bool {
        $body = $request->data();

        return ($body['content'] ?? null) === 'plain'
            && ! array_key_exists('username', $body)
            && ! array_key_exists('avatar_url', $body);
    });
});

it('throws on a non-successful HTTP response without leaking the webhook URL', function (): void {
    $http = new HttpFactory;
    $http->fake(['discord.com/*' => $http->response('rate limited', 429)]);

    $gateway = new HttpDiscordGateway($http);

    try {
        $gateway->send('https://discord.com/api/webhooks/secret/token', 'x');
        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('429')
            ->and($e->getMessage())->not->toContain('secret')
            ->and($e->getMessage())->not->toContain('token');
    }
});
