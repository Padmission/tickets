<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Padmission\Tickets\Models\TicketNotification;
use Padmission\Tickets\TicketPlugin;

trait InteractsWithNotifications
{
    /**
     * Get users who should receive notifications for this ticket
     */
    public function getNotificationRecipients(): Collection
    {
        return collect([$this->assignee, $this->submitter])
            ->filter()
            ->unique(function ($user) {
                return $user->getKey();
            });
    }

    /**
     * Check if notifications should be sent for this ticket
     */
    public function shouldSendNotification(string $type): bool
    {
        // Can be overridden in specific implementations
        // to add business rules about when to send notifications
        return true;
    }

    /**
     * Mark that a notification was sent to a user
     */
    public function markNotificationSent(Authenticatable $user): TicketNotification
    {
        return $this->ticketNotifications()->create([
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Get the last notification sent to a user
     */
    public function getLastNotificationFor(Authenticatable $user): ?TicketNotification
    {
        return $this->ticketNotifications()
            ->where('user_id', $user->getKey())
            ->latest()
            ->first();
    }

    /**
     * Check if a user has been notified about this ticket
     */
    public function hasNotified(Authenticatable $user): bool
    {
        return $this->ticketNotifications()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * Get the submitter relationship
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'submitter_id'
        );
    }

    /**
     * Get the ticket notifications relationship
     */
    public function ticketNotifications(): HasMany
    {
        return $this->hasMany(
            TicketPlugin::resolveModelClass(TicketNotification::class),
            'ticket_id'
        );
    }
}
