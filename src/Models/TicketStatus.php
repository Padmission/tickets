<?php

namespace Padmission\Tickets\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketStatusFactory;
use Padmission\Tickets\Models\Contracts\TicketStatusInterface;
use Padmission\Tickets\Models\Observers\TicketStatusObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;


/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
#[ObservedBy([TicketStatusObserver::class])]
#[ScopedBy([CurrentPanelScope::class])]
#[UseFactory(TicketStatusFactory::class)]
class TicketStatus extends Model implements TicketStatusInterface
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_statuses';

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

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getOpenStatuses(): Collection
    {
        return self::query()
            ->tap(new CurrentPanelScope)
            ->orderBy('order')
            ->get()
            ->tap(fn ($collection) => $collection->pop());
    }

    public static function getClosedStatus(): static
    {
        return self::query()
            ->tap(new CurrentPanelScope)
            ->orderBy('order', 'DESC')
            ->first();
    }
}
