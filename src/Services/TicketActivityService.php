<?php

namespace Padmission\Tickets\Services;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;

class TicketActivityService
{
    /**
     * @var (Closure(Authenticatable, Ticket): bool)|null
     */
    protected ?Closure $skipSeenTracking = null;

    /**
     * Keep markAsSeen() from moving a user's read pointer while the callback
     * returns true, such as while someone else is browsing as that user.
     *
     * @param  (Closure(Authenticatable, Ticket): bool)|null  $callback
     */
    public function skipSeenTrackingWhen(?Closure $callback): void
    {
        $this->skipSeenTracking = $callback;
    }

    public function getActivities(
        Ticket $ticket,
        ?int $offsetId = null,
        ?int $limit = null,
        $user = null,
    ): Collection {
        $user ??= auth()->user();

        $currentSender = $user?->getKey() === $ticket->submitter_id
            ? ActivitySender::User
            : ActivitySender::Supporter;

        return $ticket
            ->ticketActivities()
            ->with('user')
            ->whereIn('type', $this->getActivityTypesForSender($ticket, $currentSender, $user))
            ->when($offsetId, fn ($query) => $query->where('id', '>', $offsetId))
            ->when($limit, fn ($query) => $query->limit($limit))
            ->orderBy('id', 'desc')
            ->get()
            ->reverse()
            ->values()
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
        $userState = $this->getUserState($ticket, $notifiable);
        $offsetId = max($userState?->last_notified_activity_id, $userState?->last_seen_activity_id, 0);

        return $this->getActivities($ticket, $offsetId, $maxEvents + 1, $notifiable);
    }

    public function getActivityTypesForSender(Ticket $ticket, $currentSender, $user = null): array
    {
        $user ??= auth()->user();

        if (
            $currentSender === ActivitySender::Supporter
            && $user
            && Gate::forUser($user)->allows('manage', $ticket)
        ) {
            return array_filter(
                ActivityType::cases(),
                fn (ActivityType $type) => $type !== ActivityType::TurnChanged
            );
        }

        return [
            ActivityType::Opened,
            ActivityType::Message,
            ActivityType::Closed,
        ];
    }

    public function getUserState(Ticket $ticket, $notifiable): ?TicketUserState
    {
        /** @var TicketUserState|null $userState */
        $userState = $ticket
            ->ticketUserStates()
            ->where('user_id', $notifiable->getKey())
            ->first();

        return $userState;
    }

    public function markAsSeen(Ticket $ticket, $notifiable, int $activityId): void
    {
        if ($this->skipSeenTracking && ($this->skipSeenTracking)($notifiable, $ticket)) {
            return;
        }

        $this->advancePointer($ticket, $notifiable, 'last_seen_activity_id', $activityId);
    }

    public function markAsSent(Ticket $ticket, $notifiable, int $activityId): void
    {
        $this->advancePointer($ticket, $notifiable, 'last_notified_activity_id', $activityId);
    }

    protected function advancePointer(Ticket $ticket, $notifiable, string $column, int $activityId): void
    {
        $advanceExisting = fn (): int => $ticket
            ->ticketUserStates()
            ->where('user_id', $notifiable->getKey())
            ->where(fn ($query) => $query
                ->whereNull($column)
                ->orWhere($column, '<', $activityId))
            ->update([$column => $activityId]); // @phpstan-ignore argument.type

        if ($advanceExisting() > 0) {
            return;
        }

        if ($ticket->ticketUserStates()->where('user_id', $notifiable->getKey())->exists()) {
            return;
        }

        try {
            $ticket->ticketUserStates()->create([ // @phpstan-ignore argument.type
                'user_id' => $notifiable->getKey(),
                $column => $activityId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $advanceExisting();
        }
    }
}
