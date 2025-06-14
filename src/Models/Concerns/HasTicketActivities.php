<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\TicketPlugin;

trait HasTicketActivities
{
    /**
     * Add an activity to the ticket
     */
    public function addActivity(
        ActivityType $type,
        string $content,
        ActivitySender $sender = ActivitySender::User,
        ?int $userId = null,
        array $data = []
    ): TicketActivity {
        $userId ??= auth()->id();

        return $this->ticketActivities()->create([
            'type' => $type,
            'content' => $content,
            'sender' => $sender,
            'user_id' => $userId,
            'data' => $data,
        ]);
    }

    /**
     * Add a user message activity
     */
    public function addMessage(string $content, ?int $userId = null): TicketActivity
    {
        return $this->addActivity(
            ActivityType::Message,
            $content,
            ActivitySender::User,
            $userId
        );
    }

    /**
     * Add a system note activity
     */
    public function addSystemNote(string $content, array $data = []): TicketActivity
    {
        return $this->addActivity(
            ActivityType::Note,
            $content,
            ActivitySender::System,
            null,
            $data
        );
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities(int $limit = 10): Collection
    {
        return $this->ticketActivities()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get activities of a specific type
     */
    public function getActivitiesByType(ActivityType $type): Collection
    {
        return $this->ticketActivities()
            ->where('type', $type)
            ->with('user')
            ->get();
    }

    /**
     * Get the ticket activities relationship
     */
    public function ticketActivities(): HasMany
    {
        return $this->hasMany(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        );
    }

    /**
     * Get the latest message relationship
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        )->ofMany(['created_at' => 'max'], function ($query) {
            $query->where('type', ActivityType::Message->value);
        });
    }

    /**
     * Get the latest activity relationship
     */
    public function latestActivity(): HasOne
    {
        return $this->hasOne(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        )->latestOfMany();
    }
}
