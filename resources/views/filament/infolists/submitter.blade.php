@php
    use Filament\Facades\Filament;

    $ticket = $getRecord();
@endphp
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="avatar-entry">
        @if ($ticket->submitter)
            @php
                $avatarUrl = Filament::getUserAvatarUrl($ticket->submitter);
                $name = Filament::getUserName($ticket->submitter);
            @endphp

            <x-filament::avatar
                :src="$avatarUrl"
                :name="$name"
                size="sm"
            />

            <span>
                {{ $name }}
            </span>
        @elseif($ticket->submitter_data)
            {{ $ticket->submitter_data->name }}

            <div class="avatar-entry__email">
                {{ $ticket->submitter_data->email }}
            </div>
        @endif
    </div>
</x-dynamic-component>
