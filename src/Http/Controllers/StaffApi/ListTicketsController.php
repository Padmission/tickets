<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Http\DataMappers\StaffApi\StaffTicketMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\TicketPlugin;

/*
 * The panel's ticket list as queues: the same tickets, ranking and "needs
 * you" rule as its All and My tabs, so the desktop client and the panel
 * never disagree about what is waiting on whom.
 */
class ListTicketsController
{
    public const array VIEWS = ['needs_you', 'mine', 'unassigned', 'open', 'closed', 'all'];

    public function __invoke(Request $request): array
    {
        $validated = $request->validate([
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = static::forView($validated['view'] ?? 'open');

        if (filled($search = $validated['search'] ?? null)) {
            $number = TicketResource::ticketNumberFromSearch($search);

            $subject = $query->qualifyColumn('subject');

            $query->where(fn (Builder $query): Builder => $number !== null
                ? $query->whereKey($number)->orWhere($subject, 'like', '%'.$search.'%')
                : $query->where($subject, 'like', '%'.$search.'%'));
        }

        $page = static::ordered($query)
            ->with(['status', 'priority', 'assignee', 'submitter', 'latestMessage'])
            ->paginate($validated['per_page'] ?? 50);

        return [
            'data' => collect($page->items())
                ->map(fn (Ticket $ticket): array => StaffTicketMapper::summary($ticket, $request->user()))
                ->all(),
            'meta' => [
                'view' => $validated['view'] ?? 'open',
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    public static function forView(string $view): Builder
    {
        /** @var Builder<Ticket> $tickets */
        $tickets = TicketResource::allTicketsQuery()->withConversationState();

        /** @phpstan-ignore method.notFound, method.notFound */
        return match ($view) {
            'needs_you' => static::needsYou($tickets->open()),
            'mine' => $tickets->open()->whereIn('assignee_id', TicketPlugin::get()->getCurrentUserAssigneeIds() ?: [0]),
            'unassigned' => $tickets->open()->whereNull('assignee_id'),
            'open' => $tickets->open(),
            'closed' => $tickets->closed(),
            default => $tickets,
        };
    }

    /**
     * The rows the panel's Needs You card counts.
     *
     * @param  Builder<Ticket>  $tickets
     * @return Builder<Ticket>
     */
    public static function needsYou(Builder $tickets): Builder
    {
        [$rank, $bindings] = ConversationStateQuery::rankExpression(ConversationViewer::current());

        return $tickets->whereRaw("{$rank} = 0", $bindings);
    }

    /**
     * The panel table's own order: who it waits on, then the latest activity.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function ordered(Builder $query): Builder
    {
        return TicketResource::orderByRank($query, 'asc')
            ->orderBy(
                fn ($query) => $query
                    ->select('created_at')
                    ->from((new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable())
                    ->whereColumn('ticket_id', 'tickets.id')
                    ->latest()
                    ->limit(1),
                'desc'
            );
    }
}
