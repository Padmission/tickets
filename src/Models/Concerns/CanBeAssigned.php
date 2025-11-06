<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\TicketPlugin;

trait CanBeAssigned
{
    public function assignee(): BelongsTo
    {
        $relation = $this->belongsTo(
            TicketPlugin::resolveUserModelClass(),
            'assignee_id'
        );

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'assignee']);
        }

        return $relation;
    }
}
