@component('padmission-tickets::mails.layout', ['notification' => $notification])
    <x-mail::unindent-html>
        # {!! \Padmission\Tickets\Support\MailText::escape($headline ?? __('padmission-tickets::notifications.ticket-closed.headline')) !!}

        {!! \Padmission\Tickets\Support\MailText::escape($intro ?? __('padmission-tickets::notifications.ticket-closed.intro')) !!}

        @if (filled($ticket->subject))
            **{{ __('padmission-tickets::notifications.general.ticket_label') }}** {!! \Padmission\Tickets\Support\MailText::escape($ticket->subject) !!}
        @endif

        @if (filled($dispositionName ?? null))
            **{{ __('padmission-tickets::notifications.general.disposition_label') }}** {!! \Padmission\Tickets\Support\MailText::escape($dispositionName) !!}
        @endif

        @if (filled($lastSupporterMessage ?? null))
            **{!! \Padmission\Tickets\Support\MailText::escape($latestReplyLabel ?? __('padmission-tickets::notifications.ticket-closed.latest_reply')) !!}**

            {!! \Padmission\Tickets\Support\MailText::escape($lastSupporterMessage) !!}
        @endif

        @if (isset($actionUrl))
            <x-mail::button :url="$actionUrl">
                {{ $actionLabel ?? __('padmission-tickets::notifications.general.action') }}
            </x-mail::button>
        @endif
    </x-mail::unindent-html>
@endcomponent
