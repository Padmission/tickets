<div class="pad-ti-escalation-status">
    <x-filament::callout
        :color="$status['warning'] ? 'warning' : 'gray'"
        :icon="$status['warning'] ? 'heroicon-o-chat-bubble-left-ellipsis' : null"
        :description="$status['text']"
    >
        @if ($status['readReplyLabel'] || $status['openUrl'])
            <x-slot name="footer">
                @if ($status['readReplyLabel'] && $status['paneOpen'])
                    {{-- The escalation is already beside the chat, so the button finds the reply in it. --}}
                    <x-filament::button size="sm" color="warning" x-on:click="$dispatch('pad-ti-linked-scroll')">
                        {{ $status['readReplyLabel'] }}
                    </x-filament::button>
                @elseif ($status['readReplyLabel'])
                    <x-filament::button size="sm" color="warning" wire:click="showLinked({{ $status['escalationId'] }})">
                        {{ $status['readReplyLabel'] }}
                    </x-filament::button>
                @endif

                @if ($status['openUrl'])
                    <x-filament::button size="sm" color="gray" tag="a" :href="$status['openUrl']">
                        {{ __('padmission-tickets::tickets.linked_view.open_escalation') }}
                    </x-filament::button>
                @endif
            </x-slot>
        @endif
    </x-filament::callout>
</div>
