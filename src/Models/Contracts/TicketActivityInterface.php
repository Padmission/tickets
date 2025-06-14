<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface TicketActivityInterface
{
    public function ticket(): BelongsTo;

    public function user(): BelongsTo;
}
