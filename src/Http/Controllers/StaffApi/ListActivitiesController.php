<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Http\Request;
use Padmission\Tickets\Http\Controllers\StaffApi\Concerns\ResolvesStaffTicket;
use Padmission\Tickets\Http\DataMappers\StaffApi\StaffActivityMapper;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\TicketActivityService;

/*
 * The conversation as the panel's supporters read it: their history notes
 * included, oldest first. `after_id` fetches what is new since a client's
 * last poll; `before_id` pages back through older entries.
 */
class ListActivitiesController
{
    use ResolvesStaffTicket;

    public function __invoke(Request $request, int $ticket): array
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $record = $this->resolveTicket($ticket, $request->user());
        $limit = $validated['limit'] ?? 100;
        $service = resolve(TicketActivityService::class);

        if (isset($validated['before_id'])) {
            $older = $service->getActivities($record, user: $request->user())
                ->filter(fn (TicketActivity $activity): bool => $activity->id < $validated['before_id']);
            $activities = $older->slice(-$limit)->values();
            $hasMore = $older->count() > $limit;
        } else {
            $activities = $service->getActivities($record, $validated['after_id'] ?? null, $limit + 1, $request->user());
            $hasMore = $activities->count() > $limit;
            $activities = $activities->slice(-$limit)->values();
        }

        $activities->loadMissing('attachments');

        return [
            'data' => $activities->map(fn (TicketActivity $activity): array => StaffActivityMapper::map($activity))->all(),
            'meta' => ['has_more' => $hasMore],
        ];
    }
}
