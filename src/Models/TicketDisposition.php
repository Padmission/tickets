<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketDispositionFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketDispositionObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

#[ObservedBy(TicketDispositionObserver::class)]
#[ScopedBy(CurrentPanelScope::class)]
#[UseFactory(TicketDispositionFactory::class)]
class TicketDisposition extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_dispositions';

    protected $guarded = ['id'];
}
