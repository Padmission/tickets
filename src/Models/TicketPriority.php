<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketPriorityFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketPriorityObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

class TicketPriority extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_priorities';

    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::observe(TicketPriorityObserver::class);
        static::addGlobalScope(new CurrentPanelScope);
    }

    protected static function newFactory(): TicketPriorityFactory
    {
        return TicketPriorityFactory::new();
    }
}
