@php
    use Filament\Facades\Filament;
    use Filament\Support\Facades\FilamentAsset;use Padmission\Tickets\TicketPlugin;

    $config = TicketPlugin::get()->getChatWidgetConfig();

    $config = clone $config;
    $config->allowScreenshots(false);

    $primaryColor = $config->getPrimaryColor();
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

        chat-component {
            --color-surface: transparent;
            --composer-bg: transparent;
        }

        @if ($primaryColor)
            chat-component {
                --color-primary: rgb({{ $primaryColor }});
            }
        @endif
    </style>

    <chat-component
        id="supporter-chat"
        ticket-id="{{ $this->record->id }}"
        config="{{ $config->toJs() }}"
        scroll-threshold="100"
        polling-interval="10000"
        has-elevated-rights="true"
    ></chat-component>

    <script>
        const chat = document.getElementById('supporter-chat')

        chat.addEventListener('message-sent', (event) => {
            console.log('got a message from chat', Livewire)
            Livewire.dispatch('message-sent');
        })
    </script>

    <script
        src="{{ FilamentAsset::getScriptSrc('chat-widget', package: 'padmission/tickets') }}"
        type="module"
    >
    </script>
</div>
