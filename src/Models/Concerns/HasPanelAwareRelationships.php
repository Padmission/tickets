<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Support\Str;
use Padmission\Tickets\Models\Relations\PanelAwareBelongsTo;
use Padmission\Tickets\Models\Relations\PanelAwareHasMany;

trait HasPanelAwareRelationships
{
    protected function panelAwareBelongsTo(string $related, string $modelName, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): PanelAwareBelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_'.$instance->getKeyName();
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new PanelAwareBelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey,
            $relation,
            $modelName
        );
    }

    protected function panelAwareHasMany(string $related, string $modelName, ?string $foreignKey = null, ?string $localKey = null): PanelAwareHasMany
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return new PanelAwareHasMany(
            $instance->newQuery(),
            $this,
            $instance->getTable().'.'.$foreignKey,
            $localKey,
            $modelName
        );
    }
}
