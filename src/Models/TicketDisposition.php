<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketDispositionFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketDispositionObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

class TicketDisposition extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_dispositions';

    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::observe(TicketDispositionObserver::class);
        static::addGlobalScope(new CurrentPanelScope);
    }

    protected static function newFactory(): TicketDispositionFactory
    {
        return TicketDispositionFactory::new();
    }
}
