@php
    use Filament\Facades\Filament;

    $ticket = $getRecord();
@endphp
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if ($ticket->submitter)
        @php
            $avatarUrl = Filament::getUserAvatarUrl($ticket->submitter);
            $name = Filament::getUserName($ticket->submitter);
        @endphp
        <div class="flex gap-2">
            <x-filament::avatar
                :src="$avatarUrl"
                :name="$name"
                size="sm"
            />

            <div class="text-sm leading-6 text-gray-950 dark:text-white">
                {{ $name }}
            </div>
        </div>
    @elseif($ticket->submitter_data)
        <div class="text-sm leading-6 text-gray-950 dark:text-white">
            {{ $ticket->submitter_data->name }}
            <div class="break-words">
                {{ $ticket->submitter_data->email }}
            </div>
        </div>
    @endif
</x-dynamic-component>
