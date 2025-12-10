<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 32px 0;">
    <tr>
        <td align="center" style="padding: 24px 0;">
            <!-- OTP code display -->
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <!-- Whitespace was removed so it's not copied with the digits -->
                    @foreach(str_split($code) as $digit)<td style="padding: 0 6px;"><div style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 2px solid #e2e8f0; border-radius: 12px; width: 52px; height: 64px; display: table-cell; vertical-align: middle; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);"><span style="font-size: 32px; font-weight: 700; color: #1e293b; letter-spacing: -0.02em;">{{ $digit }}</span></div></td>@endforeach
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding-top: 16px;">
            <p style="margin: 0; font-size: 13px; color: #94a3b8; font-weight: 500;">
                {{ __('padmission-tickets::notifications.otp.expires-hint', ['minutes' => config('padmission-tickets.otp.expires', 10)]) ?? 'This code will expire in ' . config('padmission-tickets.otp.expires', 10) . ' minutes' }}
            </p>
        </td>
    </tr>
</table>
