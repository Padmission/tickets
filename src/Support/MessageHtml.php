<?php

namespace Padmission\Tickets\Support;

use Illuminate\Validation\ValidationException;
use Tiptap\Core\DOMParser;
use Tiptap\Core\DOMSerializer;
use Tiptap\Core\Schema;
use Tiptap\Extensions\StarterKit;
use Tiptap\Marks\Link;
use Tiptap\Nodes\CodeBlock;
use TypeError;

/*
 * The chat draws a message's HTML as it is, so every path that stores one keeps
 * only what the composer and the actions' rich editors can write. The value is
 * always parsed as HTML: Tiptap's own sanitize() takes any string that decodes
 * as JSON for a document and hands it back untouched, markup and all.
 */
class MessageHtml
{
    // What the content column, a MySQL TEXT, holds.
    public const MAX_BYTES = 65535;

    /*
     * Cleaning writes characters out as entities, so cleaned HTML can run far
     * longer than what was typed: a message is refused, under the field it
     * came from, rather than failing to save.
     */
    public static function sanitizeToFit(string $html, string $field): string
    {
        $clean = static::sanitize($html);

        if (strlen($clean) > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => __('padmission-tickets::validation.message_too_long')]);
        }

        return $clean;
    }

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $schema = new Schema([
            new StarterKit(['codeBlock' => false]),
            // A link keeps its address only and always gets the target and rel the mark adds:
            // a class, target or rel the sender chose could cover the page or reach back into it.
            new class extends Link
            {
                public function addAttributes(): array
                {
                    return ['href' => []];
                }
            },
            // A code block keeps no class: the sender could name one of the panel's own.
            new class extends CodeBlock
            {
                public function addAttributes(): array
                {
                    return [];
                }
            },
        ]);

        try {
            $document = (new DOMParser($schema))->process($html);
        } catch (TypeError) {
            // Tiptap's parser finds no body when the markup is nothing but what it drops, a lone script for one.
            return '';
        }

        return (new DOMSerializer($schema))->process($schema->apply($document));
    }
}
