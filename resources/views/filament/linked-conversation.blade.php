{{--
    Opens at the first message the viewer has not read, or the newest one, and
    marks it for a moment, since that is what they came to read.
--}}
<div
    @class(['pad-ti-linked', 'pad-ti-linked--drawer' => $drawer])
    wire:key="pad-ti-linked-{{ $linked->getKey() }}"
    x-data="{
        reveal() {
            const body = this.$refs.body
            const messages = body.querySelector('.pad-ti-transcript__messages') ?? body
            const target = body.querySelector('[data-pad-ti-first-unread]') ?? [...body.querySelectorAll('.pad-ti-transcript__messages > li')].pop()

            if (! target) {
                return
            }

            messages.scrollTop += target.getBoundingClientRect().top - messages.getBoundingClientRect().top - 8
            target.classList.remove('pad-ti-transcript--highlight')
            void target.offsetWidth
            target.classList.add('pad-ti-transcript--highlight')
        },
    }"
    x-init="$nextTick(() => reveal())"
>
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

    <div class="pad-ti-linked__body" x-ref="body">
        {{-- The host's actions for the requester, such as Impersonate, sit right after their name. --}}
        @include('padmission-tickets::filament.original-conversation', [
            'escalatedTicket' => $record,
            'originalTickets' => collect([$linked]),
            'activityService' => $activityService,
            'titleRow' => 'none',
            'lastSeenId' => $lastSeenId,
            'submitterActions' => ($hasPersonActions ?? false) && isset($getChildSchema) ? $getChildSchema() : null,
        ])
    </div>
</div>
