<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

trait ManagesStatus
{
    public function status(): BelongsTo
    {
        $relation = $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketStatus::class)
        )
            ->withTrashed()
            ->withoutGlobalScope(CurrentPanelScope::class);

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'status']);
        }

        return $relation;
    }
}
