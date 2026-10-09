<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketPriorityFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Concerns\IsTicketLookup;
use Padmission\Tickets\Models\Observers\TicketPriorityObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

#[ObservedBy(TicketPriorityObserver::class)]
class TicketPriority extends Model
{
    use HasColor;
    use HasFactory;
    use IsTicketLookup;
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
        /** @var ?static */
        return static::optionsForTicket($ticket)->orderBy('order')->first();
    }

    protected static function lookupName(): string
    {
        return 'priority';
    }
}
