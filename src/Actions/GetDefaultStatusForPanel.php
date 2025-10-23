<?php

namespace Padmission\Tickets\Actions;

use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

class GetDefaultStatusForPanel
{
    public function __invoke(string $panelId): TicketStatus
    {
        $defaultStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::query()
            ->where('panel', $panelId)
            ->orderBy('order', 'asc')
            ->first();

        if (! $defaultStatus) {
            throw new RuntimeException(sprintf(
                'No ticket status found for panel "%s". Please configure ticket statuses for this panel.',
                $panelId
            ));
        }

        return $defaultStatus;
    }
}
