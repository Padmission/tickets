@component('padmission-tickets::mails.layout', ['notification' => $notification])
    <x-mail::unindent-html>
        # {{ $headline ?? __('padmission-tickets::notifications.ticket-created.headline') }}

        {{ $intro ?? __('padmission-tickets::notifications.ticket-created.intro') }}

        @if (filled($ticket->subject))
            **{{ __('padmission-tickets::notifications.general.ticket_label') }}** {{ $ticket->subject }}
        @endif

        @if (filled($assigneeName ?? null))
            **{{ __('padmission-tickets::notifications.general.assigned_to_label') }}** {{ $assigneeName }}
        @else
            {{ __('padmission-tickets::notifications.ticket-created.unassigned') }}
        @endif

        @if (isset($actionUrl))
            <x-mail::button :url="$actionUrl">
                {{ $actionLabel ?? __('padmission-tickets::notifications.general.action') }}
            </x-mail::button>
        @endif
    </x-mail::unindent-html>
@endcomponent
