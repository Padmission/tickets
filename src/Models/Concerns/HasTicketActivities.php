<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\TicketPlugin;

trait HasTicketActivities
{
    public function addTicketActivity(
        ActivityType $type,
        ActivitySender $sender = ActivitySender::User,
        ?int $userId = null,
        array $data = [],
        ?string $content = null,
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
     * @return HasMany<TicketActivity,$this>
     */
    public function ticketActivities(): HasMany
    {
        $relation = $this->hasMany(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        );

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'ticketActivities']);
        }

        return $relation;
    }

    public function latestMessage(): HasOne
    {
        $relation = $this->hasOne(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        )->ofMany(['created_at' => 'max'], function ($query) {
            $query->where('type', ActivityType::Message->value);
        });

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'latestMessage']);
        }

        return $relation;
    }

    public function latestUserMessage(): HasOne
    {
        $relation = $this->hasOne(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        )->ofMany(['created_at' => 'max'], function ($query) {
            $query
                ->where('type', ActivityType::Message->value)
                ->where('sender', ActivitySender::User->value);
        });

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'latestUserMessage']);
        }

        return $relation;
    }
}
