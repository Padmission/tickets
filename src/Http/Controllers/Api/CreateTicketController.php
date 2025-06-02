<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Priority;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class CreateTicketController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
        ]);

        $ticket = TicketPlugin::resolveModelClass(Ticket::class)::create([
            ...$validated,
            'submitter_id' => $request->user()->id,
            'turn' => Turn::User,
            'status_id' => TicketPlugin::resolveModelClass(Status::class)::first()->id,
            'priority_id' => TicketPlugin::resolveModelClass(Priority::class)::first()->id,
        ]);

        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
        ];
    }
}
