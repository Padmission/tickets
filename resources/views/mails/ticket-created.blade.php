@component('padmission-tickets::mails.layout', ['notification' => $notification])
    <x-mail::unindent-html>
        # {!! \Padmission\Tickets\Support\MailText::escape($headline ?? __('padmission-tickets::notifications.ticket-created.headline')) !!}

        {!! \Padmission\Tickets\Support\MailText::escape($intro ?? __('padmission-tickets::notifications.ticket-created.intro')) !!}

        @if (filled($ticket->subject))
            **{{ __('padmission-tickets::notifications.general.ticket_label') }}** {!! \Padmission\Tickets\Support\MailText::escape($ticket->subject) !!}
        @endif

        @if (filled($openingMessage ?? null))
            **{!! \Padmission\Tickets\Support\MailText::escape($openingMessageLabel) !!}**

            {!! \Padmission\Tickets\Support\MailText::escape($openingMessage) !!}
        @endif

        @if (filled($assigneeName ?? null))
            **{{ __('padmission-tickets::notifications.general.assigned_to_label') }}** {!! \Padmission\Tickets\Support\MailText::escape($assigneeName) !!}
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
