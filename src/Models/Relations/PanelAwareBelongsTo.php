<?php

namespace Padmission\Tickets\Models\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class PanelAwareBelongsTo extends BelongsTo
{
    protected string $modelName;

    public function __construct($query, $child, $foreignKey, $ownerKey, $relationName, string $modelName)
    {
        parent::__construct($query, $child, $foreignKey, $ownerKey, $relationName);
        $this->modelName = $modelName;
    }

    public function getResults()
    {
        $this->applyPanelModifier();

        return parent::getResults();
    }

    public function addConstraints()
    {
        parent::addConstraints();
        $this->applyPanelModifier();
    }

    protected function applyPanelModifier(): void
    {
        if (! TicketPlugin::get()->getRelationshipScopeModifier()) {
            return;
        }

        $panelId = $this->resolvePanelId();
        $modifier = TicketPlugin::get($panelId)->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $this->query, 'model' => $this->modelName]);
        }
    }

    protected function resolvePanelId(): ?string
    {
        if (! $this->child->exists) {
            return null;
        }

        if ($this->child instanceof Ticket) {
            return $this->child->panel;
        }

        if (property_exists($this->child, 'ticket_id') && $this->child->ticket_id) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $ticket = $ticketModel::withoutGlobalScopes()->find($this->child->ticket_id);

            return $ticket?->panel;
        }

        return null;
    }
}
