<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Support\Str;
use Padmission\Tickets\Models\Relations\PanelAwareBelongsTo;
use Padmission\Tickets\Models\Relations\PanelAwareHasMany;

trait HasPanelAwareRelationships
{
    /**
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @return PanelAwareBelongsTo<TRelatedModel, $this>
     *
     * @phpstan-return PanelAwareBelongsTo<TRelatedModel, $this>
     */
    protected function panelAwareBelongsTo(string $related, string $modelName, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): PanelAwareBelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        /** @var TRelatedModel $instance */
        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_'.$instance->getKeyName();
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        /** @phpstan-ignore return.type */
        return new PanelAwareBelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey,
            $relation,
            $modelName
        );
    }

    /**
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @return PanelAwareHasMany<TRelatedModel, $this>
     *
     * @phpstan-return PanelAwareHasMany<TRelatedModel, $this>
     */
    protected function panelAwareHasMany(string $related, string $modelName, ?string $foreignKey = null, ?string $localKey = null): PanelAwareHasMany
    {
        /** @var TRelatedModel $instance */
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        /** @phpstan-ignore return.type */
        return new PanelAwareHasMany(
            $instance->newQuery(),
            $this,
            $instance->getTable().'.'.$foreignKey,
            $localKey,
            $modelName
        );
    }
}
