<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $type
 * @property string $content
 * @property string $sender
 * @property string|null $side
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface IsTicketActivity
{
	public function ticket(): BelongsTo;
	public function user() : BelongsTo;
}
