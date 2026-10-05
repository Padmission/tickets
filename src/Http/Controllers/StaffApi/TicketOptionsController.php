<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Http\Controllers\StaffApi\Concerns\ResolvesStaffTicket;
use Padmission\Tickets\Http\DataMappers\StaffApi\StaffTicketMapper;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketCloser;
use Padmission\Tickets\Services\TicketReassignment;
use Padmission\Tickets\TicketPlugin;

/*
 * The choices the panel's Edit and Close dialogs offer for this ticket:
 * its own panel's (and tenant's) statuses, priorities and dispositions, and
 * the people it may go to.
 */
class TicketOptionsController
{
    use ResolvesStaffTicket;
    use ScopesLookupsToTicket;

    public function __invoke(Request $request, int $ticket): array
    {
        $record = $this->resolveTicket($ticket, $request->user());

        $lookups = fn (string $model) => $this->scopeLookupToTicket(TicketPlugin::resolveModelClass($model)::query(), $record)
            ->orderBy('order')
            ->get()
            ->map(fn (Model $lookup): ?array => StaffTicketMapper::lookup($lookup))
            ->values()
            ->all();

        return [
            'data' => [
                'statuses' => $lookups(TicketStatus::class),
                'priorities' => $lookups(TicketPriority::class),
                'dispositions' => resolve(TicketCloser::class)->dispositionsFor($record)
                    ->orderBy('order')
                    ->get()
                    ->map(fn (Model $lookup): ?array => StaffTicketMapper::lookup($lookup))
                    ->values()
                    ->all(),
                'assignees' => collect(resolve(TicketReassignment::class)->eligible($record))
                    ->map(fn (string $name, int|string $id): array => ['id' => $id, 'name' => $name])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
