<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface CanBeAssignedTickets
{
    public function assignedTickets(): HasMany;
}
