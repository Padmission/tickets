<?php

namespace Padmission\Tickets\Enums;

use Filament\Support\Contracts\HasLabel;

enum Disposition: string implements HasLabel
{
    case Resolved = 'resolved';
    case Abandoned = 'abandoned';

    case Unresolvable = 'unresolvable';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Resolved => __('padmission-tickets::tickets.enums.disposition.resolved'),
            self::Abandoned => __('padmission-tickets::tickets.enums.disposition.abandoned'),
            self::Unresolvable => __('padmission-tickets::tickets.enums.disposition.unresolvable'),
        };
    }
}
