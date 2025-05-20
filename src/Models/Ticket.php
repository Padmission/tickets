<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\TicketPlugin;

#[UseFactory(TicketFactory::class)]
class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'turn' => Turn::class,
        'closed_at' => 'datetime',
    ];

    public function status(): Builder
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Status::class)
        )->withTrashed();
    }

    public function priority(): Builder
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Priority::class)
        )->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'assignee_id'
        );
    }
}
