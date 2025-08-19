<?php

namespace Padmission\Tickets\Models\Concerns;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasColor
{
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
