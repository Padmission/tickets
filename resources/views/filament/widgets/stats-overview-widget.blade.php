@php
    $columns = $this->getColumns();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $dateRangeOptions = $this->getDateRangeOptions();
    $hasDateRangePicker = filled($dateRangeOptions);
    $hasDescription = filled($description);
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview grid gap-y-4">
    @if ($hasHeading || $hasDescription)
        <div class="fi-wi-stats-overview-header grid gap-y-1">
            @if ($hasHeading || $hasDateRangePicker)
                <div class="flex items-center justify-between gap-x-4">
                    @if($hasHeading)
                        <h3 class="fi-wi-stats-overview-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            {{ $heading }}
                        </h3>
                    @endif
                        @if($hasDateRangePicker)
                        <div class="flex-shrink-0">
                            {{ $this->form }}
                        </div>
                    @endif
                </div>
            @endif
            @if ($hasDescription)
                <p class="fi-wi-stats-overview-header-description overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </div>
    @endif
    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
        @class([
            'fi-wi-stats-overview-stats-ctn grid gap-6',
            'md:grid-cols-1' => $columns === 1,
            'md:grid-cols-2' => $columns === 2,
            'md:grid-cols-3' => $columns === 3,
            'md:grid-cols-2 xl:grid-cols-4' => $columns === 4,
        ])
    >
        @foreach ($this->getStats() as $stat)
            {{ $stat }}
        @endforeach
    </div>
</x-filament-widgets::widget>
