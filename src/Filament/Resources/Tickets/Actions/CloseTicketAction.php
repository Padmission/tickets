<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

class CloseTicketAction extends Action
{
    use ScopesLookupsToTicket;

    public static function getDefaultName(): ?string
    {
        return 'close-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.close.label'))
            ->modalHeading(__('padmission-tickets::tickets.actions.close.modal_heading'))
            ->modalDescription(fn (Ticket $record): string => $this->describe($record))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.close.submit'))
            ->button()
            ->color('gray')
            ->hidden(function ($record): bool {
                if ($record->panel !== Filament::getCurrentOrDefaultPanel()->getId()) {
                    return true;
                }

                return $record->isClosed;
            })
            ->requiresConfirmation()
            ->slideOver(false)
            ->icon('heroicon-o-check-circle');

        $this->schema(fn (Ticket $record): array => $this->dispositionsExist($record) ? [
            Select::make('disposition')
                ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                ->relationship(
                    'disposition',
                    'display_name',
                    fn ($query) => $this->scopeLookupToTicket($query, $this->getRecord()),
                )
                ->lazy()
                ->required(),
        ] : []);

        $this->action(function (Ticket $record, Component $livewire, $data) {
            $record->close(
                dispositionId: $data['disposition'] ?? null,
                closedById: Filament::auth()->id()
            );

            $livewire->dispatch('refresh-sidebar');
        });
    }

    protected function describe(Ticket $record): string
    {
        $key = 'padmission-tickets::tickets.actions.close.';

        if (count(TicketPlugin::get()->getLinkedTicketChildPanels()) > 0 && $record->isEscalation()) {
            return __($key.'modal_description_received', ['contact' => $this->contactOf($record)]);
        }

        return implode(' ', array_filter([
            __($key.'modal_description'),
            $this->describeOpenEscalation($record),
        ]));
    }

    /*
     * Closing an original leaves its escalation open, and only the person
     * handling it can close that.
     */
    protected function describeOpenEscalation(Ticket $record): ?string
    {
        $escalation = resolve(TicketEscalationLinks::class)->escalationOf($record);

        if ($escalation === null || $escalation->isClosed) {
            return null;
        }

        $key = 'padmission-tickets::tickets.actions.close.modal_description_escalated_original';
        $team = TicketPlugin::find($escalation->panel)?->getSupportTeamName();

        return match (true) {
            $escalation->isSubmittedBy(Filament::auth()->id()) => TicketPlugin::teamText("{$key}_you", $team),
            $escalation->submitter === null => TicketPlugin::teamText("{$key}_unnamed", $team),
            default => TicketPlugin::teamText($key, $team, ['handler' => resolve(GetUserDisplayName::class)->forUser($escalation->submitter)]),
        };
    }

    protected function contactOf(Ticket $escalation): string
    {
        $key = 'padmission-tickets::tickets.actions.close.';
        $name = $escalation->requesterName();

        if (blank($name)) {
            return __($key.'the_contact');
        }

        $plugin = TicketPlugin::get();
        $organization = $plugin->describeTicketOrigin($escalation)
            ?? resolve(TicketEscalationLinks::class)->linkedOriginalsQuery($escalation->getKey())->get()
                ->map(fn (Ticket $original): ?string => $plugin->describeTicketOrigin($original))
                ->filter()
                ->first();

        return blank($organization) ? $name : __($key.'contact_at', ['name' => $name, 'organization' => $organization]);
    }

    /*
     * Asked the way the disposition options are loaded, so a disposition is
     * required only when the ticket's own panel and tenant offers one.
     */
    protected function dispositionsExist(Ticket $record): bool
    {
        $query = Relation::noConstraints(fn (): Relation => $record->disposition())->getQuery();

        return $this->scopeLookupToTicket($query, $record)->exists();
    }
}
