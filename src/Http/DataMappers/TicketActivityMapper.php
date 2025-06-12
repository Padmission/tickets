<?php

namespace Padmission\Tickets\Http\DataMappers;

use Padmission\Tickets\Models\TicketActivity;

class TicketActivityMapper
{
    /**
     * @param  TicketActivity  $activity
     */
    public static function map($activity): array
    {
        return [
            'id' => $activity->id,
            'side' => $activity->side,
            'user_name' => $activity->userName,
            'content' => $activity->content,
            'created_at' => $activity->created_at,
        ];
    }
}
