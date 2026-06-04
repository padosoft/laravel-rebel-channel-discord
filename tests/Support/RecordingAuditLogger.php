<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Discord\Tests\Support;

use Padosoft\Rebel\Core\Audit\AuditEvent;
use Padosoft\Rebel\Core\Contracts\AuditLogger;

/**
 * In-memory {@see AuditLogger} for tests: keeps every recorded event so assertions can
 * inspect the audit trail without touching the database.
 */
final class RecordingAuditLogger implements AuditLogger
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function record(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<AuditEvent> */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->events, fn (AuditEvent $e): bool => $e->type === $type));
    }
}
