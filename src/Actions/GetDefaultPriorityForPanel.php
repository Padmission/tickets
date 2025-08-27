<?php

namespace Padmission\Tickets\Actions;

use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

class GetDefaultPriorityForPanel
{
    public function __invoke(string $panelId): TicketPriority
    {
        $defaultPriority = TicketPlugin::resolveModelClass(TicketPriority::class)::query()
            ->where('panel', $panelId)
            ->orderBy('order', 'asc')
            ->first();

        if (! $defaultPriority) {
            throw new RuntimeException(sprintf(
                'No ticket priority found for panel "%s". Please configure ticket priorities for this panel.',
                $panelId
            ));
        }

        return $defaultPriority;
    }
}
