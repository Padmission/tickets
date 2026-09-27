<?php

namespace Padmission\Tickets\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ModalTableSelect;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Filament\Infolists\UserDescription;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class LinkedTicketModalSelect extends ModalTableSelect
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->columnSpanFull()
            ->placeholder(fn () => $this->isMultiple() ? 'No tickets linked' : 'Not linked')
            // The select action writes through afterStateUpdated, so it is
            // authorized only while the field is enabled. The check is a
            // closure so the abilities behind disabled() are evaluated when
            // the action runs.
            ->selectAction(fn (Action $action) => $action
                ->link()
                ->label(__('padmission-tickets::tickets.resources.tickets.link_existing_ticket'))
                ->authorize(fn (): bool => ! $this->isDisabled()))
            ->getOptionLabelFromRecordUsing(function ($record) {
                $canViewTicket = Filament::auth()->user()->can('view', $record);
                $url = $canViewTicket ? $this->originalUrl($record) : null;
                $plugin = TicketPlugin::get();
                $origin = $plugin->describeTicketOrigin($record);
                $requester = $record->submitter ? Filament::getUserName($record->submitter) : null;
                $roles = $record->submitter ? UserDescription::render($plugin->describeUser($record->submitter, $record)) : null;
                $details = filled($origin) || filled($requester);

                return new HtmlString(Blade::render(<<<'BLADE'
                    <div class="ticket-card">
                        <x-filament::badge size="sm" color="gray">
                            #{{ $record->id }}
                        </x-filament::badge>

                        @if ($record->status)
                            <x-filament::badge size="sm" :color="$record->status->colorPalette">
                                {{ $record->status->display_name }}
                            </x-filament::badge>
                        @endif

                        <div class="ticket-card__subject">
                            @unless ($canViewTicket)
                                {{ $record->subject }}
                            @else
                                <a href="{{ $url }}">
                                    {{ $record->subject }}

                                    <x-heroicon-o-arrow-top-right-on-square class="fi-icon fi-size-sm" />
                                </a>
                            @endunless
                        </div>

                        @if ($details)
                            <div class="ticket-card__details">
                                {{ $origin }}
                                @if (filled($origin) && filled($requester)) · @endif
                                @if (filled($requester))
                                    {{ __('padmission-tickets::tickets.resources.tickets.requested_by_line', ['name' => $requester]) }}@if ($roles), {{ $roles }}@endif
                                @endif
                            </div>
                        @endif
                    </div>
                BLADE, compact('record', 'url', 'canViewTicket', 'details', 'origin', 'requester', 'roles')));
            });
    }

    /*
     * Each original is read beside its escalation. The team the escalation
     * was sent to reads it on this page, and the team that escalated it
     * answers its requester on the original, with the escalation beside it.
     */
    protected function originalUrl(Ticket $original): string
    {
        $escalation = isset($this->container) ? $this->getRecord() : null;

        if ($escalation instanceof Ticket && $escalation->isInCurrentPanel()) {
            return TicketResource::getUrl('view', ['record' => $escalation, 'linked' => $original->getKey()]);
        }

        return TicketResource::getUrl('view', ['record' => $original, 'linked' => $escalation?->getKey()]);
    }
}
