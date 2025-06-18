@php
    use Filament\Facades\Filament;
    use Filament\Support\Facades\FilamentAsset;
    use Padmission\Tickets\Models\Ticket;
    use Padmission\Tickets\Services\TicketAuth;use Padmission\Tickets\TicketPlugin;

    $config = TicketPlugin::get()->getChatWidgetConfig();

    if (! $config->getAllowEmailAuthentication() && ! Filament::auth()->user()?->can('create', TicketPlugin::resolveModelClass(Ticket::class))) {
        return;
    }

    $auth = resolve(TicketAuth::class);

@endphp

<div wire:ignore>
    <chat-widget
        id="chat-widget"
        config="{{ $config->toJs() }}"
    />

    <script
        src="{{ FilamentAsset::getScriptSrc('chat-widget', package: 'padmission-tickets') }}"
        type="module"
    ></script>
    <script
        src="{{ FilamentAsset::getScriptSrc('chat-component', package: 'padmission-tickets') }}"
        type="module"
    ></script>

    <style>
        chat-widget {
            --color-primary: {{ $config->getPrimaryColor() }};
        }
    </style>
</div>
