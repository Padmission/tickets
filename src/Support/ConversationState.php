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

    public function __construct(
        public Ticket $ticket,
        public ?string $waitingOn,
        public ?string $marker,
        public int $rank,
        public bool $isNew,
        public int|string|null $ownerId,
        public bool $escalationOpen,
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
        );
    }

    public function label(): ?string
    {
        return match ($this->waitingOn) {
            null, 'closed' => null,
            'you', 'you_on_hold' => __('padmission-tickets::tickets.resources.tickets.waiting_on.you'),
            'needs_assignment', 'requester', 'contact' => __("padmission-tickets::tickets.resources.tickets.waiting_on.{$this->waitingOn}"),
            'team' => $this->teamName() ?? __('padmission-tickets::tickets.resources.tickets.waiting_on.team'),
            'support' => Turn::Supporter->getLabel(),
            default => $this->colleagueName(),
        };
    }

    public function color(): string
    {
        return in_array($this->waitingOn, ['you', 'needs_assignment'], true) ? 'warning' : 'gray';
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

        return match ($this->waitingOn) {
            null, 'closed' => null,
            'support' => Turn::Supporter->getDescription(),
            'you' => match (true) {
                $this->isEscalationRow() => $this->teamHelp('you_to_team'),
                $this->ticket->turn === Turn::User => Turn::User->getDescription(),
                default => __(self::HELP.'.you', ['name' => $requester]),
            },
            'you_on_hold', 'needs_assignment', 'requester' => __(self::HELP.".{$this->waitingOn}", ['name' => $requester]),
            'colleague_on_hold' => __(self::HELP.'.colleague_on_hold', ['name' => $requester, 'colleague' => $this->colleagueName()]),
            'colleague' => $this->isEscalationRow()
                ? TicketPlugin::teamText(self::HELP.'.owner_colleague', $this->teamName(), ['colleague' => $this->colleagueName()])
                : __(self::HELP.'.colleague', ['name' => $requester, 'colleague' => $this->colleagueName()]),
            'contact' => $this->contactHelp($requester),
            'team' => $this->assigneeName() === null
                ? TicketPlugin::teamText(self::HELP.'.team_unassigned', $this->teamName())
                : TicketPlugin::teamText(self::HELP.'.team', $this->teamName(), ['assignee' => $this->assigneeName()]),
            default => null,
        };
    }

    protected function contactHelp(string $name): string
    {
        $organization = TicketPlugin::get()->describeTicketOrigin($this->ticket);

        return $organization === null
            ? __(self::HELP.'.contact_unnamed_org', ['name' => $name])
            : __(self::HELP.'.contact', ['name' => $name, 'organization' => $organization]);
    }

    protected function teamHelp(string $key): string
    {
        $team = $this->teamName();

        return $team === null
            ? __(self::HELP.".{$key}_unnamed")
            : __(self::HELP.".{$key}", ['team' => $team]);
    }

    protected function isEscalationRow(): bool
    {
        return $this->ticket->panel !== Filament::getCurrentOrDefaultPanel()->getId();
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
        return $this->isEscalationRow()
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
