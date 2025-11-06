<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Database\Factories\TicketNotificationFactory;
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
        $relation = $this->belongsTo(TicketPlugin::resolveModelClass(Ticket::class));

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'ticket']);
        }

        return $relation;
    }

    /**
     * @return BelongsTo<Model&Authenticatable, $this>
     */
    public function user(): BelongsTo
    {
        $relation = $this->belongsTo(
            TicketPlugin::resolveUserModelClass()
        );

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'user']);
        }

        return $relation;
    }
}
