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

    @if ($isOriginal)
        {{-- An original: which one of how many, then its title and who it involves. --}}
        <header class="pad-ti-linked__header pad-ti-linked__header--original">
            <div class="pad-ti-linked__nav">
                @if ($linkedTickets->count() > 1)
                    <span>{{ __('padmission-tickets::tickets.linked_view.pane.original') }}</span>

                    <span class="pad-ti-linked__counter-wrap" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                        <button type="button" class="pad-ti-linked__counter" aria-haspopup="listbox" x-bind:aria-expanded="open" x-on:click="open = ! open">
                            {{ __('padmission-tickets::tickets.linked_view.pane.position', ['position' => $position, 'count' => $linkedTickets->count()]) }}
                            <x-heroicon-o-chevron-down class="fi-icon fi-size-xs" />
                        </button>

                        <div class="pad-ti-linked__menu" role="listbox" aria-label="{{ __('padmission-tickets::tickets.linked_view.switch') }}" x-show="open" x-cloak>
                            @foreach ($linkedTickets as $ticket)
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected="{{ $ticket->is($linked) ? 'true' : 'false' }}"
                                    @class(['pad-ti-linked__option', 'is-active' => $ticket->is($linked)])
                                    wire:click="showLinked({{ $ticket->getKey() }})"
                                    x-on:click="open = false"
                                >
                                    <span class="pad-ti-linked__check">
                                        @if ($ticket->is($linked))
                                            <x-heroicon-o-check class="fi-icon fi-size-sm" />
                                        @endif
                                    </span>
                                    <span class="pad-ti-linked__option-name">{{ $ticket->requesterName() ?? $headings[$ticket->getKey()] }} <span class="pad-ti-linked__num">#{{ $ticket->getKey() }}</span></span>
                                    <x-filament::badge size="sm" :color="$ticket->status?->colorPalette">{{ $ticket->status?->display_name }}</x-filament::badge>
                                </button>
                            @endforeach
                        </div>
                    </span>

                    <span class="pad-ti-linked__arrows">
                        <x-filament::icon-button
                            icon="heroicon-o-chevron-left"
                            size="sm"
                            color="gray"
                            :label="__('padmission-tickets::tickets.linked_view.pane.previous')"
                            :tooltip="__('padmission-tickets::tickets.linked_view.pane.previous')"
                            :disabled="$previousId === null"
                            :wire:click="$previousId === null ? null : 'showLinked('.$previousId.')'"
                        />
                        <x-filament::icon-button
                            icon="heroicon-o-chevron-right"
                            size="sm"
                            color="gray"
                            :label="__('padmission-tickets::tickets.linked_view.pane.next')"
                            :tooltip="__('padmission-tickets::tickets.linked_view.pane.next')"
                            :disabled="$nextId === null"
                            :wire:click="$nextId === null ? null : 'showLinked('.$nextId.')'"
                        />
                    </span>
                @else
                    <span>{{ __('padmission-tickets::tickets.linked_view.pane.original_number', ['id' => $linked->getKey()]) }}</span>
                @endif

                <x-filament::badge size="sm" :color="$linked->status?->colorPalette">{{ $linked->status?->display_name }}</x-filament::badge>

                <span class="pad-ti-linked__readonly">
                    <x-heroicon-o-lock-closed class="fi-icon fi-size-xs" />
                    {{ __('padmission-tickets::tickets.linked_view.read_only') }}
                </span>
            </div>

            <div class="pad-ti-linked__title">
                {{ $linked->subject }}
                @if ($linkedTickets->count() > 1)
                    <span class="pad-ti-linked__title-number">#{{ $linked->getKey() }}</span>
                @endif
            </div>

            <dl class="pad-ti-linked__people">
                <dt>{{ __('padmission-tickets::tickets.actions.view_original_conversation.requested_by') }}</dt>
                <dd>
                    <span class="pad-ti-linked__person">
                        @if ($linked->submitter)
                            <x-filament::avatar :src="filament()->getUserAvatarUrl($linked->submitter)" alt="" size="pad-ti-linked__avatar" />
                        @endif
                        {{ $linked->requesterName() ?? '-' }}
                        @if (($hasPersonActions ?? false) && isset($getChildSchema))
                            <span class="pad-ti-transcript__person-actions">{{ $getChildSchema() }}</span>
                        @endif
                    </span>
                </dd>

                <dt>{{ __('padmission-tickets::tickets.actions.view_original_conversation.assigned_to') }}</dt>
                <dd>
                    <span class="pad-ti-linked__person">
                        @if ($handler)
                            <x-filament::avatar :src="filament()->getUserAvatarUrl($handler)" alt="" size="pad-ti-linked__avatar" />
                            {{ $summary->displayName($handler) }}
                        @else
                            {{ __('padmission-tickets::tickets.resources.tickets.unassigned') }}
                        @endif
                    </span>
                </dd>

                @if (filled($waitingOn))
                    <dt>{{ __('padmission-tickets::tickets.resources.tickets.turn') }}</dt>
                    <dd><x-filament::badge size="sm" color="gray">{{ $waitingOn }}</x-filament::badge></dd>
                @endif
            </dl>

            @if ($headerLink)
                <x-filament::link :href="$headerLink['url']" size="sm" class="pad-ti-linked__header-link">
                    {{ $headerLink['label'] }}
                </x-filament::link>
            @endif
        </header>
    @else
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
    @endif

    <div class="pad-ti-linked__body" x-ref="body">
        @include('padmission-tickets::filament.original-conversation', [
            'escalatedTicket' => $record,
            'originalTickets' => collect([$linked]),
            'activityService' => $activityService,
            'titleRow' => 'none',
            'lastSeenId' => $lastSeenId,
            // An original's header already names its people.
            'showPeople' => ! $isOriginal,
        ])
    </div>
</div>
