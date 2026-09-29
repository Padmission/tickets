@php
    $tag = match (true) {
        filled($row['open']['showLinked']) => 'button',
        filled($row['open']['url']) => 'a',
        default => 'div',
    };
@endphp

<li
    @class(['pad-ti-originals__row', 'is-closed' => $row['closed']])
    wire:key="pad-ti-original-{{ $row['id'] }}"
    @if ($show) x-show="{{ $show }}" x-cloak @endif
>
    <{{ $tag }}
        class="pad-ti-originals__open"
        @if ($tag === 'button') type="button" wire:click="showLinked({{ $row['open']['showLinked'] }})" @endif
        @if ($tag === 'a') href="{{ $row['open']['url'] }}" @endif
    >
        <span class="pad-ti-originals__subject">{{ $row['subject'] }}</span>

        @if ($row['status'])
            <span class="pad-ti-originals__badge">
                <x-filament::badge size="sm" :color="$row['status']->colorPalette">{{ $row['status']->display_name }}</x-filament::badge>
            </span>
        @endif

        <span class="pad-ti-originals__meta">{{ $row['meta'] }}</span>

        <span class="pad-ti-originals__state">
            @if ($row['handler'])
                <x-filament::avatar :src="filament()->getUserAvatarUrl($row['handler'])" alt="" size="pad-ti-originals__avatar" />
            @endif
            <span>{{ $row['line'] }}</span>
        </span>

        @if ($row['relay'])
            <span class="pad-ti-originals__relay">
                <x-filament::badge size="sm" color="warning">{{ $row['relay'] }}</x-filament::badge>
            </span>
        @endif

        @if ($tag !== 'div')
            <span class="pad-ti-originals__chevron" aria-hidden="true">
                <x-heroicon-o-chevron-right class="fi-icon fi-size-sm" />
            </span>
        @endif
    </{{ $tag }}>

    @if ($canChange)
        <span class="pad-ti-originals__remove">
            {{ ($this->removeOriginalAction)(['original' => $row['id']]) }}
        </span>
    @endif
</li>
