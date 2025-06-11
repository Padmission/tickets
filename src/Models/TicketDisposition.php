<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
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
            $panel = $model->panel ? $model->panel : Filament::getCurrentPanel()?->getId();
            $cacheKey = 'TicketDisposition::'.($panel ? $panel : '');
            Cache::forget($cacheKey);
        });

        static::deleted(function ($model) {
            $panel = $model->panel ? $model->panel : Filament::getCurrentPanel()?->getId();
            $cacheKey = 'TicketDisposition::'.($panel ? $panel : '');
            Cache::forget($cacheKey);
        });

        static::addGlobalScope('panel', function (Builder $builder) {
            $panel = Filament::getCurrentPanel();
            if ($panel) {
                $builder->where('panel', $panel->getId());
            }
        });
    }
}
