<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Database\Factories\PriorityFactory;

#[UseFactory(PriorityFactory::class)]
class Priority extends Model
{
    use HasFactory;

    protected $table = 'ticket_priorities';

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
}
