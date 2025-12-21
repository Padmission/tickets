<x-padmission-tickets::email
    :actionUrl="$actionUrl"
    :actionText="$actionText"
    :introLines="$introLines"
    :logo="$logo"
    :outroLines="$outroLines"
    :styles="$styles">

    @if($activities->count())
    <x-slot name="activity">
        <!-- Activities header -->
        @if(isset($activitiesHeader))
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
            <tr>
                <td align="center">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                            <td style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
                                <p style="margin: 0; font-size: 14px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">
                                    {!! $activitiesHeader !!}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        @endif

        <!-- Activity messages -->
        @foreach($activities as $activity)
            @if($activity->sender == \Padmission\Tickets\Enums\ActivitySender::User)
            <!-- User message (left aligned) -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 16px;">
                <tr>
                    <td align="left">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="85%" class="activity-bubble" style="max-width: 85%;">
                            <tr>
                                <td style="background-color: #f1f5f9; border-radius: 16px 16px 16px 4px; padding: 16px 20px;">
                                    <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #334155;">
                                        {!! strip_tags($activity->content) !!}
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 4px 0 4px;">
                                    <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                        @if($activity->user)
                                            {{ $activity->userName }}
                                        @else
                                            {{ __('padmission-tickets::emails.activity.sender-you') }}
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            @elseif($activity->sender == \Padmission\Tickets\Enums\ActivitySender::Supporter)
            <!-- Supporter message (right aligned, accent color) -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 16px;">
                <tr>
                    <td align="right">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="85%" class="activity-bubble" style="max-width: 85%;">
                            <tr>
                                <td style="background-color: #4f46e5; border-radius: 16px 16px 4px 16px; padding: 16px 20px;">
                                    <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #ffffff;">
                                        {!! $activity->content !!}
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td align="right" style="padding: 6px 4px 0 4px;">
                                    <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                        @if($activity->user)
                                            {{ $activity->userName }}
                                        @else
                                            {{ __('padmission-tickets::emails.activity.sender-support') }}
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            @else
            <!-- System message (centered, subtle) -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 16px;">
                <tr>
                    <td align="center">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                            <tr>
                                <td style="background-color: #fef3c7; border-radius: 20px; padding: 8px 16px;">
                                    <p style="margin: 0; font-size: 13px; color: #92400e; font-weight: 500;">
                                        {!! $activity->content !!}
                                    </p>
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
