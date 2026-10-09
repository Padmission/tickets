<?php

namespace Padmission\Tickets\Actions;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

class GetDefaultPriorityForPanel
{
    public function __invoke(string $panelId, mixed $tenantId = null): TicketPriority
    {
        $priorityModel = TicketPlugin::resolveModelClass(TicketPriority::class);

        if (filled($tenantId)) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $defaultPriority = $priorityModel::getDefaultFor((new $ticketModel)->forceFill([
                'panel' => $panelId,
                'tenant_id' => $tenantId,
            ]));
        } else {
            $defaultPriority = $priorityModel::optionsForPanel($panelId)->orderBy('order', 'asc')->first();
        }

        if (! $defaultPriority) {
            throw new RuntimeException(sprintf(
                'No ticket priority found for panel "%s". Please configure ticket priorities for this panel.',
                $panelId
            ));
        }

        return $defaultPriority;
    }
}
