<?php

namespace Padmission\Tickets\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Contracts\Database\Query\Builder;

class CurrentPanelScope
{
    public function __invoke($query): Builder
    {
        return $query->where('panel', Filament::getCurrentPanel()->getId());
    }
}
