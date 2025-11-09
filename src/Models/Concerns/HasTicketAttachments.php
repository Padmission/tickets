<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\TicketPlugin;

trait HasTicketAttachments
{
    /**
     * @return HasMany<TicketAttachment,$this>
     */
    public function attachments(): HasMany
    {
        $foreignKey = $this->getTable() === 'tickets' ? 'ticket_id' : 'activity_id';

        $relation = $this->hasMany(
            TicketPlugin::resolveModelClass(TicketAttachment::class),
            foreignKey: $foreignKey,
        );

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'attachments']);
        }

        return $relation;
    }
}
