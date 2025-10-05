<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;

trait ManagesPriority
{
    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketPriority::class)
        )
            ->withTrashed()
            ->withoutGlobalScope(CurrentPanelScope::class);
    }
}
