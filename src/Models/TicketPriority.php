<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketPriorityFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketPriorityObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Support\TicketOrganization;
use Padmission\Tickets\TicketPlugin;

#[ObservedBy(TicketPriorityObserver::class)]
class TicketPriority extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_priorities';

    protected $guarded = ['id'];

    protected static string $factory = TicketPriorityFactory::class;

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentPanelScope);
    }

    /*
     * The first priority of the ticket's own panel and tenant, for a ticket
     * opened where neither is the viewer's own, as TicketStatus finds its
     * open status.
     */
    public static function getDefaultFor(Ticket $ticket): ?static
    {
        $query = static::query()
            ->withoutGlobalScope(CurrentPanelScope::class)
            ->where('panel', $ticket->panel);

        if (TicketOrganization::shouldScope($ticket)) {
            $query->where('tenant_id', $ticket->getAttribute('tenant_id'));
        }

        $modifier = TicketPlugin::find($ticket->panel)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'priority']);
        }

        /** @var ?static */
        return $query->orderBy('order')->first();
    }
}
