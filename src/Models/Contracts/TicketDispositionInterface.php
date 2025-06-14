<?php

namespace Padmission\Tickets\Models\Contracts;


/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @property ?string $panel
 * @method int|string getKey()
 */
interface TicketDispositionInterface
{
    public function __set(
        $key,
        $value
    );
    public function __get(
        $key
    );
    public function __isset(
        $key
    );
}
