<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivityType;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @property ?TicketInterface $ticket
 * @property ?string $panel
 * @property ?int $user_id
 * @property ?ActivityType $type
 */
interface TicketActivityInterface
{
    public function __set($key, $value);

    public function __get($key);

    public function __isset($key);

    public function ticket(): BelongsTo;

    public function user(): BelongsTo;
}
