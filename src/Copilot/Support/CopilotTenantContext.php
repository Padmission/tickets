<?php

declare(strict_types=1);

namespace Padmission\Tickets\Copilot\Support;

use Closure;

class CopilotTenantContext
{
    protected static ?Closure $resolver = null;

    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * @return array{type: string, id: int|string}|null
     */
    public static function current(): ?array
    {
        if (! static::$resolver) {
            return null;
        }

        $result = (static::$resolver)();

        if (! is_array($result) || empty($result['type']) || empty($result['id'])) {
            return null;
        }

        return $result;
    }
}
