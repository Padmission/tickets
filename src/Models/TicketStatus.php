<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketStatusFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketStatusObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

#[ObservedBy(TicketStatusObserver::class)]
class TicketStatus extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_statuses';

    protected $guarded = ['id'];

    protected static string $factory = TicketStatusFactory::class;

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentPanelScope);
    }

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
        /** @var static */
        return self::query()
            ->tap(new CurrentPanelScope)
            ->orderBy('order', 'DESC')
            ->firstOrFail();
    }
}
