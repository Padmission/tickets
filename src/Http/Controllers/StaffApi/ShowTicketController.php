<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Http\Request;
use Padmission\Tickets\Http\Controllers\StaffApi\Concerns\ResolvesStaffTicket;
use Padmission\Tickets\Http\DataMappers\StaffApi\StaffTicketMapper;
use Padmission\Tickets\Support\ConversationState;

class ShowTicketController
{
    use ResolvesStaffTicket;

    public function __invoke(Request $request, int $ticket): array
    {
        $record = $this->resolveTicket($ticket, $request->user());

        $record->loadMissing(['status', 'priority', 'disposition', 'assignee', 'submitter', 'latestMessage', 'childTickets']);

        return ['data' => StaffTicketMapper::detail($record, ConversationState::for($record), $request->user())];
    }
}
