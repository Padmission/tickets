<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Http\DataMappers\TicketMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use Tiptap\Editor;
use function sprintf;

class CreateTicketController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $this->authorizeTicketCreation();

        $subject = $this->validateAndSanitizeSubject($request);

        $targetPanelId = $this->resolveTargetPanelId();
        $this->verifyPanelExists($targetPanelId);

        $defaultStatus = $this->getDefaultStatusForPanel($targetPanelId);
        $defaultPriority = $this->getDefaultPriorityForPanel($targetPanelId);

        $ticket = $this->createTicket(
            $request,
            $subject,
            $targetPanelId,
            $defaultStatus,
            $defaultPriority
        );

        return TicketMapper::map($ticket);
    }

    private function authorizeTicketCreation(): void
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $this->authorize('create', $ticketModel);
    }

    private function validateAndSanitizeSubject(Request $request): string
    {
        $request->validate([
            'subject' => 'required|string|max:255',
        ]);

        return (new Editor)
            ->setContent($request->input('subject'))
            ->getText();
    }

    private function resolveTargetPanelId(): string
    {
        $currentPanel = Filament::getCurrentPanel();
        $targetPanelId = TicketPlugin::get()->getTargetPanelId()
            ?? $currentPanel?->getId();

        if (! $targetPanelId) {
            throw new \RuntimeException('No target panel configured for ticket creation.');
        }

        return $targetPanelId;
    }

    private function verifyPanelExists(string $panelId): void
    {
        $panel = Filament::getPanel($panelId);
        if ($panel->getId() !== $panelId) {
            throw new \RuntimeException(sprintf(
                'Panel "%s" is not registered in Filament.',
                $panelId
            ));
        }
    }

    private function getDefaultStatusForPanel(string $panelId): TicketStatus
    {
        $defaultStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::query()
            ->where('panel', $panelId)
            ->orderBy('order', 'asc')
            ->first();

        if (! $defaultStatus) {
            throw new \RuntimeException(sprintf(
                'No ticket status found for panel "%s". Please configure ticket statuses for this panel.',
                $panelId
            ));
        }

        return $defaultStatus;
    }

    private function getDefaultPriorityForPanel(string $panelId): TicketPriority
    {
        $defaultPriority = TicketPlugin::resolveModelClass(TicketPriority::class)::query()
            ->where('panel', $panelId)
            ->orderBy('order', 'asc')
            ->first();

        if (! $defaultPriority) {
            throw new \RuntimeException(sprintf(
                'No ticket priority found for panel "%s". Please configure ticket priorities for this panel.',
                $panelId
            ));
        }

        return $defaultPriority;
    }

    private function createTicket(
        Request $request,
        string $subject,
        string $targetPanelId,
        TicketStatus $status,
        TicketPriority $priority
    ): Ticket {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $currentPanel = Filament::getCurrentPanel();

        return $ticketModel::create([
            'panel' => $targetPanelId,
            'source_panel' => $currentPanel?->getId(),
            'subject' => $subject,
            'submitter_id' => $request->user()->id,
            'turn' => Turn::User,
            'status_id' => $status->id,
            'priority_id' => $priority->id,
            'data' => [
                'url' => request()->input('url'),
                'ip_address' => request()->ip(),
            ],
        ]);
    }
}
