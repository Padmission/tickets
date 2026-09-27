@component('padmission-tickets::mails.layout', ['notification' => $notification])
    <x-mail::unindent-html>
        # {{ $headline ?? __('padmission-tickets::notifications.ticket-closed.headline') }}

        {{ $intro ?? __('padmission-tickets::notifications.ticket-closed.intro') }}

        @if (filled($ticket->subject))
            **{{ __('padmission-tickets::notifications.general.ticket_label') }}** {{ $ticket->subject }}
        @endif

        @if (filled($dispositionName ?? null))
            **{{ __('padmission-tickets::notifications.general.closed_as_label') }}** {{ $dispositionName }}
        @endif

        @if (filled($lastSupporterMessage ?? null))
            **{{ $latestReplyLabel ?? __('padmission-tickets::notifications.ticket-closed.latest_reply') }}**

            {{ $lastSupporterMessage }}
        @endif

        @if (isset($actionUrl))
            <x-mail::button :url="$actionUrl">
                {{ $actionLabel ?? __('padmission-tickets::notifications.general.action') }}
            </x-mail::button>
        @endif
    </x-mail::unindent-html>
@endcomponent
