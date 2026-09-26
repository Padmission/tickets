@php
    use Filament\Facades\Filament;
    use Padmission\Tickets\Filament\Infolists\UserDescription;
    use Padmission\Tickets\TicketPlugin;

    $ticket = $getRecord();
@endphp
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="avatar-entry">
        @if ($ticket->submitter)
            @php
                $avatarUrl = Filament::getUserAvatarUrl($ticket->submitter);
                $name = Filament::getUserName($ticket->submitter);
                $description = UserDescription::render(TicketPlugin::get()->describeUser($ticket->submitter, $ticket));
            @endphp

            <x-filament::avatar
                :src="$avatarUrl"
                :name="$name"
                size="sm"
            />

            <div>
                {{ $name }}

                @if (filled($description))
                    <div class="avatar-entry__description">
                        {{ $description }}
                    </div>
                @endif
            </div>
        @elseif($ticket->submitter_data)
            {{ $ticket->submitter_data->name }}

            <div class="avatar-entry__email">
                {{ $ticket->submitter_data->email }}
            </div>
        @endif
    </div>
</x-dynamic-component>
