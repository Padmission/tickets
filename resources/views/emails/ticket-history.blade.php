<x-padmission-tickets::email
    :actionUrl="$actionUrl"
    :actionText="$actionText"
    :introLines="$introLines"
    :logo="$logo"
    :outroLines="$outroLines"
    :styles="$styles">

    @if($activities->count())
    <x-slot name="activity">
        @if(isset($activitiesHeader))
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:16px;">
                <tr>
                    <td align="center">
                        <table width="80%" align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
                            <tr>
                                <td align="center">
                                    {!! $activitiesHeader !!}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        @endif
        @foreach($activities as $activity)
            @if($activity->sender == \Padmission\Tickets\Enums\ActivitySender::User)
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:16px;">
                    <tr>
                        <td align="left">
                            <table width="80%" align="left" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="left" style="padding: 12px 12px 0 12px; background:#edf2f7;">
                                        {!! strip_tags($activity->content) !!}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding:4px 12px 12px 12px;background:#edf2f7; font-size:8px; font-style:italic;">
                                        @if($activity->user)
                                            - {{ $activity->user->name }}
                                        @else
                                            - {{ __('padmission-tickets::emails.activity.sender-you') }}
                                        @endif
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            @elseif($activity->sender == \Padmission\Tickets\Enums\ActivitySender::Supporter)

                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:16px;">
                    <tr>
                        <td align="right">
                            <table width="80%" align="right" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="right" style="padding:12px 12px 0 12px;background:#edf2f7;">
                                        {!! $activity->content !!}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding:4px 12px 12px 12px;background:#edf2f7; font-size:8px; font-style:italic;">
                                        @if($activity->user)
                                            - {{ $activity->user->name }}
                                        @else
                                            - {{ __('padmission-tickets::emails.activity.sender-support') }}
                                        @endif
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            @else
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:16px;">
                    <tr>
                        <td align="center">
                            <table width="80%" align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
                                <tr>
                                    <td align="center" style="padding:12px 0;background:#f5f5f5;">
                                        {!! $activity->content !!}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endif
        @endforeach
    </x-slot>
    @endif
</x-padmission-tickets::email>
