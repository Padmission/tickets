<?php

namespace Padmission\Tickets\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\CopilotTicketService;
use Padmission\Tickets\Services\TicketAuth;

class CopilotTicketPanel extends Component
{
    public string $view = 'list';

    public string $filter = 'open';

    public ?int $activeTicketId = null;

    public function mount(?int $initialTicketId = null): void
    {
        if (! $initialTicketId) {
            $this->announceShownTicket();

            return;
        }

        $this->selectTicket($initialTicketId);
    }

    public function render(): View
    {
        $user = $this->user();
        $activeTicket = $this->activeTicket($user);

        return view('padmission-tickets::livewire.copilot-ticket-panel', [
            'activeTicket' => $activeTicket,
            'tickets' => $this->tickets()->visibleTickets($user, $this->filter),
        ]);
    }

    public function showList(): void
    {
        $this->view = 'list';
        $this->activeTicketId = null;
        $this->resetValidation();
        $this->announceShownTicket();
    }

    public function showCreateForm(): void
    {
        $this->view = 'create';
        $this->activeTicketId = null;
        $this->resetValidation();
        $this->announceShownTicket();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['open', 'closed', 'all'], true) ? $filter : 'open';
        $this->showList();
    }

    public function selectTicket(int $ticketId): void
    {
        $ticket = $this->tickets()->findVisibleTicket($this->user(), $ticketId);

        $this->tickets()->markTicketSeen($this->user(), $ticket);

        $this->activeTicketId = $ticket->getKey();
        $this->view = 'detail';
        $this->resetValidation();
        $this->dispatch('padmission-copilot-ticket-seen');
        $this->announceShownTicket();
    }

    /*
     * The assistant mounts this pane again whenever its Tickets tab comes
     * back, with the ticket it last knew of, which is the one a deep link
     * opened unless it is told what the pane shows now, from the moment it
     * is mounted.
     */
    protected function announceShownTicket(): void
    {
        $this->dispatch('padmission-copilot-ticket-shown', ticketId: $this->activeTicketId);
    }

    #[On('padmission-ticket-created-from-copilot')]
    public function selectCreatedTicket(int $ticketId): void
    {
        $this->selectTicket($ticketId);
    }

    #[On('padmission-ticket-message-sent-from-copilot')]
    public function refreshTickets(): void
    {
        $this->resetValidation();
    }

    /*
     * Support may have closed it since the pane last drew its header.
     */
    public function resolveTicket(): void
    {
        $ticket = $this->requireActiveTicket();

        if ($ticket->isClosed) {
            Notification::make()
                ->warning()
                ->title(__('padmission-tickets::tickets.copilot.already_closed'))
                ->send();

            $this->selectTicket($ticket->getKey());

            return;
        }

        $this->tickets()->resolveTicket($this->user(), $ticket);

        $this->selectTicket($ticket->getKey());

        // The chat keeps its own state, so it shows the ticket closed now rather than at its next poll.
        $this->dispatch('ticket-chat-changed', ticketId: $ticket->getKey(), canReply: resolve(TicketAuth::class)->canReply($ticket, $this->user()));
    }

    protected function requireActiveTicket(): Ticket
    {
        if (! $this->activeTicketId) {
            abort(404);
        }

        return $this->tickets()->findVisibleTicket($this->user(), $this->activeTicketId);
    }

    protected function activeTicket(Authenticatable&Model $user): ?Ticket
    {
        if (! $this->activeTicketId || $this->view !== 'detail') {
            return null;
        }

        return $this->tickets()->findVisibleTicket($user, $this->activeTicketId);
    }

    protected function user(): Authenticatable&Model
    {
        $user = Auth::user();

        abort_unless($user instanceof Authenticatable && $user instanceof Model, 403);

        return $user;
    }

    protected function tickets(): CopilotTicketService
    {
        return app(CopilotTicketService::class);
    }
}
