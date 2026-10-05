<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Http\Request;
use Padmission\Tickets\Http\Controllers\StaffApi\Concerns\ResolvesStaffTicket;
use Padmission\Tickets\Services\TicketReopening;

class ReopenTicketController
{
    use ResolvesStaffTicket;

    public function __invoke(Request $request, int $ticket): array
    {
        $record = $this->resolveTicket($ticket, $request->user());

        abort_unless(
            in_array(TicketReopening::REOPEN, resolve(TicketReopening::class)->choicesFor($record, $request->user()), true),
            403,
        );

        $record->reopen($request->user()->getAuthIdentifier());

        return (new ShowTicketController)($request, $record->id);
    }
}
