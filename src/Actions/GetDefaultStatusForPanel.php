<?php

namespace Padmission\Tickets\Actions;

use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

class GetDefaultStatusForPanel
{
    public function __invoke(string $panelId, mixed $tenantId = null): TicketStatus
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);

        // A new ticket takes the first status of its own organization, the same
        // one closing and reopening use, once that organization is known.
        if (filled($tenantId)) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $defaultStatus = $statusModel::getOpenStatusFor((new $ticketModel)->forceFill([
                'panel' => $panelId,
                'tenant_id' => $tenantId,
            ]));
        } else {
            $defaultStatus = $statusModel::query()
                ->withoutGlobalScope(CurrentPanelScope::class)
                ->where('panel', $panelId)
                ->orderBy('order', 'asc')
                ->first();
        }

        if (! $defaultStatus) {
            throw new RuntimeException(sprintf(
                'No ticket status found for panel "%s". Please configure ticket statuses for this panel.',
                $panelId
            ));
        }

        return $defaultStatus;
    }
}
