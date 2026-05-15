<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Tools\Concerns;

use Padmission\Tickets\Copilot\Agent\Middleware\AuditMiddleware;
use Padmission\Tickets\Copilot\Enums\AuditAction;

trait LogsAudit
{
    protected function audit(
        AuditAction $action,
        ?string $resourceType = null,
        ?string $recordKey = null,
        ?array $payload = null,
    ): void {
        if (! config('filament-copilot.audit.enabled', true)) {
            return;
        }

        AuditMiddleware::logAction(
            action: $action,
            user: $this->user,
            panelId: $this->panelId,
            tenant: $this->tenant,
            resourceType: $resourceType,
            recordKey: $recordKey,
            payload: $payload,
        );
    }
}
