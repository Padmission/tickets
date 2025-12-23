@php
    use Padmission\Tickets\Enums\ActivitySender;
@endphp

@component('padmission-tickets::mails.layout', ['notification' => $notification])
    <x-mail::unindent-html>
        # {{ __('padmission-tickets::notifications.ticket-'.$notificationType.'.headline') }}

        {{ __('padmission-tickets::notifications.ticket-'.$notificationType.'.intro') }}

        @if($activities->count())
            <h3 style="text-align: center; text-transform:uppercase; margin-top: 24px;">
                {{ __('padmission-tickets::notifications.general.recent-activity') }}
            </h3>

            <table style="margin-bottom: 16px;"  width="100%" cellpadding="0" cellspacing="0" role="presentation">
                @foreach($activities as $activity)
                    @php
                        $align = match($activity->sender) {
                            ActivitySender::User => 'left',
                            ActivitySender::Supporter => 'right',
                            default => 'center'
                        };

                        $width = match ($activity->sender) {
                            ActivitySender::System => '',
                            default =>  '90%'
                        };

                        $style = match ($activity->sender) {
                            ActivitySender::User => 'padding: 12px 16px; background: #f8fafc; border-radius: 8px 8px 8px 0',
                            ActivitySender::Supporter => 'padding: 12px 16px; background: #f3f4f6; border-radius: 8px 8px 0 8px',
                            default => 'padding: 8px 12px; background: #fef3c7; border-radius: 32px; font-size: .8em; font-weight: 500; color: rgba(145.67, 64.502, 15.012);',
                        };

                        $senderName = match($activity->sender) {
                            ActivitySender::User => $activity->user ? $activity->userName : __('padmission-tickets::notifications.general.sender-you'),
                            ActivitySender::Supporter => $activity->user ? $activity->userName : __('padmission-tickets::notifications.general.sender-support'),
                            default => null
                        };
                    @endphp
                    <tr>
                        <td style="padding-bottom: 16px">
                            <table
                                width="{{ $width }}" align="{{ $align }}" cellpadding="0" cellspacing="0" role="presentation"
                            >
                                <tr>
                                    <td
                                        align="{{ $align === 'center' ? 'center' : 'left' }}"
                                        style="{{ $style }}"
                                    >
                                        {!! strip_tags($activity->content) !!}
                                    </td>
                                </tr>
                                @if ($senderName)
                                    <tr>
                                        <td align="{{ $align }}" style="padding-top: 8px; font-size: 0.9em; font-style:italic;">
                                            {{ $senderName }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if ($hasMoreActivities)
            {{ __('padmission-tickets::notifications.general.more-activities') }}
        @endif

        @if(isset($actionUrl))
            <x-mail::button :url="$actionUrl">
                {{ __('padmission-tickets::notifications.general.action') }}
            </x-mail::button>
        @endif
    </x-mail::unindent-html>
@endcomponent
