<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Contracts\IsTicketActivity;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\TicketPlugin;

trait HasTicketActivities
{
    public function addTicketActivity(
        ActivityType $type,
        ?string $content,
        ActivitySender $sender = ActivitySender::User,
        ?int $userId = null,
        array $data = []
    ): IsTicketActivity {
        $userId ??= auth()->id();

		$ticketActivity = $this->ticketActivities()->create([
			'type' => $type,
			'content' => $content ?? '',
			'sender' => $sender,
			'user_id' => $userId,
			'data' => $data,
		]);

		if (!$ticketActivity instanceof IsTicketActivity) {
			throw new \LogicException('Invalid return');
		}

        return $ticketActivity;
    }

	/**
	 * @return HasMany<TicketActivity, self>
	 */
    public function ticketActivities(): HasMany
    {
        return $this->hasMany(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        );
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticket_id'
        )->ofMany(['created_at' => 'max'], function ($query) {
            $query->where('type', ActivityType::Message->value);
        });
    }
}
