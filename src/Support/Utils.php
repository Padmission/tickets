<?php

declare(strict_types=1);

namespace Padmission\Tickets\Support;

class Utils
{
    public static function isTenantEnabled(): bool
    {
        return config('padmission-tickets.tenant.enabled', false);
    }
}
