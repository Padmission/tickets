<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketStatusFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketStatusObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Support\TicketOrganization;
use Padmission\Tickets\TicketPlugin;

#[ObservedBy(TicketStatusObserver::class)]
class TicketStatus extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_statuses';

    protected $guarded = ['id'];

    protected static string $factory = TicketStatusFactory::class;

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentPanelScope);
    }

    public static function getOpenStatuses(): Collection
    {
        return self::query()
            ->tap(new CurrentPanelScope)
            ->orderBy('order')
            ->get()
            ->tap(fn ($collection) => $collection->pop());
    }

    public static function getClosedStatus(): static
    {
        /** @var static */
        return self::query()
            ->tap(new CurrentPanelScope)
            ->orderBy('order', 'desc')
            ->firstOrFail();
    }

    /*
     * The closed status of the ticket's own panel and tenant, which differs
     * from the current panel's when staff close a ticket from elsewhere.
     */
    public static function getClosedStatusFor(Ticket $ticket): ?static
    {
        /** @var ?static */
        return static::statusesFor($ticket)->orderBy('order', 'desc')->first();
    }

    /*
     * The first status of the ticket's own panel and tenant, which a
     * reopened ticket takes, as a new ticket takes its panel's first.
     */
    public static function getOpenStatusFor(Ticket $ticket): ?static
    {
        /** @var ?static */
        return static::statusesFor($ticket)->orderBy('order')->first();
    }

    /**
     * @return Builder<static>
     */
    protected static function statusesFor(Ticket $ticket): Builder
    {
        $query = static::query()
            ->withoutGlobalScope(CurrentPanelScope::class)
            ->where('panel', $ticket->panel);

        if (TicketOrganization::shouldScope($ticket)) {
            $query->where('tenant_id', $ticket->getAttribute('tenant_id'));
        }

        $modifier = TicketPlugin::find($ticket->panel)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'status']);
        }

        return $query;
    }
}
