<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketStatusFactory;
use Padmission\Tickets\Models\Concerns\HasColor;
use Padmission\Tickets\Models\Observers\TicketStatusObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

class TicketStatus extends Model
{
    use HasColor;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_statuses';

    protected $guarded = ['id'];

    protected static string $factory = TicketStatusFactory::class;

    protected static function boot(): void
    {
        parent::boot();

        static::observe(TicketStatusObserver::class);
        static::addGlobalScope(new CurrentPanelScope);
    }

    public static function getOpenStatuses(): Collection
    {
        return static::query()
            ->where(fn ($query) => $query
                ->whereNull('seed_key')
                ->orWhere('seed_key', '!=', 'closed'))
            ->orderBy('order')
            ->get();
    }

    public static function getClosedStatus(): static
    {
        /** @var static */
        return static::query()
            ->where('seed_key', 'closed')
            ->firstOrFail();
    }

    public static function findClosedStatus(): ?static
    {
        /** @var static|null */
        return static::query()
            ->where('seed_key', 'closed')
            ->first();
    }

    public static function getAiInProgressStatus(): ?static
    {
        /** @var static|null */
        return static::query()
            ->where('seed_key', 'ai_in_progress')
            ->first();
    }
}
