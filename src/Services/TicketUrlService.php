<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Rules\SafeUrl;
use Padmission\Tickets\TicketPlugin;

class TicketUrlService
{
    public function getActionUrl(Ticket $ticket): string
    {
        $data = (array) $ticket->data;
        // A ticket saved before the page address was checked may hold one that runs script.
        $url = is_string($data['url'] ?? null) && SafeUrl::isSafe($data['url']) ? $data['url'] : url('/');

        return $url.'#'.'ticket-'.$ticket->id;
    }

    /*
     * Null when an escalation has no page to link: a requester's link would
     * name a ticket the recipient never filed.
     */
    public function getActionUrlFor(Ticket $ticket, Model $recipient): ?string
    {
        return $this->workPageUrl($ticket, $recipient) ?? ($ticket->isEscalation() ? null : $this->getActionUrl($ticket));
    }

    /*
     * Built from the panel's URL, since a queue worker may not register the
     * resource's plugin in every panel.
     */
    public function workPageUrl(Ticket $ticket, Model $recipient): ?string
    {
        if ($ticket->isSubmittedBy($recipient)) {
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
            $this->escalatingPanel($escalation),
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

        return $this->panelUrl($this->escalatingPanel($escalation), '/tickets?tab=linked');
    }

    /*
     * An escalation whose originals were all removed and that has no source
     * panel was still sent from a panel that escalates to its panel.
     */
    protected function escalatingPanel(Ticket $escalation): ?string
    {
        return $escalation->escalationSourcePanel()
            ?? collect(array_keys(Filament::getPanels()))->first(fn (string $panelId): bool => array_key_exists(
                (string) $escalation->panel,
                TicketPlugin::find($panelId)?->getLinkedTicketParentPanels() ?? [],
            ));
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
