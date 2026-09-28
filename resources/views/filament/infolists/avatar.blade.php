@php
    use Filament\Facades\Filament;
    use Padmission\Tickets\Filament\Infolists\UserDescription;
    use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
    use Padmission\Tickets\TicketPlugin;

    $user = $getState();
    $record = $getRecord();

    if ($user) {
        $avatarUrl = Filament::getUserAvatarUrl($user);
        $name = Filament::getUserName($user);
        $isViewer = TicketResource::isAssignedToCurrentUser($record);
        $description = $isViewer ? null : UserDescription::render(TicketPlugin::get()->describeUser($user, $getRecord()));
    }
@endphp
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="avatar-entry">
        @if ($user)
            <x-filament::avatar
                :src="$avatarUrl"
                :name="$name"
                size="sm"
            />

            <div>
                {{ $isViewer ? __('padmission-tickets::tickets.side_you') : $name }}

                @if (filled($description))
                    <div class="avatar-entry__description">
                        {{ $description }}
                    </div>
                @endif
            </div>
        @elseif (filled($record?->assignee_id))
            {{-- Assigned to someone outside the viewer's scope, such as staff on a ticket escalated to another panel. --}}
            <span>
                {{ TicketPlugin::find($record->panel)?->getSupportTeamName() ?? __('padmission-tickets::tickets.resources.tickets.assigned_elsewhere') }}
            </span>
        @else
            <span class="avatar-entry__description">
                {{ __('padmission-tickets::tickets.resources.tickets.unassigned') }}
            </span>
        @endif
    </div>
</x-dynamic-component>
