<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Padmission\Tickets\Actions\GetDefaultPriorityForPanel;
use Padmission\Tickets\Actions\GetDefaultStatusForPanel;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Http\DataMappers\TicketMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Rules\PlainText;
use Padmission\Tickets\Rules\SafeUrl;
use Padmission\Tickets\Services\ApiTicketResolver;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Services\TicketReopening;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

use function sprintf;

class CreateTicketController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $this->authorizeTicketCreation();

        $subject = $this->validateAndSanitizeSubject($request);
        $request->validate(['url' => ['nullable', 'string', 'max:2048', new SafeUrl]]);
        $followedUp = $this->followedUpTicket($request);

        $targetPanelId = $this->resolveTargetPanelId();
        $this->verifyPanelExists($targetPanelId);

        // Writing a new ticket is writing in the chat, and a follow-up writes on the ticket it follows.
        resolve(TicketAuth::class)->refuseDisabledNewTicket($targetPanelId, $request->user());

        if ($followedUp !== null) {
            resolve(TicketAuth::class)->refuseDisabledReply($followedUp, $request->user());
        }

        $user = $request->user();
        $tenantId = $followedUp?->getAttribute('tenant_id')
            ?? ($user instanceof Model ? $user->getAttribute('tenant_id') : null);
        $defaultStatus = resolve(GetDefaultStatusForPanel::class)($targetPanelId, $tenantId);
        $defaultPriority = resolve(GetDefaultPriorityForPanel::class)($targetPanelId, $tenantId);

        $ticket = $this->createTicket(
            $request,
            $subject,
            $targetPanelId,
            $defaultStatus,
            $defaultPriority
        );

        if ($followedUp !== null) {
            $ticket->addTicketActivity(ActivityType::FollowsUp, ActivitySender::System, $request->user()->getAuthIdentifier(), ['ticket' => $followedUp->getKey()]);
        }

        return TicketMapper::map($ticket);
    }

    /*
     * A requester may follow up only on a closed ticket of their own, as the
     * closed ticket's reply dialog offers.
     */
    private function followedUpTicket(Request $request): ?Ticket
    {
        if (! $request->filled('follows_up')) {
            return null;
        }

        $request->validate(['follows_up' => ['integer']]);

        $ticket = resolve(ApiTicketResolver::class)->resolve($request->integer('follows_up'), $request->user());

        abort_unless(in_array(TicketReopening::NEW_TICKET, resolve(TicketReopening::class)->choicesFor($ticket, $request->user()), true), 403);

        return $ticket;
    }

    private function authorizeTicketCreation(): void
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $this->authorize('create', $ticketModel);
    }

    private function validateAndSanitizeSubject(Request $request): string
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        /*
         * The subject is plain text, never parsed as HTML: Tiptap's getText()
         * returned it HTML-escaped and dropped whatever followed a "<". Older
         * widget builds send it still escaped, so entities are read once, and
         * markup is refused in what they spell out.
         */
        $subject = Str::squish(html_entity_decode((string) $request->input('subject'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        Validator::make(['subject' => $subject], ['subject' => [new PlainText]])->validate();

        return $subject;
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
