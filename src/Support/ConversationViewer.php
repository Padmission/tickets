<?php

namespace Padmission\Tickets\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\TicketPlugin;

final readonly class ConversationViewer
{
    /**
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, string>  $parentPanelIds
     * @param  array<int, int|string>  $supporterPool  values of $supporterMatchColumn
     */
    public function __construct(
        public int|string|null $userId,
        public array $assigneeIds,
        public bool $isSupporter,
        public string $panelId,
        public bool $receivesEscalations,
        public array $parentPanelIds,
        public string $supporterMatchColumn = 'id',
        public array $supporterPool = [],
    ) {}

    public static function current(): self
    {
        return self::resolve(Filament::getCurrentOrDefaultPanel()->getId(), Filament::auth()->id());
    }

    protected static function resolve(string $panelId, int|string|null $userId): self
    {
        return once(function () use ($panelId, $userId): self {
            $plugin = TicketPlugin::get($panelId);
            $column = $plugin->getSupporterMatchColumn();
            $pool = static::supporterPool($plugin, $column);
            $user = Filament::auth()->user();

            return new self(
                userId: $userId,
                assigneeIds: $userId === null ? [] : $plugin->getCurrentUserAssigneeIds(),
                isSupporter: $user instanceof Model
                    && $user->getKey() == $userId
                    && in_array($user->getAttribute($column), $pool, false),
                panelId: $panelId,
                receivesEscalations: count($plugin->getLinkedTicketChildPanels()) > 0,
                parentPanelIds: array_keys($plugin->getLinkedTicketParentPanels()),
                supporterMatchColumn: $column,
                supporterPool: $pool,
            );
        });
    }

    /**
     * Resolved here, once, because a host's pool query can be costly to run
     * as a correlated subquery on every row.
     *
     * @return array<int, int|string>
     */
    protected static function supporterPool(TicketPlugin $plugin, string $column): array
    {
        $query = $plugin->getAllSupportersQuery();

        if ($query === null) {
            return [];
        }

        /** @var Builder<Model> $pool */
        $pool = app()->call($query);

        return $pool
            ->pluck($pool->qualifyColumn($column))
            ->filter(fn (mixed $value): bool => filled($value))
            ->unique()
            ->values()
            ->all();
    }
}
