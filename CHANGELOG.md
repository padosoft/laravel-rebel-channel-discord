# Changelog

All notable changes to `padosoft/laravel-rebel-channel-discord` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-06-04

### Added
- **`DiscordDeliveryChannel`**: a Rebel Channels `MessageDeliveryChannel`
  (`key='discord'`, `supports(Channel::Discord)`) that delivers a message to a Discord
  channel via an incoming webhook — primarily for security/SOC alerts (anomaly cases,
  lockouts, high-risk events) and optionally OTP/notifications. Converts any
  transport/HTTP error into a clean `provider_error` failure (never throws out, never
  logs the webhook URL).
- **Gateway seam** (`DiscordGateway` + `HttpDiscordGateway`, the latter POSTing JSON
  `{ content }` via `Illuminate\Http\Client\Factory`) with a `FakeDiscordGateway` for
  offline tests.
- **Telemetry**: each send records a Rebel audit event via the core `AuditLogger` —
  `channel.delivery.sent` / `channel.delivery.failed`, with `channel: 'discord'`,
  `provider: 'discord'`, the recipient stored only as a keyed HMAC, and metadata
  `{ message_status, error_code }` — so the admin panel's Channel Performance shows
  Discord traffic.
- **Auto-registration**: binds the delivery channel to the `MessageDeliveryChannel`
  contract and tags it `rebel.delivery-channels`, but only when a `DISCORD_WEBHOOK_URL`
  is configured and `register_provider` is on (otherwise the package stays installed but
  dormant; the real HTTP gateway is bound only when needed).
- **Live test suite** (`tests/Live`, opt-in via `REBEL_DISCORD_LIVE=1` + a real
  `DISCORD_WEBHOOK_URL`) that posts to a real Discord channel; self-skips otherwise.
- Config file, `.env.example` (with the steps to create a Discord channel webhook), CI
  matrix (PHP 8.3/8.4/8.5 × Laravel 12/13), Pest suite, PHPStan level max, Pint, MIT
  license.

[Unreleased]: https://github.com/padosoft/laravel-rebel-channel-discord/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/padosoft/laravel-rebel-channel-discord/releases/tag/v0.1.0
