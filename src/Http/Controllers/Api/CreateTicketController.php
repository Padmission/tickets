<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Http\DataMappers\TicketMapper;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use Tiptap\Editor;

class CreateTicketController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $this->authorize('create', $ticketModel);

        $request->validate([
            'subject' => 'required|string|max:255',
        ]);

        $subject = (new Editor)
            ->setContent($request->input('subject'))
            ->getText();

        $defaultStatusId = TicketPlugin::resolveModelClass(TicketStatus::class)::query()
            ->tap(new CurrentPanelScope)
            ->first()
            ->id;

        $defaultPriorityId = TicketPlugin::resolveModelClass(TicketPriority::class)::query()
            ->tap(new CurrentPanelScope)
            ->first()
            ->id;

        $ticket = $ticketModel::create([
            'subject' => $subject,
            'submitter_id' => $request->user()->id,
            'turn' => Turn::User,
            'status_id' => $defaultStatusId,
            'priority_id' => $defaultPriorityId,
            'data' => [
                'url' => request()->input('url'),
                'ip_address' => request()->ip(),
            ],
        ]);

        return TicketMapper::map($ticket);
    }
}
