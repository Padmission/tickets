<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Padmission\Tickets\Models\TicketLastSeen;
use Padmission\Tickets\TicketPlugin;

trait InteractsWithLastSeen
{
    public function getNotificationRecipients(): Collection
    {
        return collect([$this->assignee, $this->submitter])
            ->filter()
            ->unique(function ($user) {
                return $user->getKey();
            });
    }

    // Can be overridden in specific implementations to add business rules about when to send notifications
    public function shouldSendNotification(string $type): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<Model&Authenticatable, $this>
     */
    public function submitter(): BelongsTo
    {
        $relation = $this->belongsTo(
            TicketPlugin::resolveUserModelClass(),
            'submitter_id'
        );

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'submitter']);
        }

        return $relation;
    }

    public function ticketLastSeen(): HasMany
    {
        $relation = $this->hasMany(
            TicketPlugin::resolveModelClass(TicketLastSeen::class),
            'ticket_id'
        );

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'ticketLastSeen']);
        }

        return $relation;
    }

    public function hasUnreadMessagesFor(Authenticatable $user): bool
    {
        $lastSeen = $this->ticketLastSeen()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if (! $lastSeen || ! $lastSeen->last_seen_activity_id) {
            return $this->ticketActivities()->exists();
        }

        return $this->ticketActivities()
            ->where('id', '>', $lastSeen->last_seen_activity_id)
            ->exists();
    }
}
