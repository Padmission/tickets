@php
    use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
@endphp

@if ($original !== null || $duplicates->isNotEmpty())
    <x-filament::section>
        @if ($original !== null && TicketResource::canView($original))
            <p>
                {{ __('padmission-tickets::tickets.duplicates.original') }}
                <x-filament::link :href="TicketResource::getUrl('view', ['record' => $original])">
                    #{{ $original->getKey() }} – {{ $original->subject }}
                </x-filament::link>
            </p>
        @endif

        @if ($duplicates->isNotEmpty())
            <h3 class="font-medium">{{ __('padmission-tickets::tickets.duplicates.heading') }}</h3>
            <ul class="space-y-1">
                @foreach ($duplicates as $duplicate)
                    <li>
                        <x-filament::link :href="TicketResource::getUrl('view', ['record' => $duplicate])">
                            #{{ $duplicate->getKey() }} – {{ $duplicate->subject }}
                        </x-filament::link>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
@endif
