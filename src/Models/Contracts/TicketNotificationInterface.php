<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface TicketNotificationInterface
{
    public function ticket(): BelongsTo;
    public function user(): BelongsTo;
}
