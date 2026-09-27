<?php

namespace Padmission\Tickets\Support;

use Filament\Facades\Filament;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\TicketPlugin;

final readonly class ConversationViewer
{
    /**
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, string>  $parentPanelIds
     */
    public function __construct(
        public int|string|null $userId,
        public array $assigneeIds,
        public bool $isSupporter,
        public string $panelId,
        public bool $receivesEscalations,
        public array $parentPanelIds,
    ) {}

    public static function current(): self
    {
        return self::resolve(Filament::getCurrentOrDefaultPanel()->getId(), Filament::auth()->id());
    }

    protected static function resolve(string $panelId, int|string|null $userId): self
    {
        return once(function () use ($panelId, $userId): self {
            $plugin = TicketPlugin::get($panelId);

            return new self(
                userId: $userId,
                assigneeIds: $userId === null ? [] : $plugin->getCurrentUserAssigneeIds(),
                isSupporter: TicketResource::currentUserIsSupporter($userId),
                panelId: $panelId,
                receivesEscalations: count($plugin->getLinkedTicketChildPanels()) > 0,
                parentPanelIds: array_keys($plugin->getLinkedTicketParentPanels()),
            );
        });
    }
}
