<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;

class TicketUrlService
{
    public function getActionUrl(Ticket $ticket): string
    {
        $data = (array) $ticket->data;
        $url = $data['url'] ?? url('/');

        return $url.'#'.'ticket-'.$ticket->id;
    }

    public function getActionUrlFor(Ticket $ticket, Model $recipient): string
    {
        return $this->workPageUrl($ticket, $recipient) ?? $this->getActionUrl($ticket);
    }

    /*
     * Built from the panel's URL, since a queue worker may not register the
     * resource's plugin in every panel.
     */
    public function workPageUrl(Ticket $ticket, Model $recipient): ?string
    {
        if ((string) $recipient->getKey() === (string) $ticket->submitter_id) {
            return $ticket->isEscalation() ? $this->escalationUrl($ticket) : null;
        }

        // Whoever it was handed away from can no longer open it.
        if ($ticket->isEscalation() && Gate::forUser($recipient)->denies('view', $ticket)) {
            return $this->unviewableEscalationUrl($ticket);
        }

        return $this->viewUrl($ticket->panel, $ticket->getKey());
    }

    public function escalationUrl(Ticket $escalation): ?string
    {
        $originals = EscalationSummary::originalsOf($escalation);
        $original = $originals->whereNull('closed_at')->first() ?? $originals->first();

        return $this->viewUrl(
            $escalation->escalationSourcePanel(),
            $escalation->getKey(),
            $original === null ? [] : ['linked' => $original->getKey()],
        );
    }

    public function originalUrl(Ticket $original, ?Ticket $escalation = null): ?string
    {
        return $this->viewUrl(
            $original->panel,
            $original->getKey(),
            $escalation === null ? [] : ['linked' => $escalation->getKey()],
        );
    }

    public function unviewableEscalationUrl(Ticket $escalation): ?string
    {
        $original = EscalationSummary::originalsOf($escalation)->whereNull('closed_at')->first();

        if ($original !== null) {
            return $this->originalUrl($original);
        }

        return $this->panelUrl($escalation->escalationSourcePanel(), '/tickets?tab=linked');
    }

    /**
     * @param  array<string, int|string>  $query
     */
    protected function viewUrl(?string $panelId, int|string $ticketId, array $query = []): ?string
    {
        $url = $this->panelUrl($panelId, "/tickets/{$ticketId}/view");

        return $url !== null && $query !== [] ? $url.'?'.http_build_query($query) : $url;
    }

    protected function panelUrl(?string $panelId, string $path): ?string
    {
        $panel = $panelId === null ? null : (Filament::getPanels()[$panelId] ?? null);

        return $panel === null ? null : rtrim((string) $panel->getUrl(), '/').$path;
    }
}
