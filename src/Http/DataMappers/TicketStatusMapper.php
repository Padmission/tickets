<?php

namespace Padmission\Tickets\Http\DataMappers;

use Padmission\Tickets\Models\TicketStatus;

class TicketStatusMapper
{
    public static function map(TicketStatus $status): array
    {
        return [
            'id' => $status->id,
            'display_name' => $status->display_name,
            'color' => $status->color_palette[500],
        ];
    }
}
