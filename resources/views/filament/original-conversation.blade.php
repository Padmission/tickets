@php
    use Filament\Facades\Filament;
    use Padmission\Tickets\Enums\ActivitySender;
    use Padmission\Tickets\Enums\ActivityType;
    use Padmission\Tickets\Filament\Infolists\UserDescription;
    use Padmission\Tickets\Services\TicketAssignee;
    use Padmission\Tickets\TicketPlugin;

    $plugin = TicketPlugin::get();
    $titleRow ??= 'full';
    $lastSeenId ??= null;
    $submitterActions ??= null;
    $viewer = Filament::auth()->user();
@endphp

<div class="pad-ti-transcript">
    @foreach ($originalTickets as $ticket)
        @php
            $submitterIsViewer = $ticket->isSubmittedBy($viewer);
            $submitterName = match (true) {
                $submitterIsViewer => __('padmission-tickets::tickets.side_you'),
                $ticket->submitter !== null => Filament::getUserName($ticket->submitter),
                default => $ticket->submitter_data?->name,
            };
            // Roles help to place a stranger; beside the chat they only crowd the header.
            $submitterDescription = $submitterIsViewer || $titleRow === 'none'
                ? null
                : UserDescription::render($plugin->describeUser($ticket->submitter, $ticket));
            $submitterLabel = match (true) {
                ! $ticket->isEscalation() => __('padmission-tickets::tickets.actions.view_original_conversation.requested_by'),
                $ticket->isInCurrentPanel() => __('padmission-tickets::tickets.resources.tickets.contact'),
                default => __('padmission-tickets::tickets.resources.tickets.handled_by'),
            };
            $assignee = $ticket->isNotInCurrentPanel() ? TicketAssignee::for($ticket) : $ticket->assignee;
            $assigneeName = match (true) {
                $assignee !== null && filled($ticket->assignee_id) && (string) $ticket->assignee_id === (string) $viewer?->getAuthIdentifier() => __('padmission-tickets::tickets.side_you'),
                $assignee !== null => Filament::getUserName($assignee),
                filled($ticket->assignee_id) => TicketPlugin::find($ticket->panel)?->getSupportTeamName() ?? __('padmission-tickets::tickets.resources.tickets.assigned_elsewhere'),
                default => null,
            };
        @endphp

        <section class="pad-ti-transcript__ticket">
            <header class="pad-ti-transcript__header">
                @if ($titleRow !== 'none')
                    <div class="pad-ti-transcript__title">
                        @if ($titleRow === 'full')
                            <x-filament::badge size="sm" color="gray">#{{ $ticket->getKey() }}</x-filament::badge>
                        @endif
                        <x-filament::badge size="sm" :color="$ticket->status?->colorPalette">
                            {{ $ticket->status?->display_name }}
                        </x-filament::badge>
                        @if ($titleRow === 'full')
                            <span>{{ $ticket->subject }}</span>
                        @endif
                    </div>
                @endif

                <dl class="pad-ti-transcript__people">
                    <div>
                        <dt>{{ $submitterLabel }}</dt>
                        <dd>
                            {{ $submitterName ?? '-' }}
                            @if ($submitterActions)
                                <span class="pad-ti-transcript__person-actions">{{ $submitterActions }}</span>
                            @endif
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

            @php
                $activities = $activityService->getActivities($ticket, user: $viewer);
                $firstUnreadId = $lastSeenId === null ? null : $activities->first(fn ($activity): bool => $activity->getKey() > $lastSeenId)?->getKey();
            @endphp

            <ol class="pad-ti-transcript__messages">
                @forelse ($activities as $activity)
                    @if ($activity->sender === ActivitySender::System || ! in_array($activity->type, [ActivityType::Message, ActivityType::InternalMessage]))
                        <li class="pad-ti-transcript__event" @if ($activity->getKey() === $firstUnreadId) data-pad-ti-first-unread @endif>
                            {{ $activity->plainTextContent() }}
                            · {{ TicketPlugin::formatMessageTime($activity->created_at) }}
                        </li>
                    @else
                        <li @class([
                            'pad-ti-transcript__message',
                            'pad-ti-transcript__message--internal' => $activity->type === ActivityType::InternalMessage,
                        ]) @if ($activity->getKey() === $firstUnreadId) data-pad-ti-first-unread @endif>
                            <div class="pad-ti-transcript__meta">
                                {{-- A message reads "You" only when you wrote it. --}}
                                <strong>{{ filled($activity->user_id) && (string) $activity->user_id === (string) $viewer?->getAuthIdentifier() ? __('padmission-tickets::tickets.side_you') : $activity->senderName }}</strong>
                                · {{ TicketPlugin::formatMessageTime($activity->created_at) }}
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
