<?php

namespace Padmission\Tickets\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketDispositionFactory;
use Padmission\Tickets\Models\Contracts\TicketDispositionInterface;
use Padmission\Tickets\Models\Observers\TicketDispositionObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

#[ObservedBy(TicketDispositionObserver::class)]
#[ScopedBy(CurrentPanelScope::class)]
#[UseFactory(TicketDispositionFactory::class)]
class TicketDisposition extends Model implements TicketDispositionInterface
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_dispositions';

    protected $guarded = ['id'];

    /**
     * @return Attribute<array,never>
     */
    protected function colorPalette(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Color::{$this->color},
        );
    }
}
