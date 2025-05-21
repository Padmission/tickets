<?php

namespace Padmission\Tickets\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Turn: string implements HasIcon, HasLabel
{
    case User = 'user';

    case Supporter = 'supporter';

    public function getIcon(): string
    {
        return match ($this) {
            self::User => 'heroicon-o-user',
            self::Supporter => 'heroicon-o-wrench',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::User => __('padmission-tickets::tickets.enums.turn.user'),
            self::Supporter => __('padmission-tickets::tickets.enums.turn.supporter'),
        };
    }
}
