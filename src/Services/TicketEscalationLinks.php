<?php

namespace Padmission\Tickets\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketHandedOverEvent;
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
            $currentId = $this->lockedLink($original);
            $current = $this->linkedRow($currentId);

            if (static::isOpen($current)) {
                return self::ALREADY_ESCALATED;
            }

            $escalation = LinkedTicketCandidates::openEscalations(static::query(), $original)
                ->whereKey($escalationId)
                ->lockForUpdate()
                ->first();

            if ($escalation === null) {
                return self::NOT_LINKABLE;
            }

            if (filled($currentId)) {
                $this->unlink($original, $currentId, $current);
            }

            $this->link($original, $escalation);

            return null;
        });
    }

    /*
     * Call inside the transaction that opens the new escalation: the row lock
     * taken here is held until it commits, so nobody can link the ticket
     * elsewhere in between. A link to an escalation that is gone or closed is
     * cleared here, so the new one replaces it.
     */
    public function canOpenEscalation(Ticket $original): bool
    {
        $currentId = $this->lockedLink($original);

        if (blank($currentId)) {
            return true;
        }

        $current = $this->linkedRow($currentId);

        if (static::isOpen($current)) {
            return false;
        }

        $this->unlink($original, $currentId, $current);

        return true;
    }

    public function linkNewEscalation(Ticket $original, Ticket $escalation): void
    {
        $this->link($original, $escalation, started: true);
    }

    /*
     * Read past host scopes, so an escalation the viewer cannot see still
     * blocks a new one.
     */
    public function hasOpenEscalation(Ticket $original): bool
    {
        return static::isOpen($this->linkedRow($original->linked_ticket_id));
    }

    public function hasClosedEscalation(Ticket $original): bool
    {
        $escalation = $this->linkedRow($original->linked_ticket_id);

        return $escalation !== null && ! $escalation->trashed() && $escalation->isClosed;
    }

    /*
     * The original's escalation, read past host scopes. A deleted one is no
     * escalation at all.
     */
    public function escalationOf(Ticket $original): ?Ticket
    {
        $escalation = $this->linkedRow($original->linked_ticket_id);

        return $escalation?->trashed() === false ? $escalation : null;
    }

    public function removeFromEscalation(Ticket $original): bool
    {
        return DB::transaction(function () use ($original): bool {
            $escalationId = $this->lockedLink($original);

            if (blank($escalationId)) {
                return false;
            }

            $this->unlink($original, $escalationId, $this->linkedRow($escalationId));

            return true;
        });
    }

    /*
     * Moves the escalation to another person on the escalating team. Refused
     * when it closed or changed hands after the dialog was opened, so two
     * people taking it over at once cannot silently overwrite each other.
     */
    public function handOver(Ticket $escalation, int|string $toUserId, int|string|null $expectedFromId): bool
    {
        $fromId = DB::transaction(function () use ($escalation, $toUserId, $expectedFromId): int|string|false {
            $locked = static::query()->withoutGlobalScopes()->whereKey($escalation->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->trashed() || $locked->isClosed || (string) $locked->submitter_id !== (string) $expectedFromId) {
                return false;
            }

            if ((string) $locked->submitter_id === (string) $toUserId) {
                return false;
            }

            $escalation->update(['submitter_id' => $toUserId]);
            $escalation->unsetRelation('submitter');

            $this->addActivity($escalation, ActivityType::HandedOver, ['from' => $locked->submitter_id, 'to' => $toUserId, 'recipient_notified' => false]);

            return $locked->submitter_id;
        });

        if ($fromId === false) {
            return false;
        }

        event(new TicketHandedOverEvent($escalation, auth()->user(), $fromId, $toUserId));

        return true;
    }

    /**
     * @param  array<int|string>  $selectedIds
     */
    public function syncOriginals(Ticket $escalation, array $selectedIds): bool
    {
        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));

        return DB::transaction(function () use ($escalation, $selectedIds): bool {
            // Nobody writes on a closed escalation, so its originals stay as they were.
            $current = static::query()->withoutGlobalScopes()->find($escalation->getKey());

            if ($current === null || $current->isClosed) {
                return false;
            }

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
                $this->unlink($locked[$id], $escalation->getKey(), $escalation);
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
        $query = static::query()->withoutGlobalScopes();

        return $query->whereNull($query->getModel()->getQualifiedDeletedAtColumn())
            ->where('linked_ticket_id', $escalationId);
    }

    protected function link(Ticket $original, Ticket $escalation, bool $started = false): void
    {
        $this->writeLink($original, $escalation->getKey());

        $this->addActivity(
            $original,
            $started ? ActivityType::Escalated : ActivityType::AddedToEscalation,
            ['escalation' => $escalation->getKey()],
        );
        $this->addActivity($escalation, ActivityType::OriginalAdded, ['original' => $original->getKey()]);
        $escalation->forgetIsEscalation();
    }

    protected function unlink(Ticket $original, int|string $escalationId, ?Ticket $escalation): void
    {
        $this->writeLink($original, null);

        $this->addActivity($original, ActivityType::RemovedFromEscalation, ['escalation' => $escalationId]);

        if ($escalation !== null) {
            $this->addActivity($escalation, ActivityType::OriginalRemoved, ['original' => $original->getKey()]);
            $escalation->forgetIsEscalation();
        }
    }

    /*
     * The row a link points at, deleted or not, read past host scopes.
     */
    protected function linkedRow(mixed $escalationId): ?Ticket
    {
        return blank($escalationId) ? null : static::query()->withoutGlobalScopes()->find($escalationId);
    }

    protected static function isOpen(?Ticket $escalation): bool
    {
        return $escalation !== null && ! $escalation->trashed() && $escalation->isOpen;
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
