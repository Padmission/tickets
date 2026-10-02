<?php

namespace Padmission\Tickets\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/*
 * An address the app links to, such as the page a ticket was opened from: a
 * web or mail address, or one relative to the app. Anything else, a
 * javascript: or data: URL above all, would run or load something else for
 * whoever follows the link. Browsers ignore whitespace and control
 * characters inside a scheme, so they go before matching.
 */
class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! static::isSafe($value)) {
            $fail('padmission-tickets::validation.safe_url')->translate();
        }
    }

    public static function isSafe(string $url): bool
    {
        $url = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if ($url === '') {
            return false;
        }

        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $scheme)) {
            // Relative to this app only: browsers read a leading // or a backslash in its place as another host.
            return preg_match('#^[/\\\\][/\\\\]#', $url) !== 1;
        }

        return in_array(strtolower($scheme[1]), ['http', 'https', 'mailto'], true);
    }
}
