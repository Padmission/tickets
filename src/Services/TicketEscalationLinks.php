<?php

namespace Padmission\Tickets\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

/*
 * Every change to an escalation link goes through here: each one re-reads the
 * link under a row lock so a concurrent change is refused rather than
 * overwritten, saves through the model so host model events fire, and leaves
 * a history entry on both tickets.
 */
class TicketEscalationLinks
{
    public const ALREADY_ESCALATED = 'already_escalated';

    public const NOT_LINKABLE = 'not_linkable';

    /**
     * @return self::ALREADY_ESCALATED|self::NOT_LINKABLE|null
     */
    public function addToEscalation(Ticket $original, int|string $escalationId): ?string
    {
        return DB::transaction(function () use ($original, $escalationId): ?string {
            if (filled($this->lockedLink($original))) {
                return self::ALREADY_ESCALATED;
            }

            $escalation = LinkedTicketCandidates::openEscalations(static::query(), $original)
                ->whereKey($escalationId)
                ->lockForUpdate()
                ->first();

            if ($escalation === null) {
                return self::NOT_LINKABLE;
            }

            $this->link($original, $escalation);

            return null;
        });
    }

    /*
     * Call inside the transaction that opens the new escalation: the row lock
     * taken here is held until it commits, so nobody can link the ticket
     * elsewhere in between.
     */
    public function canOpenEscalation(Ticket $original): bool
    {
        return blank($this->lockedLink($original));
    }

    public function linkNewEscalation(Ticket $original, Ticket $escalation): void
    {
        $this->link($original, $escalation);
    }

    public function removeFromEscalation(Ticket $original): bool
    {
        return DB::transaction(function () use ($original): bool {
            $escalationId = $this->lockedLink($original);

            if (blank($escalationId)) {
                return false;
            }

            $escalation = static::query()->withoutGlobalScopes()->find($escalationId);

            $this->writeLink($original, null);

            $this->addActivity($original, ActivityType::RemovedFromEscalation, ['escalation' => $escalationId]);

            if ($escalation !== null) {
                $this->addActivity($escalation, ActivityType::OriginalRemoved, ['original' => $original->getKey()]);
            }

            return true;
        });
    }

    /**
     * @param  array<int|string>  $selectedIds
     */
    public function syncOriginals(Ticket $escalation, array $selectedIds): bool
    {
        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));

        return DB::transaction(function () use ($escalation, $selectedIds): bool {
            // Removals come from the escalation's actual links, not from the
            // picker's rules, so a link those rules would no longer offer (such
            // as one made before they tightened) can still be cleared.
            $linkedIds = $this->linkedOriginalsQuery($escalation->getKey())->pluck('id')->map(fn ($id): int => (int) $id)->all();

            // Locking every affected row in key order keeps two concurrent
            // saves of the same escalation from deadlocking on each other.
            $lockIds = array_values(array_unique([...$selectedIds, ...$linkedIds]));
            sort($lockIds);

            $locked = static::query()->withoutGlobalScopes()
                ->whereKey($lockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Ticket $ticket): int => (int) $ticket->getKey());

            $linkedIds = $locked->filter(fn (Ticket $ticket): bool => $ticket->linked_ticket_id == $escalation->getKey())->keys()->all();
            $toAdd = array_values(array_diff($selectedIds, $linkedIds));
            $toRemove = array_values(array_diff($linkedIds, $selectedIds));

            if ($toAdd !== [] && LinkedTicketCandidates::children(static::query(), $escalation)->whereKey($toAdd)->count() < count($toAdd)) {
                return false;
            }

            foreach ($toRemove as $id) {
                $this->writeLink($locked[$id], null);
                $this->addActivity($locked[$id], ActivityType::RemovedFromEscalation, ['escalation' => $escalation->getKey()]);
                $this->addActivity($escalation, ActivityType::OriginalRemoved, ['original' => $id]);
            }

            foreach ($toAdd as $id) {
                $this->link($locked[$id], $escalation);
            }

            return true;
        });
    }

    /**
     * @return Builder<Ticket>
     */
    public function linkedOriginalsQuery(int|string $escalationId): Builder
    {
        return static::query()->withoutGlobalScopes()->where('linked_ticket_id', $escalationId);
    }

    protected function link(Ticket $original, Ticket $escalation): void
    {
        $this->writeLink($original, $escalation->getKey());

        $this->addActivity($original, ActivityType::AddedToEscalation, ['escalation' => $escalation->getKey()]);
        $this->addActivity($escalation, ActivityType::OriginalAdded, ['original' => $original->getKey()]);
    }

    /*
     * The link is not news to the requester, so it leaves updated_at alone:
     * that timestamp was the one trace of an escalation in their list.
     */
    protected function writeLink(Ticket $original, int|string|null $escalationId): void
    {
        $original::withoutTimestamps(fn () => $original->update(['linked_ticket_id' => $escalationId]));
    }

    /*
     * The link as it is in the database right now, under a row lock, synced
     * back onto the model so a stale in-memory value is never saved.
     */
    protected function lockedLink(Ticket $original): mixed
    {
        $current = $original->newModelQuery()->whereKey($original->getKey())->lockForUpdate()->value('linked_ticket_id');

        $original->linked_ticket_id = $current;
        $original->syncOriginalAttribute('linked_ticket_id');

        return $current;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function addActivity(Ticket $ticket, ActivityType $type, array $data): void
    {
        $ticket->addTicketActivity(
            type: $type,
            sender: ActivitySender::System,
            userId: auth()->id(),
            data: $data,
        );
    }

    /**
     * @return Builder<Ticket>
     */
    protected static function query(): Builder
    {
        return TicketPlugin::resolveModelClass(Ticket::class)::query();
    }
}
