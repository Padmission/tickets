<?php

namespace Padmission\Tickets\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ModalTableSelect;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\TicketsTable;

class LinkedTicketModalSelect extends ModalTableSelect
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->tableConfiguration(TicketsTable::class)
            ->columnSpanFull()
            ->placeholder(fn () => $this->isMultiple() ? 'No tickets linked' : 'Not linked')
            ->selectAction(fn (Action $action) => $action->link())
            ->getOptionLabelFromRecordUsing(function ($record) {
                $panel = Filament::getPanel($record->panel);
                $canAccessPanel = $panel && Filament::auth()->user()->canAccessPanel($panel);
                $url = $panel ? TicketResource::getUrl('view', ['record' => $record->id], panel: $record->panel) : null;

                return new HtmlString(Blade::render(<<<'BLADE'
                    <div class="ticket-card">
                        <x-filament::badge size="sm" color="gray">
                            #{{ $record->id }}
                        </x-filament::badge>

                        <x-filament::badge size="sm" :color="$record->status->colorPalette">
                            {{ $record->status->display_name }}
                        </x-filament::badge>

                        <div class="ticket-card__subject">
                            @unless ($canAccessPanel)
                                {{ $record->subject }}
                            @else
                                <a href="{{ $url }}">
                                    {{ $record->subject }}

                                    <x-heroicon-o-arrow-top-right-on-square class="fi-icon fi-size-sm" />
                                </a>
                            @endunless
                        </div>
                    </div>
                BLADE, compact('record', 'url', 'canAccessPanel')));
            });
    }
}
