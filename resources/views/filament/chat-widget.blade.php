@php
    use Filament\Facades\Filament;
    use Filament\Support\Facades\FilamentAsset;
    use Padmission\Tickets\Models\Ticket;
    use Padmission\Tickets\TicketPlugin;

    if (Filament::auth()->user()?->cannot('create', TicketPlugin::resolveModelClass(Ticket::class))) {
        return;
    }

    $config = TicketPlugin::get()->getChatWidgetConfig();
@endphp

<div wire:ignore>
    <chat-widget
        id="chat-widget"
        widget-id="panel-{{ Filament::getId() }}"
        default-message="{!! $config->getIntroMessage() !!}"
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
