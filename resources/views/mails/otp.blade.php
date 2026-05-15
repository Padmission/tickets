@component('padmission-tickets::mails.layout', ['notification' => $notification])
    <x-mail::unindent-html>
        # {{ __('padmission-tickets::notifications.otp-verification.subject')  }}

        {{ __('padmission-tickets::notifications.otp-verification.message') }}

        <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: Arial, sans-serif; margin: 30px 0;">
            <tr>
                <td align="center" style="padding: 20px 0; color: #333333;">
                    <div style="display: block; text-align: center; margin: 0 auto;">
                        <!-- Whitespace was removed so it's not copied with the digits -->
                        @foreach(str_split($code) as $digit)<span style="display: inline-block; background-color: #f5f5f5; border-radius: 8px; margin: 0 5px; padding: 12px 14px; font-size: 28px; font-weight: bold; color: #000000; border: 1px solid #dddddd;">{{ $digit }}</span>@endforeach
                    </div>
                </td>
            </tr>
        </table>

        {{ __('padmission-tickets::notifications.otp-verification.expires-hint', ['minutes' => config('padmission-tickets.otp.expires', 10)]) ?? 'This code will expire in ' . config('padmission-tickets.otp.expires', 10) . ' minutes' }}
    </x-mail::unindent-html>
@endcomponent
