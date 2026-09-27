<?php

namespace Padmission\Tickets\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

final readonly class ConversationState
{
    protected const HELP = 'padmission-tickets::tickets.resources.tickets.waiting_on_help';

    protected const MARKER = 'padmission-tickets::tickets.resources.tickets.escalation_marker';

    protected const MARKER_HELP = 'padmission-tickets::tickets.resources.tickets.escalation_marker_help';

    public function __construct(
        public Ticket $ticket,
        public ?string $waitingOn,
        public ?string $marker,
        public int $rank,
        public bool $isNew,
        public int|string|null $ownerId,
        public bool $escalationOpen,
        public bool $isEscalation,
    ) {}

    public static function fromRow(Ticket $row): self
    {
        return self::read($row, $row);
    }

    /*
     * The page's own record keeps the relations it already loaded, such as an
     * assignee found through another panel.
     */
    public static function for(Ticket $ticket): self
    {
        /** @var Ticket|null $row */
        $row = TicketResource::getEloquentQuery()
            ->withConversationState()
            ->whereKey($ticket->getKey())
            ->first();

        return self::read($ticket, $row);
    }

    protected static function read(Ticket $ticket, ?Ticket $row): self
    {
        $ownerId = $row?->getAttribute('conversation_owner_id');

        return new self(
            ticket: $ticket,
            waitingOn: $row?->getAttribute('conversation_waiting_on'),
            marker: $row?->getAttribute('conversation_marker'),
            rank: (int) ($row?->getAttribute('conversation_rank') ?? 2),
            isNew: (bool) $row?->getAttribute('conversation_is_new'),
            ownerId: is_numeric($ownerId) ? (int) $ownerId : $ownerId,
            escalationOpen: (bool) $row?->getAttribute('conversation_escalation_open'),
            isEscalation: (bool) $row?->getAttribute('conversation_is_escalation'),
        );
    }

    public function label(): ?string
    {
        return match ($this->waitingOn) {
            null, 'closed' => null,
            'you', 'you_on_hold', 'you_requester', 'you_owner' => __('padmission-tickets::tickets.resources.tickets.waiting_on.you'),
            'needs_assignment', 'requester', 'contact' => __("padmission-tickets::tickets.resources.tickets.waiting_on.{$this->waitingOn}"),
            'team' => $this->teamName() ?? __('padmission-tickets::tickets.resources.tickets.waiting_on.team'),
            'support' => Turn::Supporter->getLabel(),
            default => $this->colleagueName() ?? __('padmission-tickets::tickets.resources.tickets.waiting_on.colleague'),
        };
    }

    public function color(): string
    {
        return in_array($this->waitingOn, ['you', 'you_requester', 'you_owner', 'needs_assignment'], true) ? 'warning' : 'gray';
    }

    public function icon(): ?string
    {
        return match ($this->waitingOn) {
            'you_on_hold', 'colleague_on_hold' => 'heroicon-m-clock',
            'needs_assignment' => 'heroicon-m-user-plus',
            'team' => 'heroicon-m-building-office',
            default => null,
        };
    }

    public function tooltip(): ?string
    {
        $requester = $this->ticket->requesterName() ?? __('padmission-tickets::tickets.resources.tickets.waiting_on.requester');
        $colleague = $this->colleagueName();

        return match ($this->waitingOn) {
            null, 'closed' => null,
            'support' => Turn::Supporter->getDescription(),
            'you_requester' => Turn::User->getDescription(),
            'you_owner' => $this->teamName() === null
                ? __(self::HELP.'.you_to_team_unnamed')
                : __(self::HELP.'.you_to_team', ['team' => $this->teamName()]),
            'you', 'you_on_hold', 'needs_assignment', 'requester' => __(self::HELP.".{$this->waitingOn}", ['name' => $requester]),
            'colleague', 'colleague_on_hold' => __(self::HELP.".{$this->waitingOn}".($colleague === null ? '_unnamed' : ''), ['name' => $requester, 'colleague' => $colleague]),
            'owner_colleague' => TicketPlugin::teamText(self::HELP.'.owner_colleague'.($colleague === null ? '_unnamed' : ''), $this->teamName(), ['colleague' => $colleague]),
            'contact' => $this->contactHelp($requester),
            'team' => $this->assigneeName() === null
                ? TicketPlugin::teamText(self::HELP.'.team_unassigned', $this->teamName())
                : TicketPlugin::teamText(self::HELP.'.team', $this->teamName(), ['assignee' => $this->assigneeName()]),
            default => null,
        };
    }

    public function markerLabel(): ?string
    {
        $team = $this->escalationTeamName();

        return match ($this->marker) {
            'escalated' => __(self::MARKER.'.escalated'),
            'replied' => $this->ownerIsViewer() || $this->handlerName() === null
                ? TicketPlugin::teamText(self::MARKER.'.replied', $team)
                : TicketPlugin::teamText(self::MARKER.'.replied_to_other', $team, ['name' => $this->handlerName()]),
            'closed' => __(self::MARKER.'.closed'),
            default => null,
        };
    }

    public function markerColor(): string
    {
        return $this->marker === 'replied' && $this->ownerIsViewer() ? 'warning' : 'gray';
    }

    public function markerTooltip(): ?string
    {
        $team = $this->escalationTeamName();
        $escalation = $this->ticket->parentTicket;

        return match ($this->marker) {
            'escalated' => TicketPlugin::teamText(
                self::MARKER_HELP.'.escalated_waiting_'
                    .($escalation?->turn === Turn::User ? 'owner' : 'team')
                    .match (true) {
                        $this->ownerIsViewer() => '_you',
                        $this->handlerName() === null => '_unnamed',
                        default => '',
                    },
                $team,
                ['handler' => $this->handlerName()],
            ),
            'replied' => TicketPlugin::teamText(self::MARKER_HELP.'.replied', $team, [
                'name' => $this->ticket->requesterName() ?? __('padmission-tickets::tickets.resources.tickets.waiting_on.requester'),
            ]),
            'closed' => __(self::MARKER_HELP.'.closed', ['time' => $escalation?->closed_at?->diffForHumans()]),
            default => null,
        };
    }

    public function ownerIsViewer(): bool
    {
        return $this->ownerId !== null && $this->ownerId == Filament::auth()->id();
    }

    protected function handlerName(): ?string
    {
        return $this->name($this->ticket->parentTicket?->submitter);
    }

    protected function escalationTeamName(): ?string
    {
        $panel = $this->ticket->parentTicket?->panel;

        return $panel === null
            ? TicketPlugin::get()->getEscalationTargetName()
            : TicketPlugin::find($panel)?->getSupportTeamName();
    }

    protected function contactHelp(string $name): string
    {
        $organization = TicketPlugin::get()->describeTicketOrigin($this->ticket);

        return $organization === null
            ? __(self::HELP.'.contact_unnamed_org', ['name' => $name])
            : __(self::HELP.'.contact', ['name' => $name, 'organization' => $organization]);
    }

    protected function teamName(): ?string
    {
        return TicketPlugin::find($this->ticket->panel)?->getSupportTeamName();
    }

    /*
     * On an escalation the colleague is the one who handles it, not the
     * other team's assignee.
     */
    protected function colleagueName(): ?string
    {
        return $this->waitingOn === 'owner_colleague'
            ? $this->name($this->ticket->submitter)
            : $this->assigneeName();
    }

    protected function assigneeName(): ?string
    {
        return $this->name($this->ticket->assignee);
    }

    protected function name(?Model $user): ?string
    {
        return $user === null ? null : (new GetUserDisplayName)->forUser($user);
    }
}
