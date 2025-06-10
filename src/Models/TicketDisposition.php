<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Padmission\Tickets\Database\Factories\TicketDispositionFactory;

#[UseFactory(TicketDispositionFactory::class)]
class TicketDisposition extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_dispositions';

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->panel ??= Filament::getCurrentPanel()->getId();
        });

        static::saved(function ($model) {
            Cache::forget(static::getPanelCacheKey());
        });

        static::deleted(function ($model) {
            Cache::forget(static::getPanelCacheKey());
        });

        static::addGlobalScope('panel', function (Builder $builder) {
            $panel = Filament::getCurrentPanel();
            if ($panel) {
                $builder->where('panel', $panel->getId());
            }
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

    public static function getPanelCacheKey(?Panel $panel = null) : string {
        if (!$panel) {
            $panel = Filament::getCurrentPanel();
        }
        return __METHOD__.'::'.($panel ? $panel->getId() : '');
    }
}
