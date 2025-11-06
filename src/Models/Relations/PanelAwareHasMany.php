<?php

namespace Padmission\Tickets\Models\Relations;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class PanelAwareHasMany extends HasMany
{
    protected string $modelName;

    public function __construct($query, $parent, $foreignKey, $localKey, string $modelName)
    {
        parent::__construct($query, $parent, $foreignKey, $localKey);
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
        if (! $this->parent->exists) {
            return null;
        }

        if ($this->parent instanceof Ticket) {
            return $this->parent->panel;
        }

        if (property_exists($this->parent, 'ticket_id') && $this->parent->ticket_id) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $ticket = $ticketModel::withoutGlobalScopes()->find($this->parent->ticket_id);

            return $ticket?->panel;
        }

        return null;
    }
}
