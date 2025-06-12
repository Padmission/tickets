<?php

namespace Padmission\Tickets\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CurrentPanelScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $panel = Filament::getCurrentPanel();
        $panel = $panel ? $panel->getId() : $model->panel;
        if ($panel) {
            $builder->where('panel', $panel);
        }
    }

    public function __invoke($query): Builder
    {
        return $query->where('panel', Filament::getCurrentPanel()->getId());
    }
}
