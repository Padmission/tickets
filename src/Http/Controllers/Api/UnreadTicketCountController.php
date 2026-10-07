<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\CopilotTicketService;
use Padmission\Tickets\TicketPlugin;

class UnreadTicketCountController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $this->authorize('create', $ticketModel);

        $query = $ticketModel::query()
            ->where('submitter_id', $request->user()->id)
            ->withoutEscalations()
            ->when(! $request->boolean('include_closed'), fn ($query) => $query->open());

        resolve(CopilotTicketService::class)->withUnreadSupportResponseConstraint($query, $request->user());

        return [
            'unread_count' => $query->count(),
        ];
    }
}
