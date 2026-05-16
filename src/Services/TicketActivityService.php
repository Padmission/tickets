<?php

namespace Padmission\Tickets\Services;

use Illuminate\Support\Collection;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketLastSeen;

class TicketActivityService
{
    public function getActivities(
        Ticket $ticket,
        ?int $offsetId = null,
        ?int $limit = null,
        ?string $view = null,
    ): Collection {
        $currentSender = auth()->id() === $ticket->submitter_id
            ? ActivitySender::User
            : ActivitySender::Supporter;

        $view ??= $currentSender === ActivitySender::Supporter && auth()->user()?->can('manage', $ticket)
            ? 'supporter'
            : 'support';

        return $ticket
            ->ticketActivities()
            ->with('user')
            ->whereIn('type', $this->getActivityTypesForView($view))
            ->when($offsetId, fn ($query) => $query->where('id', '>', $offsetId))
            ->when($limit, fn ($query) => $query->limit($limit))
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (TicketActivity $message) use ($currentSender) {
                $message->side = match (true) {
                    $message->sender === ActivitySender::System => ActivitySide::System,
                    $message->sender === $currentSender => ActivitySide::Me,
                    default => ActivitySide::Other,
                };

                return $message;
            });

    }

    public function getUnreadActivities(
        Ticket $ticket,
        $notifiable,
        int $maxEvents
    ): Collection {
        $lastSeen = $this->getLastSeen($ticket, $notifiable);
        $offsetId = max($lastSeen?->last_notified_activity_id, $lastSeen?->last_seen_activity_id, 0);

        return $this->getActivities($ticket, $offsetId, $maxEvents + 1)->reverse();
    }

    public function getActivityTypesForSender(Ticket $ticket, $currentSender): array
    {
        $view = $currentSender === ActivitySender::Supporter && auth()->user()?->can('manage', $ticket)
            ? 'supporter'
            : 'support';

        return $this->getActivityTypesForView($view);
    }

    public function getActivityTypesForView(string $view): array
    {
        if ($view === 'supporter') {
            return ActivityType::cases();
        }

        return [
            ActivityType::Opened,
            ActivityType::Message,
            ActivityType::Escalated,
            ActivityType::AssigneeChanged,
            ActivityType::Joined,
            ActivityType::Closed,
        ];
    }

    public function getLastSeen(Ticket $ticket, $notifiable): ?TicketLastSeen
    {
        /** @var TicketLastSeen|null $lastSeen */
        $lastSeen = $ticket
            ->ticketLastSeen()
            ->where('user_id', $notifiable->getKey())
            ->first();

        return $lastSeen;
    }

    public function markAsSeen(Ticket $ticket, $notifiable, int $activityId): void
    {
        $ticket->ticketLastSeen()->updateOrCreate(
            [
                'user_id' => $notifiable->getKey(),
                'ticket_id' => $ticket->id,
            ],
            [
                'last_seen_activity_id' => $activityId,
            ]
        );
    }

    public function markAsSent(Ticket $ticket, $notifiable, int $activityId): void
    {
        $ticket->ticketLastSeen()->updateOrCreate(
            [
                'user_id' => $notifiable->getKey(),
                'ticket_id' => $ticket->id,
            ],
            [
                'last_notified_activity_id' => $activityId,
            ]
        );
    }
}
