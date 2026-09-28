<?php

namespace Padmission\Tickets\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/*
 * Plain text a ticket keeps and shows as written, such as its subject or an
 * attachment's file name, may not carry markup: an HTML tag, or a script or
 * data URL. A "<" that starts no tag, as in "Rent < 200", is text and passes.
 */
class PlainText implements ValidationRule
{
    private const string TAG = '<[a-z!\/?]';

    private const string URL = '(?:java|vb)script\s*:|data\s*:\s*[a-z]+\/[\w.+-]+';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && static::hasMarkup($value)) {
            $fail('padmission-tickets::validation.plain_text')->translate();
        }
    }

    public static function hasMarkup(string $value): bool
    {
        return preg_match('/'.self::TAG.'|'.self::URL.'/i', $value) === 1;
    }

    // For text nobody typed, such as a subject taken from a conversation, the markup is removed instead.
    public static function clean(string $value): string
    {
        $value = preg_replace('/'.self::TAG.'[^>]*>?/i', ' ', $value) ?? $value;

        return Str::squish(preg_replace('/'.self::URL.'/i', '', $value) ?? $value);
    }
}
