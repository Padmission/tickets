<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Http\Controllers\StaffApi\Concerns\ResolvesStaffTicket;
use Padmission\Tickets\Services\TicketCloser;

/*
 * The panel's Close dialog: one of the ticket's own dispositions, then the
 * same close the dialog and the list's bulk Close run.
 */
class CloseTicketController
{
    use ResolvesStaffTicket;

    public function __invoke(Request $request, int $ticket): array
    {
        $record = $this->resolveTicket($ticket, $request->user());
        $closer = resolve(TicketCloser::class);

        abort_unless($closer->canClose($record), 403);

        $validated = $request->validate([
            'disposition_id' => ['nullable', 'integer'],
        ]);

        $dispositionId = $validated['disposition_id'] ?? null;

        if ($dispositionId !== null && ! $closer->dispositionsFor($record)->whereKey($dispositionId)->exists()) {
            throw ValidationException::withMessages(['disposition_id' => __('validation.exists', ['attribute' => 'disposition'])]);
        }

        $closer->close($record, $dispositionId);

        return (new ShowTicketController)($request, $record->id);
    }
}
