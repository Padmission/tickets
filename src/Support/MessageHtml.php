<?php

namespace Padmission\Tickets\Support;

use Tiptap\Core\DOMParser;
use Tiptap\Core\DOMSerializer;
use Tiptap\Core\Schema;
use Tiptap\Extensions\StarterKit;
use Tiptap\Marks\Link;
use TypeError;

/*
 * The chat draws a message's HTML as it is, so every path that stores one keeps
 * only what the composer and the actions' rich editors can write. The value is
 * always parsed as HTML: Tiptap's own sanitize() takes any string that decodes
 * as JSON for a document and hands it back untouched, markup and all.
 */
class MessageHtml
{
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $schema = new Schema([new StarterKit, new Link]);

        try {
            $document = (new DOMParser($schema))->process($html);
        } catch (TypeError) {
            // Tiptap's parser finds no body when the markup is nothing but what it drops, a lone script for one.
            return '';
        }

        return (new DOMSerializer($schema))->process($schema->apply($document));
    }
}
