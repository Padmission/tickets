<?php

namespace Padmission\Tickets\Support;

use Illuminate\Support\HtmlString;

/*
 * The ticket emails are Markdown, and Blade's escaping leaves Markdown's own
 * syntax alone, so text someone typed, a subject, a message or a name, could
 * spell out a link or an image staff would follow or load. Every character
 * Markdown or HTML reads as syntax is written as a character reference,
 * which both read as the character itself, and blank lines are dropped so
 * no line can start a block of its own or end the table it sits in.
 */
class MailText
{
    public static function escape(?string $text): HtmlString
    {
        $lines = array_filter(
            array_map(trim(...), preg_split('/\R/u', (string) $text) ?: []),
            fn (string $line): bool => $line !== '',
        );

        return new HtmlString(implode("\n", array_map(
            fn (string $line): string => (string) preg_replace_callback(
                '/[\\\\`*_{}\[\]()#+\-.!|~=<>&"\']/',
                fn (array $match): string => '&#'.ord($match[0]).';',
                $line,
            ),
            $lines,
        )));
    }

    // A message's HTML, as the recent activity quotes it: its text only.
    public static function fromHtml(?string $html): HtmlString
    {
        return static::escape(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
