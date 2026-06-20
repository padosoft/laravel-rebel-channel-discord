# Laravel Rebel — Discord Channel

> Official documentation: https://doc.laravel-rebel.padosoft.com


> **Ship your security alerts to Discord, the Rebel way.** This package plugs a Discord
> [incoming webhook](https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks)
> into [`laravel-rebel-channels`](https://github.com/padosoft/laravel-rebel-channels) as a
> `MessageDeliveryChannel` — so your **SOC/security alerts** (anomaly cases, account lockouts,
> high-risk logins) and notifications land in a Discord channel, with Rebel's HMAC'd audit trail
> on top. Part of the `padosoft/laravel-rebel-*` suite.

<p align="center">
  <img src="resources/screenshoots/Laravel-Rebel-banner.png" alt="Laravel Rebel" width="100%">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12|13">
  <img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PHPStan-max-2A6FDB?style=flat-square" alt="PHPStan max">
  <img src="https://img.shields.io/badge/tests-Pest%204-22C55E?style=flat-square" alt="Pest 4">
  <img src="https://img.shields.io/badge/Discord-Webhook-5865F2?style=flat-square&logo=discord&logoColor=white" alt="Discord Webhook">
  <img src="https://img.shields.io/badge/license-MIT-blue?style=flat-square" alt="MIT">
</p>

---

## Table of contents

- [What it is](#what-it-is)
- [Quick glossary](#quick-glossary)
- [Why this package](#why-this-package)
- [Rebel + Discord vs the alternatives](#rebel--discord-vs-the-alternatives)
- [How it works (step by step)](#how-it-works-step-by-step)
- [Create a Discord webhook (step by step)](#create-a-discord-webhook-step-by-step)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Telemetry & the audit trail](#telemetry--the-audit-trail)
- [Live tests against the real webhook](#live-tests-against-the-real-webhook)
- [`.env.example`](#envexample)
- [Security notes](#security-notes)
- [🔋 Vibe coding with batteries included](#-vibe-coding-with-batteries-included)
- [Testing & License](#testing--license)

---

## What it is

A thin, well-tested **Discord delivery channel** for Rebel Channels. It implements the Channels
`MessageDeliveryChannel` contract (`key='discord'`, `supports(Channel::Discord)`) and posts a
message to a Discord channel through an **incoming webhook** — no bot, no OAuth, just a URL.

Its first job is **delivering security/SOC alerts**: when Rebel detects an anomaly case, an account
lockout, or a high-risk event, you forward a human-readable line to your `#soc-alerts` channel so
the team sees it in real time. It also works for plain notifications or OTP delivery where Discord
is an acceptable transport.

A small **gateway seam** (`DiscordGateway`) wraps the HTTP call, so the whole thing is unit-testable
offline and has a real **live** test-suite for an actual webhook.

Depends on [`padosoft/laravel-rebel-core`](https://github.com/padosoft/laravel-rebel-core)
and [`padosoft/laravel-rebel-channels`](https://github.com/padosoft/laravel-rebel-channels).

---

## Quick glossary

| Term | In plain words |
|---|---|
| **Incoming webhook** | A per-channel URL Discord gives you. POST JSON to it and a message appears in that channel. No bot or login needed. |
| **Webhook URL** | `https://discord.com/api/webhooks/<id>/<token>` — a **secret**: anyone with it can post to your channel. |
| **SOC alert** | A "Security Operations Center" notification — e.g. *"account #42 locked after 5 failed logins"*. |
| **Delivery channel** | A Rebel object that knows how to *send a message* over one transport (Discord, SMS, …). Here: Discord. |
| **Audit event** | A row Rebel writes to `rebel_auth_events` so the admin panel can show what happened (who, when, which channel). |
| **Keyed HMAC** | A one-way, peppered hash. We store the recipient as an HMAC, never in clear, so the audit trail leaks no PII. |

---

## Why this package

| ★ | What | In short |
|---|---|---|
| ★★★ | **SOC alerts in Discord** | Forward anomaly cases / lockouts / high-risk events to a channel your team already watches. |
| ★★★ | **Zero-bot setup** | Just an incoming-webhook URL — no Discord bot, no OAuth, no gateway connection to maintain. |
| ★★★ | **Full Rebel telemetry** | Every send writes a `channel.delivery.sent` / `.failed` audit event, so the panel's Channel Performance shows Discord traffic. |
| ★★ | **Never throws out** | Any transport/HTTP error becomes a clean `provider_error` `DeliveryResult` — your alerting path never explodes. |
| ★★ | **Offline-testable** | A gateway seam + fake means your tests don't hit Discord; a separate live suite does. |
| ★★ | **Safe by default** | No webhook URL → nothing registers; the URL is treated as a secret and never logged. |
| ★ | **Privacy-first audit** | The recipient (channel id) is stored only as a keyed HMAC. |

---

## Rebel + Discord vs the alternatives

Getting a security alert into Discord, four ways:

| Capability | **Rebel + this package** | Shopify | Discord bot (discord.php / Gateway) | Raw `Http::post()` to a webhook |
|---|:---:|:---:|:---:|:---:|
| Self-hosted Discord **SOC-alert** channel | ✅ | ❌ | ➖ (you build it) | ➖ (you build it) |
| No bot / no OAuth (just a webhook URL) | ✅ | ❌ | ❌ | ✅ |
| Auto-wired into Rebel's delivery layer | ✅ | ❌ | ❌ | ❌ |
| Graceful failure → clean `provider_error` | ✅ | ❌ | ❌ | ❌ (you handle exceptions) |
| Unified **audit trail** (recipient HMAC'd) | ✅ | ❌ | ❌ | ❌ |
| Panel **Channel Performance** for Discord | ✅ | ❌ | ❌ | ❌ |
| Webhook URL kept secret / never logged | ✅ | ➖ | ➖ | ❌ (easy to leak in logs) |
| Offline test seam + live suite | ✅ | ❌ | ➖ | ❌ |

> Legend: ✅ built-in · ➖ partial / DIY · ❌ not available.
> **Shopify** is a closed, hosted commerce platform: it has **no self-hosted Discord SOC-alert
> channel** at all — you can't point its auth/security events at your own Discord, self-host the
> sender, or get a unified, HMAC'd audit trail of deliveries. A black box, not a developer-facing
> security-alerting channel. A **Discord bot** can post too, but it means running a Gateway
> connection and OAuth you don't need for one-way alerts. A **raw `Http::post()`** works until you
> want graceful failure, a secret-safe URL, audit telemetry and tests — which is exactly what this
> package gives you for free.

---

## How it works (step by step)

```
 Rebel detects something                 this package                         Discord
 (anomaly / lockout / high-risk)
        │
        │  $channel->send($recipient, "🚨 account #42 locked", Channel::Discord, $ctx)
        ▼
 ┌──────────────────────────┐   HMAC(recipient)   ┌───────────────────────┐
 │ DiscordDeliveryChannel   │────────────────────▶│  AuditLogger (core)   │  channel.delivery.sent
 │  • supports(Discord)     │                     │  → rebel_auth_events  │  / channel.delivery.failed
 │  • catches \Throwable    │                     └───────────────────────┘
 └───────────┬──────────────┘
             │ DiscordGateway::send($webhookUrl, {content})
             ▼
 ┌──────────────────────────┐   POST {"content": …}   ┌───────────────────┐
 │ HttpDiscordGateway       │────────────────────────▶│  Discord webhook  │ ──▶ message appears
 │ (Illuminate Http client) │     204 No Content      │  #soc-alerts      │     in your channel
 └──────────────────────────┘                         └───────────────────┘
```

1. Something in Rebel wants to alert you. It calls the Discord delivery channel's `send()`.
2. The channel HMACs the recipient (the Discord channel id) and asks the gateway to POST the message.
3. The gateway POSTs `{"content": "<message>"}` (plus optional username/avatar) to your webhook URL.
   Discord replies `204 No Content` on success.
4. The channel records a `channel.delivery.sent` audit event (or `channel.delivery.failed` if the
   POST threw) — so the admin panel's Channel Performance shows the delivery. It returns a
   `DeliveryResult` either way; it **never throws out**.

---

## Create a Discord webhook (step by step)

1. In Discord, open the **server** and the **channel** you want alerts in (e.g. `#soc-alerts`).
   *(You need "Manage Webhooks" permission on that channel.)*
2. Click the **gear icon** (Edit Channel) next to the channel name.
3. Go to **Integrations → Webhooks → New Webhook**.
4. Give it a name (e.g. *"Rebel SOC"*) and optionally an avatar; make sure the target channel is
   selected.
5. Click **Copy Webhook URL**. It looks like
   `https://discord.com/api/webhooks/123456789012345678/AbCd…token`.
6. Put it in your `.env` as `DISCORD_WEBHOOK_URL` (see below). Done — the channel auto-registers.

> **Treat the URL as a secret.** Anyone who has it can post to your channel. Keep it out of version
> control (it's in `.gitignore` via `.env`) and never log it. To rotate it, delete the webhook in
> Discord and create a new one.

---

## Installation

```bash
composer require padosoft/laravel-rebel-channel-discord
php artisan vendor:publish --tag="rebel-channel-discord-config"
```

Add your webhook URL to `.env`:

```dotenv
DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/123456789012345678/your-webhook-token
```

That's it — the delivery channel registers itself into the container under the key `discord` and is
tagged `rebel.delivery-channels`.

---

## Configuration

File `config/rebel-channel-discord.php`:

| Key | Default | What it does |
|---|---|---|
| `webhook_url` | `env(DISCORD_WEBHOOK_URL)` | The Discord incoming-webhook URL (a secret). The channel registers only when this is set. |
| `username` | `env(DISCORD_WEBHOOK_USERNAME)` | Optional per-message display-name override. `null` = use the webhook's default. |
| `avatar_url` | `env(DISCORD_WEBHOOK_AVATAR_URL)` | Optional per-message avatar override. `null` = use the webhook's default. |
| `register_provider` | `true` (`env(REBEL_DISCORD_REGISTER)`) | Auto-register the channel into the container (only when `webhook_url` is set). `false` = installed but dormant. |

---

## Usage

The channel implements the Channels `MessageDeliveryChannel` contract. Resolve it (it's bound to the
contract and tagged `rebel.delivery-channels`) and call `send()`:

```php
use Padosoft\Rebel\Channels\Contracts\MessageDeliveryChannel;
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Core\Context\SecurityContext;
use Padosoft\Rebel\Core\Identifiers\PhoneIdentifier;

$discord = app(MessageDeliveryChannel::class); // the Discord channel (when it's the registered one)

// "recipient" identifies the Discord channel; its normalized value is HMAC'd for the audit trail.
$recipient = PhoneIdentifier::from('+10000000042'); // e.g. a numeric channel id you assign

$result = $discord->send(
    $recipient,
    '🚨 SOC: account #42 locked after 5 failed logins (IP risk: high)',
    Channel::Discord,
    SecurityContext::fromRequest($request),
);

if ($result->accepted()) {
    // delivered to Discord
} else {
    // $result->reason === 'provider_error' — log/alert via another path; we never threw
}
```

**Collect every delivery channel via the tag** (when you run several — Discord, Telegram, …):

```php
use Padosoft\Rebel\Channel\Discord\RebelDiscordServiceProvider;
use Padosoft\Rebel\Channels\Enums\Channel;

/** @var iterable<\Padosoft\Rebel\Channels\Contracts\MessageDeliveryChannel> $channels */
$channels = app()->tagged(RebelDiscordServiceProvider::DELIVERY_TAG);

foreach ($channels as $channel) {
    if ($channel->supports(Channel::Discord)) {
        $channel->send($recipient, $message, Channel::Discord, $context);
    }
}
```

> **Recipient note.** This channel reuses the core `PhoneIdentifier` as the *recipient* value object
> (the suite's delivery contract is typed on it). Its `normalized()` value is treated as the
> **Discord channel/webhook id** and is only ever stored as a keyed HMAC. The actual POST target is
> always the configured `webhook_url` — so one configured channel maps to one Discord channel.

---

## Telemetry & the audit trail

Delivery is only useful if you can *see* it. On **every** send this channel records one Rebel audit
event through the core `AuditLogger` (persisted to `rebel_auth_events`; sync or queued per your core
config), so the admin panel's **Channel Performance** shows Discord traffic — successes and
failures alike.

| Outcome | Audit `event_type` | `channel` | `provider` |
|---|---|---|---|
| Webhook accepted the post | `channel.delivery.sent` | `discord` | `discord` |
| Gateway threw (any error) | `channel.delivery.failed` | `discord` | `discord` |

Each event stores the recipient as a **keyed HMAC** (`identifierHmac` + `keyVersion`, never in
clear) and a `metadata` object:

```json
{
  "message_status": "sent",
  "error_code": null
}
```

On failure, `message_status` is `"failed"` and `error_code` is `"provider_error"`. The webhook URL
and the message content are **never** put in the audit metadata.

---

## Live tests against the real webhook

The offline suite uses a fake gateway. To exercise a **real** Discord webhook (`tests/Live`), opt in
explicitly — it **posts a real message** to your channel:

```bash
# .env (or shell env)
REBEL_DISCORD_LIVE=1
DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/123.../your-token

vendor/bin/pest --group=live
```

Without `REBEL_DISCORD_LIVE=1` or with no webhook URL, the live test **self-skips**, so
`composer test` and external PRs never trigger a post. In CI, supply the URL as a **secret** and set
`REBEL_DISCORD_LIVE=1` on a dedicated job.

---

## `.env.example`

```dotenv
# The Discord incoming-webhook URL (a SECRET — never commit or log it).
DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/123456789012345678/your-webhook-token

# Optional per-message display overrides (empty = use the webhook defaults).
DISCORD_WEBHOOK_USERNAME=Rebel SOC
DISCORD_WEBHOOK_AVATAR_URL=

# Auto-register the Discord delivery channel (needs DISCORD_WEBHOOK_URL).
REBEL_DISCORD_REGISTER=true

# Live tests (opt-in: POSTS A REAL MESSAGE)
REBEL_DISCORD_LIVE=0
```

---

## Security notes

- **Webhook URL is a secret**: anyone with it can post to your channel. It's read from `.env`, never
  committed, and **never logged** — not even in exceptions (the gateway error reports only the HTTP
  status code).
- **No exception leakage**: transport/HTTP errors are caught and returned as a generic
  `provider_error` `DeliveryResult` — your alerting path never throws out.
- **No PII in the audit trail**: the recipient (Discord channel id) is stored only as a keyed HMAC;
  the message content is not written to audit metadata.
- **Dormant until configured**: with no `DISCORD_WEBHOOK_URL` (or `register_provider=false`) the
  package installs cleanly and wires nothing.

---

## 🔋 Vibe coding with batteries included

This package ships **AI batteries** — so you (and your AI agent) can extend it correctly on the
first try:

- **`CLAUDE.md`** — a concise AI working guide (purpose, conventions, architecture, how to extend,
  Definition of Done). Plain Markdown, so Claude Code, Cursor, Copilot and Codex all read it.
- **`AGENTS.md`** — the agent/workflow contract (branch → PR → CI → tag/release, the gates).
- **`.claude/skills/`** — invocable skills (at least `rebel-package-dev`) encoding the suite's
  TDD loop, the **PHPStan-level-max** recipes, the security/telemetry rules, and the release
  discipline.

Open the repo in your AI editor and just start — the rules, guardrails and extension recipes come
with it. PRs that follow the shipped `CLAUDE.md` pass CI (PHPStan max + Pest + Pint) and review the
first time around.

## Testing & License

```bash
composer test      # Pest (delivery channel + gateway + registration; live suite self-skips)
composer phpstan   # static analysis, level max
composer pint      # code style
```

**License:** MIT — see [LICENSE](LICENSE). Part of the [`padosoft/laravel-rebel`](https://github.com/padosoft) suite.

