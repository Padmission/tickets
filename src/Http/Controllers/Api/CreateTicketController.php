<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Actions\GetDefaultPriorityForPanel;
use Padmission\Tickets\Actions\GetDefaultStatusForPanel;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Http\DataMappers\TicketMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;
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

        $defaultStatus = resolve(GetDefaultStatusForPanel::class)($targetPanelId);
        $defaultPriority = resolve(GetDefaultPriorityForPanel::class)($targetPanelId);

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
        $currentPanel = Filament::getCurrentOrDefaultPanel();
        $targetPanelId = TicketPlugin::get()->getTargetPanelId()
            ?? $currentPanel?->getId();

        if (! $targetPanelId) {
            throw new RuntimeException('No target panel configured for ticket creation.');
        }

        return $targetPanelId;
    }

    private function verifyPanelExists(string $panelId): void
    {
        $panel = Filament::getPanel($panelId);
        if ($panel->getId() !== $panelId) {
            throw new RuntimeException(sprintf(
                'Panel "%s" is not registered in Filament.',
                $panelId
            ));
        }
    }

    private function createTicket(
        Request $request,
        string $subject,
        string $targetPanelId,
        TicketStatus $status,
        TicketPriority $priority
    ): Ticket {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $currentPanel = Filament::getCurrentOrDefaultPanel();

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
