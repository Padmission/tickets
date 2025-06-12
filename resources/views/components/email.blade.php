@php
    view()->addNamespace('mail', base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views'));
@endphp

    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        @media only screen and (max-width: 600px) {
            .inner-body {
                width: 100% !important;
            }

            .footer {
                width: 100% !important;
            }
        }

        @media only screen and (max-width: 500px) {
            .button {
                width: 100% !important;
            }
        }
    </style>
    {{-- Include Laravel's default mail CSS --}}
@if(isset($styles))
    <style>
        {!! $styles !!}
    </style>
    @endif
    {!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
        <td align="center">
            <table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">

                @component('mail::html.header', ['url' => config('app.url')])
                    {{ config('app.name') }}
                @endcomponent

                @if(isset($introLines))
                    <tr>
                        <td class="body" width="100%" cellpadding="0" cellspacing="0"
                            style="border: hidden !important;">
                            <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0"
                                   role="presentation">

                                @foreach ($introLines as $line)
                                    <!-- Body content -->
                                    <tr align="center">
                                        <td class="content-cell" align="center">
                                            {{ $line }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                @if(isset($activity))
                    <!-- Email Body -->
                    <tr>

                        <td class="body" width="100%" cellpadding="0" cellspacing="0"
                            style="border: hidden !important;">
                            <br/>&nbsp;<br/>
                            <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0"
                                   role="presentation">
                                <!-- Body content -->
                                <tr>
                                    <td class="content-cell">
                                        {!! $activity !!}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                @if(isset($actionUrl))
                    <x-mail::html.button :url="$actionUrl">
                        {{ $actionText ? $actionText :__('padmission-tickets::emails.ticket-history.action') }}
                    </x-mail::html.button>
                @endif

                    @if(isset($outroLines))
                        <tr>
                            <td class="body" width="100%" cellpadding="0" cellspacing="0"
                                style="border: hidden !important;">
                                <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0"
                                       role="presentation">
<tr>
    <td>

        @if(isset($actionUrl))
            <x-mail::html.button :url="$actionUrl" class="button test1">
                {{ $actionText ? $actionText :__('padmission-tickets::emails.ticket-history.action') }}
            </x-mail::html.button>
        @endif
    </td>
</tr>
                                    @foreach ($outroLines as $line)
                                        <!-- Body content -->
                                        <tr align="center">
                                            <td class="content-cell" align="center">
                                                {{ $line }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                {{-- Footer using Laravel's exact footer component --}}
                @component('mail::html.footer')
                    © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
                @endcomponent

            </table>
        </td>
    </tr>
</table>
</body>
</html>
