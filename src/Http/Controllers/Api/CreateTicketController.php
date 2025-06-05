<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;
use Tiptap\Editor;

class CreateTicketController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $request->validate([
            'subject' => 'required|string|max:255',
        ]);

        $subject = (new Editor)
            ->setContent($request->input('subject'))
            ->getText();

        $ticket = TicketPlugin::resolveModelClass(Ticket::class)::create([
            'subject' => $subject,
            'submitter_id' => $request->user()->id,
            'turn' => Turn::User,
            'status_id' => TicketPlugin::resolveModelClass(TicketStatus::class)::first()->id,
            'priority_id' => TicketPlugin::resolveModelClass(TicketPriority::class)::first()->id,
        ]);

        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
        ];
    }
}
