@php
    use Filament\Facades\Filament;
    use Filament\Support\Facades\FilamentAsset;

    $primaryColor = data_get(Filament::getCurrentPanel()->getColors(), 'primary.600');
@endphp
<div
    class="pad-ti-chat-wrapper"
    wire:ignore
>
    <style>
        .pad-ti-chat-section .fi-section-content {
            padding: 0;
        }

        .pad-ti-chat-wrapper {
            height: 90svh;

            @media (width > 40rem) {
                height: 60svh;
            }
        }

        @if ($primaryColor)
            chat-component {
                --color-primary: rgb({{ $primaryColor }});
            }
        @endif
    </style>

    <chat-component
        ticket-id="{{ $this->record->id }}"
        scroll-threshold="100"
        polling-interval="10000"
        has-elevated-rights="true"
    ></chat-component>

    <script
        src="{{ FilamentAsset::getScriptSrc('chat-component', package: 'padmission-tickets') }}"
        type="module"
    >
    </script>
</div>
