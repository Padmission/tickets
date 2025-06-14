<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Collection;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @property ?string $panel
 */
interface TicketStatusInterface
{
    public function __set($key, $value);

    public function __get($key);

    public function __isset($key);

    public static function getOpenStatuses(): Collection;

    /**
     * Get the closed status.
     *
     * @return static
     */
    public static function getClosedStatus(): self;
}
