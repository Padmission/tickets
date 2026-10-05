<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Http\Controllers\StaffApi\Concerns\ResolvesStaffTicket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketReassignment;
use Padmission\Tickets\TicketPlugin;

/*
 * What the panel's Edit dialog changes, saved through the model so the
 * observer writes the same history notes and sends the same emails.
 */
class UpdateTicketController
{
    use AuthorizesRequests;
    use ResolvesStaffTicket;
    use ScopesLookupsToTicket;

    public function __invoke(Request $request, int $ticket): array
    {
        $record = $this->resolveTicket($ticket, $request->user());

        $this->authorize('update', $record);

        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'status_id' => ['sometimes', 'integer'],
            'priority_id' => ['sometimes', 'integer'],
            'assignee_id' => ['sometimes', 'integer'],
        ]);

        foreach (['status_id' => TicketStatus::class, 'priority_id' => TicketPriority::class] as $field => $model) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $exists = $this->scopeLookupToTicket(TicketPlugin::resolveModelClass($model)::query(), $record)
                ->whereKey($validated[$field])
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([$field => __('validation.exists', ['attribute' => str_replace('_id', '', $field)])]);
            }
        }

        $reassignment = resolve(TicketReassignment::class);

        if (array_key_exists('assignee_id', $validated) && ! $reassignment->isEligible($record, $validated['assignee_id'])) {
            throw ValidationException::withMessages(['assignee_id' => __('validation.in', ['attribute' => 'assignee'])]);
        }

        DB::transaction(function () use ($record, $validated, $reassignment): void {
            $record->update(collect($validated)->only(['subject', 'status_id', 'priority_id'])->all());

            if (array_key_exists('assignee_id', $validated) && $validated['assignee_id'] !== $record->assignee_id) {
                $reassignment->assign($record, $validated['assignee_id']);
            }
        });

        return (new ShowTicketController)($request, $record->id);
    }
}
