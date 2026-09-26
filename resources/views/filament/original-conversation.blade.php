@php
    use Filament\Facades\Filament;
    use Padmission\Tickets\Enums\ActivitySender;
    use Padmission\Tickets\Enums\ActivityType;
    use Padmission\Tickets\Filament\Infolists\UserDescription;
    use Padmission\Tickets\TicketPlugin;

    $plugin = TicketPlugin::get();
    $viewer = Filament::auth()->user();
@endphp

<div class="pad-ti-transcript">
    @foreach ($originalTickets as $ticket)
        @php
            $submitterName = $ticket->submitter
                ? Filament::getUserName($ticket->submitter)
                : $ticket->submitter_data?->name;
            $submitterDescription = UserDescription::render($plugin->describeUser($ticket->submitter, $ticket));
            $assigneeName = match (true) {
                $ticket->assignee !== null => Filament::getUserName($ticket->assignee),
                filled($ticket->assignee_id) => TicketPlugin::find($ticket->panel)?->getSupportTeamName() ?? __('padmission-tickets::tickets.resources.tickets.assigned_elsewhere'),
                default => null,
            };
        @endphp

        <section class="pad-ti-transcript__ticket">
            <header class="pad-ti-transcript__header">
                <div class="pad-ti-transcript__title">
                    <x-filament::badge size="sm" color="gray">#{{ $ticket->getKey() }}</x-filament::badge>
                    <x-filament::badge size="sm" :color="$ticket->status?->colorPalette">
                        {{ $ticket->status?->display_name }}
                    </x-filament::badge>
                    <span>{{ $ticket->subject }}</span>
                </div>

                <dl class="pad-ti-transcript__people">
                    <div>
                        <dt>{{ __('padmission-tickets::tickets.actions.view_original_conversation.requested_by') }}</dt>
                        <dd>
                            {{ $submitterName ?? '-' }}
                            @if (filled($submitterDescription))
                                <span class="pad-ti-transcript__muted">({{ $submitterDescription }})</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt>{{ __('padmission-tickets::tickets.actions.view_original_conversation.assigned_to') }}</dt>
                        <dd>{{ $assigneeName ?? __('padmission-tickets::tickets.resources.tickets.unassigned') }}</dd>
                    </div>
                </dl>
            </header>

            <ol class="pad-ti-transcript__messages">
                @forelse ($activityService->getActivities($ticket, user: $viewer) as $activity)
                    @if ($activity->sender === ActivitySender::System || ! in_array($activity->type, [ActivityType::Message, ActivityType::InternalMessage]))
                        <li class="pad-ti-transcript__event">
                            {{ $activity->plainTextContent() }}
                            · {{ $activity->created_at?->format($plugin->getDateTimeDisplayFormat()) }}
                        </li>
                    @else
                        <li @class([
                            'pad-ti-transcript__message',
                            'pad-ti-transcript__message--internal' => $activity->type === ActivityType::InternalMessage,
                        ])>
                            <div class="pad-ti-transcript__meta">
                                <strong>{{ $activity->senderName }}</strong>
                                · {{ $activity->created_at?->format($plugin->getDateTimeDisplayFormat()) }}
                                @if ($activity->type === ActivityType::InternalMessage)
                                    · {{ __('padmission-tickets::tickets.actions.view_original_conversation.internal_note') }}
                                @endif
                            </div>

                            <div class="pad-ti-transcript__content">
                                {!! str($activity->content)->sanitizeHtml() !!}
                            </div>

                            @if ($activity->attachments->isNotEmpty())
                                <div class="pad-ti-transcript__muted">
                                    {{ trans_choice('padmission-tickets::tickets.actions.view_original_conversation.attachments', $activity->attachments->count()) }}
                                </div>
                            @endif
                        </li>
                    @endif
                @empty
                    <li class="pad-ti-transcript__event">
                        {{ __('padmission-tickets::tickets.actions.view_original_conversation.empty') }}
                    </li>
                @endforelse
            </ol>
        </section>
    @endforeach
</div>
