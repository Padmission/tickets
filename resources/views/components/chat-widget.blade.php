@use(Filament\Support\Facades\FilamentAsset)
<div wire:ignore>
    <chat-widget
        id="chat-widget"
        default-message="
            Our support team of real people are here to help. Please give us as much detail as possible and we will respond as soon as someone is available.
        "
    />

    <script src="{{ FilamentAsset::getScriptSrc('chat-widget', package: 'padmission-tickets') }}" type="module"></script>
    <script src="{{ FilamentAsset::getScriptSrc('chat-component', package: 'padmission-tickets') }}" type="module"></script>
</div>
