<?php

namespace Padmission\Tickets\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CurrentPanelScope implements Scope
{
    public function __invoke($query): Builder
    {
        return $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId());
    }

    public function apply(Builder $builder, Model $model)
    {
        $this($builder);
    }
}
