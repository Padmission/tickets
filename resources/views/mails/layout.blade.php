{{--
    Laravel's plain-text header is the bare APP_URL, which for a host with tenant domains is
    its marketing site rather than anywhere the recipient works, so the plain text leaves it out.
    The mail namespace points at the text components only while the plain text renders.
--}}
@if (collect(view()->getFinder()->getHints()['mail'] ?? [])->contains(fn (string $path): bool => str_ends_with($path, '/text')))
<x-mail::layout>
    {{ $slot }}

    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. @lang('All rights reserved.')
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
@else
<x-mail::message>
    {{ $slot }}
</x-mail::message>
@endif
