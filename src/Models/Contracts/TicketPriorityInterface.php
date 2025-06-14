<?php

namespace Padmission\Tickets\Models\Contracts;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @property ?string $panel
 */
interface TicketPriorityInterface
{
    public function __set($key, $value);

    public function __get($key);

    public function __isset($key);
}
