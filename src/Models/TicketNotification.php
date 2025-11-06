<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Database\Factories\TicketNotificationFactory;
use Padmission\Tickets\Models\Concerns\HasPanelAwareRelationships;
use Padmission\Tickets\TicketPlugin;

#[UseFactory(TicketNotificationFactory::class)]
class TicketNotification extends Model
{
    use HasFactory;
    use HasPanelAwareRelationships;

    protected $table = 'ticket_notifications';

    protected $guarded = [];

    /* Relations */

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'ticket'
        );
    }

    /**
     * @return BelongsTo<Model&Authenticatable, $this>
     */
    public function user(): BelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveUserModelClass(),
            'user'
        );
    }
}
