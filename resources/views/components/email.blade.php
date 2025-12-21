@php
    view()->addNamespace('mail', base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views'));
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Base styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f4f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        /* Responsive */
        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .inner-body {
                width: 100% !important;
            }
            .footer {
                width: 100% !important;
            }
            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
            .activity-bubble {
                width: 90% !important;
            }
        }

        @media only screen and (max-width: 500px) {
            .button {
                width: 100% !important;
            }
            .button-td {
                padding: 0 20px !important;
            }
        }
    </style>
    @if(isset($styles))
    <style>
        {!! $styles !!}
    </style>
    @endif
    {!! $head ?? '' !!}
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7fa;">
    <!-- Background wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f7fa;">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                <!-- Email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%;">

                    <!-- Logo/Header -->
                    <tr>
                        <td align="center" style="padding: 0 0 30px 0;">
                            @if(isset($logo) && $logo)
                                {!! $logo !!}
                            @else
                                <span style="font-size: 24px; font-weight: 700; color: #1a202c; text-decoration: none;">
                                    {{ config('app.name') }}
                                </span>
                            @endif
                        </td>
                    </tr>

                    <!-- Main card -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">

                                <!-- Intro lines -->
                                @if(isset($introLines) && count($introLines) > 0)
                                <tr>
                                    <td class="mobile-padding" style="padding: 40px 48px 20px 48px;">
                                        @foreach ($introLines as $line)
                                        <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.625; color: #4a5568; text-align: center;">
                                            {{ $line }}
                                        </p>
                                        @endforeach
                                    </td>
                                </tr>
                                @endif

                                <!-- Activity content -->
                                @if(isset($activity))
                                <tr>
                                    <td class="mobile-padding" style="padding: 24px 48px;">
                                        {!! $activity !!}
                                    </td>
                                </tr>
                                @endif

                                <!-- Action button -->
                                @if(isset($actionUrl))
                                <tr>
                                    <td class="button-td" style="padding: 20px 48px 40px 48px;" align="center">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="border-radius: 8px; background-color: #4f46e5;">
                                                    <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px; background-color: #4f46e5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                                        {{ $actionText ? $actionText : __('padmission-tickets::emails.ticket-history.action') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endif

                                <!-- Outro lines -->
                                @if(isset($outroLines) && count($outroLines) > 0)
                                <tr>
                                    <td class="mobile-padding" style="padding: 0 48px 40px 48px;">
                                        @foreach ($outroLines as $line)
                                        <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.625; color: #718096; text-align: center;">
                                            {{ $line }}
                                        </p>
                                        @endforeach
                                    </td>
                                </tr>
                                @endif

                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 30px 20px;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #a0aec0;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
