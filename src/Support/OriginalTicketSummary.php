<?php

namespace Padmission\Tickets\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

/*
 * Where an original ticket stands, said the same way beside its escalation
 * in the sidebar and in the pane: who answers its requester, and whether they
 * owe the reply or wait on one. It reads the original's own turn and
 * assignee, so both teams read the same sentence about it.
 */
final class OriginalTicketSummary
{
    protected const KEY = 'padmission-tickets::tickets.resources.tickets.original_state.';

    /**
     * @param  array<string, Model>  $people  everyone the originals name, by id
     */
    public function __construct(
        protected array $people,
        protected int|string|null $viewerId,
    ) {}

    /**
     * Everyone the originals' lines name, found in one query past the
     * viewer's scopes, since the originals may be another tenant's.
     *
     * @param  Collection<int, Ticket>  $originals
     */
    public static function for(Collection $originals): self
    {
        $ids = $originals
            ->flatMap(fn (Ticket $original): array => [$original->assignee_id, $original->closed_by])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $people = $ids === [] ? [] : TicketPlugin::resolveUserModelClass()::query()
            ->withoutGlobalScopes()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (Model $user): string => (string) $user->getKey())
            ->all();

        return new self($people, Filament::auth()->id());
    }

    public function handler(Ticket $original): ?Model
    {
        return blank($original->assignee_id) ? null : ($this->people[(string) $original->assignee_id] ?? null);
    }

    public function line(Ticket $original): string
    {
        $requester = $original->requesterName() ?? __('padmission-tickets::tickets.resources.tickets.the_requester');

        if ($original->isClosed) {
            $closer = blank($original->closed_by) ? null : ($this->people[(string) $original->closed_by] ?? null);
            $date = $original->closed_at?->timezone(TicketPlugin::get()->getDisplayTimezone())->format('M j');

            return $closer === null
                ? __(self::KEY.'closed_unknown', ['date' => $date])
                : __(self::KEY.'closed', ['name' => $this->nameOf($closer), 'date' => $date]);
        }

        $handler = $this->handler($original);
        $owes = $original->turn !== Turn::User;

        return match (true) {
            $handler === null => __(self::KEY.($owes ? 'unassigned' : 'unassigned_waiting'), ['requester' => $requester]),
            $this->isViewer($handler) => __(self::KEY.($owes ? 'owes_you' : 'waiting_you'), ['requester' => $requester]),
            default => __(self::KEY.($owes ? 'owes' : 'waiting'), ['handler' => $this->nameOf($handler), 'requester' => $requester]),
        };
    }

    /*
     * The person the original waits on, as its Waiting on field names them:
     * its handler while they owe the reply, otherwise its requester.
     */
    public function waitingOn(Ticket $original): ?string
    {
        if ($original->isClosed) {
            return null;
        }

        if ($original->turn === Turn::User) {
            return $original->requesterName() ?? __('padmission-tickets::tickets.resources.tickets.waiting_on.requester');
        }

        $handler = $this->handler($original);

        return match (true) {
            $handler === null => __('padmission-tickets::tickets.resources.tickets.waiting_on.unassigned'),
            $this->isViewer($handler) => __('padmission-tickets::tickets.side_you'),
            default => $this->nameOf($handler),
        };
    }

    public function nameOf(Model $person): string
    {
        return Filament::getUserName($person);
    }

    public function displayName(Model $person): string
    {
        return $this->isViewer($person) ? __('padmission-tickets::tickets.side_you') : $this->nameOf($person);
    }

    protected function isViewer(Model $person): bool
    {
        return filled($this->viewerId) && (string) $person->getKey() === (string) $this->viewerId;
    }
}
