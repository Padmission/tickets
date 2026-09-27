@php
    use Filament\Facades\Filament;
    use Filament\Support\Facades\FilamentAsset;
    use Padmission\Tickets\Services\TicketAuth;
    use Padmission\Tickets\TicketPlugin;

    $config = TicketPlugin::get()->getChatWidgetConfig();

    $config = clone $config;
    $config->allowScreenshots(false);

    $primaryColor = $config->getPrimaryColor();

    $canReply = resolve(TicketAuth::class)->canReply($this->record, Filament::auth()->user());
    $isSubmitter = Filament::auth()->id() === $this->record->submitter_id;
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
            --color-primary: {{ $config->getPrimaryColor() }}
        }
    </style>

    <chat-component
        id="supporter-chat"
        ticket-id="{{ $this->record->id }}"
        config="{{ $config->toJs() }}"
        scroll-threshold="100"
        polling-interval="10000"
        has-elevated-rights="{{ $isSubmitter ? 'false' : 'true' }}"
        placeholder="{{ $placeholder ?? __('padmission-tickets::chat.chat.placeholder') }}"
        closed-empty-message="{{ $closedEmptyMessage ?? '' }}"
        can-reply="{{ $canReply ? 'true' : 'false' }}"
        keep-waiting-style="{{ TicketPlugin::get()->getKeepWaitingStyle() }}"
        timezone="{{ TicketPlugin::get()->getDisplayTimezone() }}"
    ></chat-component>

    <script>
        const chat = document.getElementById('supporter-chat')

        chat.addEventListener('message-sent', (event) => {
            Livewire.dispatch('message-sent');
        })
    </script>

    <script
        src="{{ FilamentAsset::getScriptSrc('chat-widget', package: 'padmission/tickets') }}"
        type="module"
    >
    </script>
</div>
