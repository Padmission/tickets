<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketStatusFactory;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

#[UseFactory(TicketStatusFactory::class)]
class TicketStatus extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_statuses';

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->panel ??= Filament::getCurrentPanel()->getId();
        });
    }

    /**
     * @return Attribute<array,never>
     */
    protected function colorPalette(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Color::{$this->color},
        );
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
        return self::query()
            ->tap(new CurrentPanelScope)
            ->orderBy('order', 'DESC')
            ->first();
    }
}
