@use(Filament\Support\Facades\FilamentAsset)
<div wire:ignore>
    <chat-widget
        id="chat-widget"
    />

    <script src="{{ FilamentAsset::getScriptSrc('chat-widget', package: 'padmission-tickets') }}" type="module"></script>
    <script src="{{ FilamentAsset::getScriptSrc('chat-component', package: 'padmission-tickets') }}" type="module"></script>
</div>
