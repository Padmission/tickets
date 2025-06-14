<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketDispositionFactory;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;

#[UseFactory(TicketDispositionFactory::class)]
class TicketDisposition extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ticket_dispositions';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope(CurrentPanelScope::class);

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
}
