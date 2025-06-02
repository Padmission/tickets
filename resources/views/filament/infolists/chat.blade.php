@use(Filament\Support\Facades\FilamentAsset)
<div wire:ignore style="height: 60vh">
    <style>
        .pad-ti-section-chat .fi-section-content {
            padding: 0;
        }
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
