<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\TicketPlugin;

trait CanBeAssigned
{
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveUserModelClass(),
            'assignee_id'
        );
    }
}
