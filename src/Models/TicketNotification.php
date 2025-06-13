<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Database\Factories\TicketNotificationFactory;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\TicketPlugin;

#[UseFactory(TicketNotificationFactory::class)]
class TicketNotification extends Model
{
    use HasFactory;

    protected $table = 'ticket_notifications';

    protected $guarded = [];

    /* Relations */

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketPlugin::resolveModelClass(Ticket::class));
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TicketPlugin::resolveModelClass(Authenticatable::class));
    }
}
