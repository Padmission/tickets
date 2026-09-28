@php
    use Filament\Support\Facades\FilamentAsset;
    use Padmission\Tickets\TicketPlugin;

    $chatConfig = TicketPlugin::get()->getChatWidgetConfig();
    $chatPrimaryColor = $chatConfig->getPrimaryColor();
    $chatTimezone = TicketPlugin::get()->getDisplayTimezone();
@endphp

<div class="flex h-full min-h-0 flex-col bg-white dark:bg-gray-900">
    <style>
        [data-padmission-copilot-chat] chat-component {
            --color-primary: {{ $chatPrimaryColor }};
            --color-surface: transparent;
            --composer-bg: transparent;
        }
    </style>

    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center gap-1">
            @foreach (['open', 'closed', 'all'] as $ticketFilter)
                <button
                    type="button"
                    wire:key="ticket-filter-{{ $ticketFilter }}"
                    wire:click="setFilter('{{ $ticketFilter }}')"
                    @class([
                        'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition-colors',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $filter === $ticketFilter,
                        'text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200' => $filter !== $ticketFilter,
                    ])
                >
                    {{ __("padmission-tickets::tickets.copilot.filters.{$ticketFilter}") }}
                </button>
            @endforeach
        </div>

        <button
            type="button"
            wire:click="showCreateForm"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white transition-colors hover:bg-primary-500"
        >
            <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />
            {{ __('padmission-tickets::tickets.copilot.new_ticket') }}
        </button>
    </div>

    @if ($view === 'list')
        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
            @if ($tickets->isEmpty())
                <div class="flex h-full flex-col items-center justify-center gap-3 px-6 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-50 dark:bg-white/5">
                        <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="h-7 w-7 text-gray-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('padmission-tickets::tickets.copilot.empty_title') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('padmission-tickets::tickets.copilot.empty_body') }}
                        </p>
                    </div>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($tickets as $ticket)
                        <button
                            type="button"
                            wire:key="copilot-ticket-{{ $ticket->getKey() }}"
                            wire:click="selectTicket({{ $ticket->getKey() }})"
                            class="block w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left transition-colors hover:border-primary-300 hover:bg-primary-50/40 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $ticket->subject }}
                                    </p>
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $ticket->latestMessage?->plainTextContent() ?: __('padmission-tickets::tickets.copilot.no_messages') }}
                                    </p>
                                </div>
                                @if ($ticket->status || $ticket->has_unread_support_response)
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        @if ($ticket->status)
                                            <x-filament::badge :color="$ticket->status->colorPalette" size="sm" class="shrink-0">
                                                {{ $ticket->status?->display_name }}
                                            </x-filament::badge>
                                        @endif

                                        @if ($ticket->has_unread_support_response)
                                            <x-filament::badge
                                                color="primary"
                                                size="sm"
                                                class="shrink-0"
                                                data-testid="copilot-ticket-unread-badge-{{ $ticket->getKey() }}"
                                            >
                                                {{ __('padmission-tickets::tickets.copilot.unread') }}
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3 text-[10px] text-gray-400 dark:text-gray-500">
                                {{ $ticket->updated_at?->diffForHumans() }}
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($view === 'create')
        <div class="flex shrink-0 items-center gap-3 border-b border-gray-200 px-6 py-4 dark:border-white/10">
            <button
                type="button"
                wire:click="showList"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300"
            >
                <x-filament::icon icon="heroicon-o-arrow-left" class="h-5 w-5" />
            </button>
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('padmission-tickets::tickets.copilot.create_title') }}
                </h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('padmission-tickets::tickets.copilot.create_subtitle') }}
                </p>
            </div>
        </div>

        <div
            wire:ignore
            wire:key="copilot-ticket-chat-create"
            data-padmission-copilot-chat
            x-data="{
                handleTicketCreated: (event) => event.detail?.id && Livewire.dispatch('padmission-ticket-created-from-copilot', { ticketId: Number(event.detail.id) }),
                handleMessageSent: () => Livewire.dispatch('padmission-ticket-message-sent-from-copilot'),
                init() {
                    window.addEventListener('ticket-created', this.handleTicketCreated)
                    this.$refs.chat.addEventListener('message-sent', this.handleMessageSent)
                },
                destroy() {
                    window.removeEventListener('ticket-created', this.handleTicketCreated)
                    this.$refs.chat?.removeEventListener('message-sent', this.handleMessageSent)
                },
            }"
            class="min-h-0 flex-1"
        >
            <chat-component
                x-ref="chat"
                ticket-id=""
                config="{{ $chatConfig->toJs() }}"
                timezone="{{ $chatTimezone }}"
                scroll-threshold="100"
                polling-interval="10000"
            ></chat-component>
        </div>
    @elseif ($activeTicket)
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-white/10">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    wire:click="showList"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300"
                >
                    <x-filament::icon icon="heroicon-o-arrow-left" class="h-5 w-5" />
                </button>
                <div class="flex min-w-0 items-center gap-2">
                    <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $activeTicket->subject }}
                    </h3>
                    @if ($activeTicket->status)
                        <x-filament::badge :color="$activeTicket->status->colorPalette" size="sm" class="shrink-0">
                            {{ $activeTicket->status?->display_name }}
                        </x-filament::badge>
                    @endif
                </div>
            </div>

            @if (! $activeTicket->isClosed)
                <div x-data class="shrink-0">
                    <button
                        type="button"
                        x-on:click="$refs.resolveDialog.showModal()"
                        wire:loading.attr="disabled"
                        wire:target="resolveTicket"
                        aria-haspopup="dialog"
                        class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                        {{ __('padmission-tickets::tickets.copilot.resolve') }}
                    </button>

                    {{-- A native modal dialog sits in the top layer, above the assistant's slide-over, and traps focus and closes on Escape by itself. --}}
                    <dialog
                        x-ref="resolveDialog"
                        class="pad-ti-confirm"
                        aria-labelledby="copilot-resolve-heading-{{ $activeTicket->getKey() }}"
                        aria-describedby="copilot-resolve-description-{{ $activeTicket->getKey() }}"
                    >
                        <h2 id="copilot-resolve-heading-{{ $activeTicket->getKey() }}" class="pad-ti-confirm__heading">
                            {{ __('padmission-tickets::tickets.copilot.resolve_confirm.heading') }}
                        </h2>
                        <p id="copilot-resolve-description-{{ $activeTicket->getKey() }}" class="pad-ti-confirm__description">
                            {{ __('padmission-tickets::tickets.copilot.resolve_confirm.description') }}
                        </p>
                        <div class="pad-ti-confirm__actions">
                            <x-filament::button color="gray" x-on:click="$refs.resolveDialog.close()">
                                {{ __('padmission-tickets::tickets.copilot.cancel') }}
                            </x-filament::button>
                            <x-filament::button
                                wire:click="resolveTicket"
                                x-on:click="$refs.resolveDialog.close()"
                                data-copilot-resolve-confirm
                            >
                                {{ __('padmission-tickets::tickets.copilot.resolve_confirm.submit') }}
                            </x-filament::button>
                        </div>
                    </dialog>
                </div>
            @endif
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            <div
                wire:ignore
                wire:key="copilot-ticket-chat-{{ $activeTicket->getKey() }}"
                data-padmission-copilot-chat
                x-data="{
                    handleMessageSent: () => Livewire.dispatch('padmission-ticket-message-sent-from-copilot'),
                    handleTicketClosed: null,
                    handleChatChanged: null,
                    init() {
                        this.handleTicketClosed = () => this.$wire.$refresh()
                        this.handleChatChanged = (event) => String(event.detail?.ticketId) === this.$refs.chat.getAttribute('ticket-id') && this.$refs.chat.refreshTicket?.(event.detail.canReply)
                        this.$refs.chat.addEventListener('message-sent', this.handleMessageSent)
                        this.$refs.chat.addEventListener('ticket-closed', this.handleTicketClosed)
                        window.addEventListener('ticket-chat-changed', this.handleChatChanged)
                    },
                    destroy() {
                        this.$refs.chat?.removeEventListener('message-sent', this.handleMessageSent)
                        this.$refs.chat?.removeEventListener('ticket-closed', this.handleTicketClosed)
                        window.removeEventListener('ticket-chat-changed', this.handleChatChanged)
                    },
                }"
                class="min-h-0 flex-1"
            >
                <chat-component
                    x-ref="chat"
                    ticket-id="{{ $activeTicket->getKey() }}"
                    config="{{ $chatConfig->toJs() }}"
                    timezone="{{ $chatTimezone }}"
                    scroll-threshold="100"
                    polling-interval="10000"
                    has-elevated-rights="false"
                ></chat-component>
            </div>
        </div>
    @endif
</div>

@assets
    <script
        src="{{ FilamentAsset::getScriptSrc('chat-widget', package: 'padmission/tickets') }}"
        type="module"
    ></script>
@endassets
