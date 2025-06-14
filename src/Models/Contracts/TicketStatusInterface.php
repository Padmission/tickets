<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface TicketStatusInterface
{
    public static function getOpenStatuses(): Collection;
    public static function getClosedStatus(): static;
}
