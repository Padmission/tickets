<div class="flex items-center">
    <button @click="window.dispatchEvent(new CustomEvent('copilot-open'))" type="button"
        class="fi-icon-btn group relative flex items-center justify-center rounded-lg outline-none transition duration-150 focus-visible:ring-2 focus-visible:ring-primary-500 hover:bg-primary-50 dark:hover:bg-primary-500/10 w-9 h-9 -mx-1"
        title="{{ __('filament-copilot::filament-copilot.open_copilot') }} (Ctrl+Shift+K)">
        <x-filament::icon icon="heroicon-o-sparkles"
            class="w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-primary-500 dark:group-hover:text-primary-400 transition-colors duration-150" />
    </button>
</div>
