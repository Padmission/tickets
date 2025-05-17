<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Enums\Turn;

#[UseFactory(TicketFactory::class)]
class Ticket extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'turn' => Turn::class,
        'closed_at' => 'datetime',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.status'));
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.priority'));
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.user'), 'assignee_id');
    }
}
