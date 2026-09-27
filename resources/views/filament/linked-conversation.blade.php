<div @class(['pad-ti-linked', 'pad-ti-linked--drawer' => $drawer])>
    @if ($drawer)
        {{-- Pushes the page over so the drawer never covers the chat or its reply box. --}}
        <style>
            .fi-page { margin-inline-end: var(--pad-ti-drawer-width); }
        </style>
    @endif

    <header class="pad-ti-linked__header">
        <div>
            <div class="pad-ti-linked__eyebrow">
                {{ $headings[$linked->getKey()] }} · {{ __('padmission-tickets::tickets.linked_view.read_only') }}
            </div>
            <div class="pad-ti-linked__title">
                {{ $linked->subject }}
                <x-filament::badge size="sm" :color="$linked->status?->colorPalette">
                    {{ $linked->status?->display_name }}
                </x-filament::badge>
            </div>
        </div>

        @if ($headerLink)
            <x-filament::link :href="$headerLink['url']" size="sm" class="pad-ti-linked__header-link">
                {{ $headerLink['label'] }}
            </x-filament::link>
        @endif
    </header>

    @if ($linkedTickets->count() > 1)
        <nav class="pad-ti-linked__switcher" aria-label="{{ __('padmission-tickets::tickets.linked_view.switch') }}">
            @foreach ($linkedTickets as $ticket)
                <button
                    type="button"
                    wire:click="showLinked({{ $ticket->getKey() }})"
                    @class(['pad-ti-linked__tab', 'pad-ti-linked__tab--active' => $ticket->is($linked)])
                    @if ($ticket->is($linked)) aria-current="true" @endif
                    x-data
                    x-tooltip="{ content: @js($ticket->subject), theme: $store.theme, placement: 'bottom' }"
                >
                    {{ $ticket->requesterName() ?? $headings[$ticket->getKey()] }}
                    <span class="pad-ti-linked__tab-number">· #{{ $ticket->getKey() }}</span>
                </button>
            @endforeach
        </nav>
    @endif

    <div class="pad-ti-linked__body">
        @include('padmission-tickets::filament.original-conversation', [
            'escalatedTicket' => $record,
            'originalTickets' => collect([$linked]),
            'activityService' => $activityService,
            'titleRow' => 'none',
        ])
    </div>
</div>
