{{--
    The escalation's originals as quiet rows. A row opens its original where
    this side reads it; its × takes it out of the escalation, and the + beside
    the label links another. Past the first few open ones, and every closed
    one, fold away until asked for.
--}}
@php
    $key = 'padmission-tickets::tickets.resources.tickets.originals_box.';
    $count = $open->count() + $closed->count();
    $hidden = max(0, $open->count() - $visible);
@endphp

<div class="pad-ti-originals" x-data="{ all: false, closed: false }">
    <div class="pad-ti-originals__label">
        <span>{{ $count === 1 ? __($key.'label_one') : __('padmission-tickets::tickets.resources.tickets.child_tickets') }}</span>

        @if ($canChange)
            {{ $this->linkOriginalsAction }}
        @endif
    </div>

    @if ($count === 0)
        <p class="pad-ti-originals__empty">{{ __('padmission-tickets::tickets.resources.tickets.child_tickets_placeholder') }}</p>
    @else
        <ul class="pad-ti-originals__list">
            @foreach ($open as $index => $row)
                @include('padmission-tickets::filament.escalation-original-row', [
                    'row' => $row,
                    'show' => $index < $visible ? null : 'all',
                ])
            @endforeach

            @if ($hidden > 0)
                <li class="pad-ti-originals__fold">
                    <button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all">
                        <x-heroicon-o-chevron-down class="fi-icon fi-size-sm" x-show="! all" />
                        <x-heroicon-o-chevron-up class="fi-icon fi-size-sm" x-show="all" x-cloak />
                        <span x-show="! all">{{ __($key.'show_more', ['count' => $hidden]) }}</span>
                        <span x-show="all" x-cloak>{{ __($key.'show_fewer') }}</span>
                    </button>
                </li>
            @endif

            @if ($closed->isNotEmpty())
                <li class="pad-ti-originals__fold">
                    <button type="button" x-on:click="closed = ! closed" x-bind:aria-expanded="closed">
                        <x-heroicon-o-chevron-down class="fi-icon fi-size-sm" x-show="! closed" />
                        <x-heroicon-o-chevron-up class="fi-icon fi-size-sm" x-show="closed" x-cloak />
                        <span>{{ __($key.'closed_count', ['count' => $closed->count()]) }}</span>
                    </button>
                </li>

                @foreach ($closed as $row)
                    @include('padmission-tickets::filament.escalation-original-row', ['row' => $row, 'show' => 'closed'])
                @endforeach
            @endif
        </ul>
    @endif
</div>
