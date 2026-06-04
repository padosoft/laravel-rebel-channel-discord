# CLAUDE.md — AI working guide for `padosoft/laravel-rebel-channel-discord`

> Working on this package with an AI agent (Claude Code, Cursor, Copilot, Codex)? Read this first.
> It's the "batteries" that make vibe-coding here land on the first try. Plain Markdown — every
> tool can read it.

## What this package is
A **Discord delivery channel** for Laravel Rebel Channels: it ships an arbitrary message to a
Discord channel via an **incoming webhook** — primarily **security/SOC alerts** (anomaly cases,
lockouts, high-risk events) and optionally OTP/notifications.

Part of the **Laravel Rebel** suite — an enterprise authentication control plane over Laravel
Fortify. The shared language (value objects, contracts, the audit trail) lives in
`padosoft/laravel-rebel-core`; this package builds on it. It implements the
`MessageDeliveryChannel` contract defined in `padosoft/laravel-rebel-channels`.

## Non-negotiable conventions
- `declare(strict_types=1);` in every PHP file; `final` classes; constructor property promotion.
- **PHPStan level max** must stay green. Do NOT add `@phpstan-ignore`, baseline entries, or
  `assert()`/inline `@var` to silence errors — fix the root cause. Common recipes:
  - narrow `mixed` before casting: `is_scalar($x) ? (string) $x : null`;
  - `json_decode($s, true)` is `array<array-key, mixed>`;
  - the container's `make(Repository::class)` is already typed `Illuminate\Contracts\Config\Repository`.
- **Tests:** Pest, Testbench. Cover happy path, graceful failure, support check, registration gating.
- **Style:** Pint (`composer pint`). **Docs/comments in English.**
- Package wiring uses `spatie/laravel-package-tools` (`configurePackage`).

## Architecture (the seams)
- **`Contracts\DiscordGateway`** — the seam over Discord's incoming-webhook HTTP API. Production
  calls go through `Gateway\HttpDiscordGateway` (POSTs JSON `{ content }` via
  `Illuminate\Http\Client\Factory`); tests bind `Testing\FakeDiscordGateway` instead of hitting
  the network. The gateway MUST throw on a non-2xx and MUST NEVER include the webhook URL (a
  secret) in an exception/log.
- **`Delivery\DiscordDeliveryChannel`** — implements `MessageDeliveryChannel`: `key()='discord'`,
  `supports(Channel::Discord)`, `send()` posts the message via the gateway and returns a
  `DeliveryResult`. It **catches `\Throwable`** → graceful `provider_error` failure. The
  `PhoneIdentifier` recipient's normalized value is the **Discord channel/webhook id**; the actual
  POST target is the configured `webhook_url`.
- **`RebelDiscordServiceProvider`** — binds the real gateway (unless already bound, so a fake wins
  in tests) and registers the delivery channel into the container (`MessageDeliveryChannel` +
  the `rebel.delivery-channels` tag) ONLY when `register_provider` is on AND a `DISCORD_WEBHOOK_URL`
  is present. No URL → nothing is wired.

## Security & telemetry rules (suite-wide)
- Never store PII in cleartext: the recipient (Discord channel/webhook id) is a **keyed HMAC** (core
  `KeyedHasher`). **Never log the webhook URL / token** (it's a secret: anyone holding it can post).
- **Telemetry completeness:** every send records through the core `AuditLogger` contract (persisted
  to `rebel_auth_events`, configurable sync|queue, Horizon-ready):
  - `channel.delivery.sent` on success, `channel.delivery.failed` on a caught error;
  - `channel: 'discord'`, `provider: 'discord'`, `identifierHmac` = keyed-HMAC of the channel id,
    `metadata: { message_status, error_code }`.
  This is what fills the panel's Channel Performance / audit trail for Discord — never skip it.

## Definition of Done (per change)
1. Red→green with Pest; `composer phpstan` (max) + `composer pint -- --test` clean.
2. One feature branch, one PR to `main`. CI matrix **PHP 8.3/8.4/8.5 × Laravel 12/13** must be green.
3. Update `README.md` + `CHANGELOG.md`. Squash-merge.
4. **Release:** `git tag vX.Y.Z && git push origin vX.Y.Z` + `gh release create`. Stay in `0.1.x`
   (Composer `^0.1` excludes `0.2.0` and would break dependents).

## Skills
This repo ships invocable skills under `.claude/skills/` — at least `rebel-package-dev` (the dev
loop + PHPStan-max recipes). Invoke it before non-trivial work.

---

> **Operational rules (Italian):** see **`AGENTS.md`** for the full workflow contract (branching,
> Definition of Done, local loop + GitHub gates, guardrails, didactic READMEs, design-lock).
