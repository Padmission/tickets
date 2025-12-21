<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Padmission\Tickets\Database\Factories\TicketLastSeenFactory;
use Padmission\Tickets\Models\Concerns\HasPanelAwareRelationships;
use Padmission\Tickets\TicketPlugin;

class TicketLastSeen extends Model
{
    use HasFactory;
    use HasPanelAwareRelationships;

    protected $table = 'ticket_last_seen';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'last_seen_activity_id',
        'last_notified_activity_id',
    ];

    protected static string $factory = TicketLastSeenFactory::class;

    /* Relations */

    /**
     * @return Relations\PanelAwareBelongsTo<Ticket, $this>
     */
    public function ticket(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'ticket'
        );
    }

    /**
     * @return Relations\PanelAwareBelongsTo<Model&Authenticatable, $this>
     */
    public function user(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveUserModelClass(),
            'user'
        );
    }
}
